<?php
/**
 * SMS delivery.
 *
 * This is the ONLY file that knows how to talk to an SMS provider. Booking and
 * technician code calls NotificationService, never this directly, so changing
 * provider is a config change plus one function here.
 *
 * Two providers are supported, chosen with SMS_PROVIDER in .env:
 *
 *   mocean    (default) https://moceanapi.com/docs
 *             POST https://rest.moceanapi.com/rest/2/sms
 *             Bearer token; fields mocean-from / mocean-to / mocean-text
 *             Replies {"messages":[{"status":0,"msgid":"...","receiver":"..."}]}
 *             where status 0 means accepted.
 *
 *   semaphore https://semaphore.co/docs
 *             POST https://api.semaphore.co/api/v4/messages
 *             form fields apikey / number / message / sendername
 *
 * Credentials come from the existing .env mechanism via envValue(); nothing is
 * hard-coded, and the credential is never returned, logged or echoed.
 */
require_once __DIR__ . '/functions.php';

const SMS_ENDPOINT_SEMAPHORE = 'https://api.semaphore.co/api/v4/messages';
const SMS_ENDPOINT_MOCEAN    = 'https://rest.moceanapi.com/rest/2/sms';
const SMS_ENDPOINT_MOCEAN_BALANCE = 'https://rest.moceanapi.com/rest/2/account/balance';

// Kept so older references still resolve.
const SMS_ENDPOINT = SMS_ENDPOINT_SEMAPHORE;

/**
 * Provider settings, read from .env.
 *
 * `dry_run` lets the whole workflow be exercised without spending credits:
 * set SMS_DRY_RUN=true and messages are logged instead of sent.
 */
function smsConfig(): array {
    $provider = strtolower(trim((string)envValue('SMS_PROVIDER', 'mocean')));
    if (!in_array($provider, ['mocean', 'semaphore'], true)) {
        $provider = 'mocean';
    }

    // Sender name falls back across both providers' keys so either naming works.
    $sender = (string)envValue('SMS_SENDER_NAME', '');
    if ($sender === '') {
        $sender = (string)envValue('MOCEAN_SENDER_NAME', '');
    }
    if ($sender === '') {
        $sender = (string)envValue('SEMAPHORE_SENDER_NAME', 'MotoTrack');
    }

    // DRY RUN: SMS_DRY_RUN is preferred; the older SEMAPHORE_DRY_RUN still works.
    $dryRaw = envValue('SMS_DRY_RUN', null);
    if ($dryRaw === null) {
        $dryRaw = envValue('SEMAPHORE_DRY_RUN', 'false');
    }

    $credential = $provider === 'mocean'
        ? (string)envValue('MOCEAN_API_TOKEN', '')
        : (string)envValue('SEMAPHORE_API_KEY', '');

    return [
        'provider'    => $provider,
        'api_key'     => $credential,
        'sender_name' => $sender !== '' ? $sender : 'MotoTrack',
        'dry_run'     => filter_var($dryRaw, FILTER_VALIDATE_BOOLEAN),
        'endpoint'    => $provider === 'mocean' ? SMS_ENDPOINT_MOCEAN : SMS_ENDPOINT_SEMAPHORE,
    ];
}

function smsIsConfigured(): bool {
    $config = smsConfig();
    return $config['api_key'] !== '' || $config['dry_run'];
}

/**
 * Normalise a Philippine mobile number to the digits-only international form
 * Semaphore accepts (639XXXXXXXXX).
 *
 * Accepts the shapes that actually occur in the users table and in typed
 * input: 09171234567, +639171234567, 639171234567, and spaced/dashed variants.
 * Returns '' when the value is not a valid PH mobile number, so callers can
 * skip SMS rather than sending something that will bounce.
 */
function smsNormalizePhNumber(string $raw): string {
    // Strip everything except digits (drops +, spaces, dashes, parentheses).
    $digits = preg_replace('/\D+/', '', $raw) ?? '';

    if ($digits === '') {
        return '';
    }

    // 09171234567 -> 639171234567
    if (strlen($digits) === 11 && str_starts_with($digits, '09')) {
        return '63' . substr($digits, 1);
    }

    // 639171234567 (already international)
    if (strlen($digits) === 12 && str_starts_with($digits, '639')) {
        return $digits;
    }

    // 9171234567 (leading zero omitted)
    if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
        return '63' . $digits;
    }

    // Anything else is not a PH mobile number we can send to.
    return '';
}

/** True when the value is a usable PH mobile number. */
function smsIsValidPhNumber(string $raw): bool {
    return smsNormalizePhNumber($raw) !== '';
}

/**
 * Send one SMS.
 *
 * Never throws: returns a result array so a provider outage can be logged
 * without disturbing the booking or job transaction that triggered it.
 *
 * @return array{ok:bool,status:string,message_id:?string,error:?string,provider:string}
 */
function smsSend(string $rawNumber, string $message): array {
    $config = smsConfig();

    $result = [
        'ok'         => false,
        'status'     => 'failed',
        'message_id' => null,
        'error'      => null,
        'provider'   => $config['provider'],
    ];

    $number = smsNormalizePhNumber($rawNumber);
    if ($number === '') {
        $result['status'] = 'skipped';
        $result['error']  = 'No valid Philippine mobile number.';
        return $result;
    }

    $message = trim($message);
    if ($message === '') {
        $result['error'] = 'Empty message body.';
        return $result;
    }

    // Development mode: prove the whole pipeline without spending credits.
    if ($config['dry_run']) {
        $result['ok']         = true;
        $result['status']     = 'sent';
        $result['provider']   = $config['provider'] . '-dry-run';
        $result['message_id'] = 'dryrun-' . substr(hash('sha256', $number . $message . microtime()), 0, 16);
        smsLogLine('DRY-RUN to ' . smsMaskNumber($number) . ': ' . str_replace("\n", ' | ', $message));
        return $result;
    }

    if ($config['api_key'] === '') {
        $result['error'] = $config['provider'] === 'mocean'
            ? 'SMS is not configured (MOCEAN_API_TOKEN missing).'
            : 'SMS is not configured (SEMAPHORE_API_KEY missing).';
        return $result;
    }

    if (!function_exists('curl_init')) {
        $result['error'] = 'cURL is unavailable on this server.';
        return $result;
    }

    if ($config['provider'] === 'mocean') {
        return smsSendViaMocean($number, $message, $config, $result);
    }

    $ch = curl_init(SMS_ENDPOINT_SEMAPHORE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        // Semaphore expects form-encoded fields, not JSON.
        CURLOPT_POSTFIELDS     => http_build_query([
            'apikey'     => $config['api_key'],
            'number'     => $number,
            'message'    => $message,
            'sendername' => $config['sender_name'],
        ]),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $raw       = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Network-level failure (DNS, timeout, TLS).
    if ($raw === false) {
        $result['error'] = 'SMS provider unreachable: ' . smsSafeError($curlError);
        smsLogLine('SEND FAILED (network) to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    if ($httpCode === 429) {
        $result['error'] = 'SMS provider rate limit reached. Try again shortly.';
        smsLogLine('SEND FAILED (rate limit) to ' . smsMaskNumber($number));
        return $result;
    }

    $decoded = json_decode((string)$raw, true);

    if ($httpCode >= 400) {
        // Semaphore reports validation problems as {"field":["reason"]}, but
        // account-level refusals (unapproved account, no credits) come back as
        // HTTP 403 with a PLAIN TEXT body — so fall back to the raw text.
        $detail = 'HTTP ' . $httpCode;
        if (is_array($decoded)) {
            $first = reset($decoded);
            if (is_array($first) && isset($first[0]) && is_string($first[0])) {
                $detail = $first[0];
            } elseif (is_string($first)) {
                $detail = $first;
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $detail = trim(strip_tags($raw));
        }
        $result['error'] = 'SMS provider rejected the message: ' . smsSafeError($detail);
        smsLogLine('SEND FAILED (http ' . $httpCode . ') to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    if (!is_array($decoded)) {
        $result['error'] = 'SMS provider returned an unreadable response.';
        smsLogLine('SEND FAILED (malformed response) to ' . smsMaskNumber($number));
        return $result;
    }

    // Semaphore answers validation problems with HTTP 200 and a body shaped
    // {"field":["reason"]} (a bad API key lands here, not in a 4xx), so the
    // body has to be inspected even on a 200.
    if (!isset($decoded[0])) {
        $reason = '';
        foreach ($decoded as $field => $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                $reason = (is_string($field) ? $field . ': ' : '') . $messages[0];
                break;
            }
            if (is_string($messages)) {
                $reason = (is_string($field) ? $field . ': ' : '') . $messages;
                break;
            }
        }
        $result['error'] = 'SMS provider rejected the message' . ($reason !== '' ? ' (' . smsSafeError($reason) . ')' : '.');
        smsLogLine('SEND FAILED (rejected) to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    // A successful send returns a list of message objects.
    $first = $decoded[0];
    if (!is_array($first) || !isset($first['message_id'])) {
        $result['error'] = 'SMS provider returned an unexpected response.';
        smsLogLine('SEND FAILED (unexpected shape) to ' . smsMaskNumber($number));
        return $result;
    }

    $providerStatus = strtolower((string)($first['status'] ?? ''));
    // Queued/Pending/Sent all mean Semaphore accepted the message.
    $accepted = in_array($providerStatus, ['queued', 'pending', 'sent'], true);

    $result['ok']         = $accepted;
    $result['status']     = $accepted ? 'sent' : 'failed';
    $result['message_id'] = (string)$first['message_id'];
    if (!$accepted) {
        $result['error'] = 'SMS provider status: ' . smsSafeError($providerStatus ?: 'unknown');
    }

    smsLogLine(
        ($accepted ? 'SENT' : 'FAILED') . ' to ' . smsMaskNumber($number)
        . ' id=' . $result['message_id'] . ' status=' . ($providerStatus ?: 'unknown')
    );

    return $result;
}

/**
 * Send via MoceanAPI.
 *
 * POST https://rest.moceanapi.com/rest/2/sms with a Bearer token.
 * A reply looks like {"messages":[{"status":0,"msgid":"...","receiver":"..."}]}
 * where status 0 means accepted; anything else carries err_msg.
 *
 * Same contract as smsSend(): never throws, always returns the result array.
 */
function smsSendViaMocean(string $number, string $message, array $config, array $result): array {
    $ch = curl_init(SMS_ENDPOINT_MOCEAN);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'mocean-from' => $config['sender_name'],
            'mocean-to'   => $number,
            'mocean-text' => $message,
        ]),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        // The token travels in the header, never in the body or query string.
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $config['api_key'],
            'Accept: application/json',
        ],
    ]);

    $raw       = curl_exec($ch);
    $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        $result['error'] = 'SMS provider unreachable: ' . smsSafeError($curlError);
        smsLogLine('SEND FAILED (network) to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    if ($httpCode === 429) {
        $result['error'] = 'SMS provider rate limit reached. Try again shortly.';
        smsLogLine('SEND FAILED (rate limit) to ' . smsMaskNumber($number));
        return $result;
    }

    $decoded = json_decode((string)$raw, true);

    if (!is_array($decoded)) {
        // Mocean answers auth/account problems with plain text on some errors.
        $detail = trim(strip_tags((string)$raw));
        $result['error'] = 'SMS provider rejected the message'
            . ($detail !== '' ? ': ' . smsSafeError($detail) : ' (HTTP ' . $httpCode . ').');
        smsLogLine('SEND FAILED (http ' . $httpCode . ') to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    // Top-level error shape: {"status":<non-zero>,"err_msg":"..."}
    if (isset($decoded['status']) && (int)$decoded['status'] !== 0 && !isset($decoded['messages'])) {
        $detail = (string)($decoded['err_msg'] ?? ('status ' . $decoded['status']));
        $result['error'] = 'SMS provider rejected the message: ' . smsSafeError($detail);
        smsLogLine('SEND FAILED (api) to ' . smsMaskNumber($number) . ': ' . $result['error']);
        return $result;
    }

    $first = $decoded['messages'][0] ?? null;
    if (!is_array($first)) {
        $result['error'] = 'SMS provider returned an unexpected response.';
        smsLogLine('SEND FAILED (unexpected shape) to ' . smsMaskNumber($number));
        return $result;
    }

    // status 0 = accepted for delivery.
    $accepted = (int)($first['status'] ?? -1) === 0;
    $result['ok']         = $accepted;
    $result['status']     = $accepted ? 'sent' : 'failed';
    $result['message_id'] = isset($first['msgid']) ? (string)$first['msgid'] : null;

    if (!$accepted) {
        $detail = (string)($first['err_msg'] ?? ('status ' . ($first['status'] ?? '?')));
        $result['error'] = 'SMS provider rejected the message: ' . smsSafeError($detail);
    }

    smsLogLine(
        ($accepted ? 'SENT' : 'FAILED') . ' to ' . smsMaskNumber($number)
        . ' id=' . ($result['message_id'] ?? '-')
        . ' status=' . ($first['status'] ?? '?')
    );

    return $result;
}

/**
 * Read-only account balance check for the configured provider.
 * Sends no message and costs nothing.
 *
 * @return array{ok:bool,balance:?string,error:?string}
 */
function smsAccountBalance(): array {
    $config = smsConfig();
    $out = ['ok' => false, 'balance' => null, 'error' => null];

    if ($config['api_key'] === '') {
        $out['error'] = 'No credential configured.';
        return $out;
    }
    if ($config['provider'] !== 'mocean') {
        $out['error'] = 'Balance lookup is only implemented for Mocean.';
        return $out;
    }

    $ch = curl_init(SMS_ENDPOINT_MOCEAN_BALANCE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $config['api_key'], 'Accept: application/json'],
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        $out['error'] = 'Provider unreachable.';
        return $out;
    }

    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded) && isset($decoded['value']) && (int)($decoded['status'] ?? -1) === 0) {
        $out['ok'] = true;
        $out['balance'] = (string)$decoded['value'];
        return $out;
    }

    $out['error'] = is_array($decoded) && isset($decoded['err_msg'])
        ? smsSafeError((string)$decoded['err_msg'])
        : 'HTTP ' . $code . ' — ' . smsSafeError(trim(strip_tags((string)$raw)));
    return $out;
}

/** Mask a number for logs: 639171234567 -> 6391****4567. */
function smsMaskNumber(string $number): string {
    $len = strlen($number);
    if ($len < 8) {
        return str_repeat('*', $len);
    }
    return substr($number, 0, 4) . str_repeat('*', $len - 8) . substr($number, -4);
}

/**
 * Strip anything secret-looking out of provider text before it is stored or
 * logged, so an API key echoed back by an error can never leak.
 */
function smsSafeError(string $text): string {
    $config = smsConfig();
    if ($config['api_key'] !== '') {
        $text = str_ireplace($config['api_key'], '[redacted]', $text);
    }
    $text = preg_replace('/\b[a-f0-9]{24,}\b/i', '[redacted]', $text) ?? $text;
    return mb_substr(trim($text), 0, 200);
}

/** Append a line to the SMS log, never including credentials. */
function smsLogLine(string $line): void {
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }
    // Database time is authoritative in this app (see NotificationService).
    $stamp = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    @file_put_contents($dir . '/sms.log', '[' . $stamp . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
