<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

final class GmailService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/gmail.php';
    }

    public function isConfigured(): bool
    {
        return is_file($this->config['client_secret_path']);
    }

    public function isConnected(): bool
    {
        return is_file($this->config['token_path']) && $this->loadToken() !== null;
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->clientCredentials();
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $client['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scopes']),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): void
    {
        $client = $this->clientCredentials();
        $token = $this->httpJson('POST', 'https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'redirect_uri' => $this->config['redirect_uri'],
            'grant_type' => 'authorization_code',
        ], true);
        if (empty($token['access_token'])) {
            throw new RuntimeException('Google did not return an access token.');
        }
        $this->saveToken($token);
    }

    public function disconnect(): void
    {
        $token = $this->loadToken();
        if ($token && !empty($token['access_token'])) {
            try {
                $this->httpJson('POST', 'https://oauth2.googleapis.com/revoke', [
                    'token' => $token['access_token'],
                ], true);
            } catch (Throwable $e) {
                error_log('Gmail revoke failed: ' . $e->getMessage());
            }
        }
        if (is_file($this->config['token_path'])) {
            unlink($this->config['token_path']);
        }
    }

    public function sendMessage(string $to, string $subject, string $body): array
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Supplier email is invalid.');
        }
        $headers = [
            'From' => $this->config['sender_name'] . ' <' . $this->config['sender_email'] . '>',
            'To' => $to,
            'Subject' => $this->sanitizeHeader($subject),
            'Date' => gmdate('D, d M Y H:i:s O'),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . '.mototrack@localhost>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
        ];
        $mime = '';
        foreach ($headers as $name => $value) {
            $mime .= $name . ': ' . $value . "\r\n";
        }
        $mime .= "\r\n" . str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        $result = $this->gmailRequest('POST', '/users/me/messages/send', [
            'raw' => $this->base64UrlEncode($mime),
        ]);
        return [
            'message_id' => (string)($result['id'] ?? ''),
            'thread_id' => (string)($result['threadId'] ?? ''),
        ];
    }

    public function listMessages(string $query): array
    {
        $messages = [];
        $pageToken = null;
        do {
            $queryString = http_build_query(array_filter([
                'q' => $query,
                'maxResults' => 100,
                'pageToken' => $pageToken,
            ], static fn($v) => $v !== null && $v !== ''), '', '&', PHP_QUERY_RFC3986);
            $page = $this->gmailRequest('GET', '/users/me/messages?' . $queryString);
            foreach (($page['messages'] ?? []) as $message) {
                if (!empty($message['id'])) {
                    $messages[] = $message;
                }
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken !== null);
        return $messages;
    }

    public function getMessage(string $messageId): array
    {
        return $this->gmailRequest('GET', '/users/me/messages/' . rawurlencode($messageId) . '?format=full');
    }

    public function extractMessage(array $message): array
    {
        $headers = [];
        foreach (($message['payload']['headers'] ?? []) as $header) {
            $name = strtolower((string)($header['name'] ?? ''));
            if ($name !== '') {
                $headers[$name] = (string)($header['value'] ?? '');
            }
        }
        $body = trim($this->decodeParts($message['payload'] ?? []));
        $clean = $this->cleanSupplierReplyBody($body);
        return [
            'message_id' => (string)($message['id'] ?? ''),
            'thread_id' => (string)($message['threadId'] ?? ''),
            'sender' => $headers['from'] ?? '',
            'recipient' => $headers['to'] ?? '',
            'subject' => $headers['subject'] ?? '',
            'internal_date_ms' => (int)($message['internalDate'] ?? 0),
            'timestamp' => isset($message['internalDate']) ? date('Y-m-d H:i:s', (int)$message['internalDate'] / 1000) : date('Y-m-d H:i:s'),
            'body' => $body,
            'clean_body' => $clean['body'],
            'clean_body_needs_review' => $clean['needs_review'],
        ];
    }

    /**
     * Remove only reliably identified quoted-reply material. Keep the full
     * decoded body separate for audit; callers use this field for AI/UI.
     */
    public function cleanSupplierReplyBody(string $body): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\s*\/(?:p|div|li|blockquote|tr|h[1-6])\s*>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xC2\xA0", "\xA0"], ' ', $text);
        $text = preg_replace('/[ \t]+\n/', "\n", $text) ?? $text;

        $needsReview = false;
        $marker = '/\n\s*On\s.+?(?:\n\s*)?wrote:\s*/isu';
        $parts = preg_split($marker, $text, 2);
        if (is_array($parts) && count($parts) === 2) {
            $quoted = $parts[1];
            $hasQuotedEvidence = preg_match('/(^|\n)\s*>|MotoTrack|Purchase Order:/i', $quoted) === 1;
            if ($hasQuotedEvidence) {
                $text = $parts[0];
            } else {
                $needsReview = true;
            }
        }

        $lines = preg_split('/\n/', $text) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            $kept[] = rtrim($line);
        }
        $clean = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)) ?? implode("\n", $kept));
        if ($clean === '' && trim($body) !== '') {
            $clean = trim($text);
            $needsReview = true;
        }

        return ['body' => $clean, 'needs_review' => $needsReview];
    }

    public function senderEmail(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $match)) {
            return strtolower(trim($match[1]));
        }
        return strtolower(trim((string)preg_replace('/^.*?\b([\w.+-]+@[\w.-]+)\b.*$/', '$1', $from)));
    }

    public function senderEmailAddress(): string
    {
        return $this->config['sender_email'];
    }

    private function gmailRequest(string $method, string $path, ?array $payload = null): array
    {
        $token = $this->validAccessToken();
        $url = 'https://gmail.googleapis.com/gmail/v1' . $path;
        return $this->httpJson($method, $url, $payload, false, [
            'Authorization: Bearer ' . $token,
        ]);
    }

    private function validAccessToken(): string
    {
        $token = $this->loadToken();
        if (!$token) {
            throw new RuntimeException('Gmail is not connected.');
        }
        $created = (int)($token['created'] ?? 0);
        $expiresIn = (int)($token['expires_in'] ?? 3600);
        if (!empty($token['access_token']) && time() < $created + $expiresIn - 60) {
            return (string)$token['access_token'];
        }
        if (empty($token['refresh_token'])) {
            throw new RuntimeException('Gmail access token expired and no refresh token exists. Reauthorize Gmail.');
        }
        $client = $this->clientCredentials();
        $refreshed = $this->httpJson('POST', 'https://oauth2.googleapis.com/token', [
            'client_id' => $client['client_id'],
            'client_secret' => $client['client_secret'],
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token',
        ], true);
        $this->saveToken(array_merge($token, $refreshed, ['created' => time()]));
        return (string)$refreshed['access_token'];
    }

    private function clientCredentials(): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Google OAuth credentials file is missing.');
        }
        $raw = json_decode((string)file_get_contents($this->config['client_secret_path']), true);
        $client = $raw['web'] ?? $raw['installed'] ?? null;
        if (!is_array($client) || empty($client['client_id']) || empty($client['client_secret'])) {
            throw new RuntimeException('Google OAuth credentials file is invalid.');
        }
        return $client;
    }

    private function loadToken(): ?array
    {
        if (!is_file($this->config['token_path'])) {
            return null;
        }
        $token = json_decode((string)file_get_contents($this->config['token_path']), true);
        return is_array($token) ? $token : null;
    }

    private function saveToken(array $token): void
    {
        $dir = dirname($this->config['token_path']);
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            throw new RuntimeException('Unable to create secure Gmail token directory.');
        }
        if (!isset($token['created'])) {
            $token['created'] = time();
        }
        $tmp = $this->config['token_path'] . '.tmp';
        file_put_contents($tmp, json_encode($token, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $this->config['token_path']);
    }

    private function httpJson(string $method, string $url, ?array $payload = null, bool $form = false, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        $headers = $extraHeaders;
        $body = null;
        if ($payload !== null) {
            if ($form) {
                $body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            } else {
                $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $headers[] = 'Content-Type: application/json';
            }
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Google request failed: ' . $error);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google returned invalid JSON.');
        }
        if ($httpCode >= 400) {
            throw new RuntimeException((string)($decoded['error_description'] ?? $decoded['error']['message'] ?? 'Google request failed.'));
        }
        return $decoded;
    }

    private function decodeParts(array $part): string
    {
        $mime = strtolower((string)($part['mimeType'] ?? ''));
        if (($mime === 'text/plain' || $mime === 'text/html') && !empty($part['body']['data'])) {
            $decoded = base64_decode(strtr((string)$part['body']['data'], '-_', '+/'), true);
            return $decoded === false ? '' : ($mime === 'text/html' ? trim(strip_tags($decoded)) : $decoded);
        }
        $text = '';
        foreach (($part['parts'] ?? []) as $child) {
            $text .= "\n" . $this->decodeParts($child);
        }
        return trim($text);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function sanitizeHeader(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}
