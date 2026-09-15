<?php
function geminiConfig(): array {
    return require __DIR__ . '/../config/gemini.php';
}

/**
 * Sends a chat request to the Gemini API.
 * On production (InfinityFree), routes through a Cloudflare Worker relay
 * because InfinityFree blocks direct outbound cURL connections.
 *
 * @param string $systemPrompt Instructions that shape the assistant's persona/behavior.
 * @param array $history Prior turns as [['role' => 'user'|'model', 'text' => string], ...].
 * @param string $userMessage The newest user message to answer.
 * @return string The model's reply text.
 * @throws RuntimeException on missing config or API failure.
 */
function geminiChat(string $systemPrompt, array $history, string $userMessage): string {
    $config = geminiConfig();
    if (empty($config['api_key'])) {
        throw new RuntimeException('Gemini API key is missing. Add GEMINI_API_KEY to your .env file.');
    }

    $contents = [];
    foreach ($history as $turn) {
        $role = ($turn['role'] ?? '') === 'model' ? 'model' : 'user';
        $text = trim((string)($turn['text'] ?? ''));
        if ($text === '') {
            continue;
        }
        $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
    }
    $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

    $payload = [
        'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
        'contents'           => $contents,
        'generationConfig'   => [
            'temperature'     => 0.4,
            'maxOutputTokens' => 2048,
        ],
    ];

    // Check if a relay is configured (used on hosts that block direct outbound cURL)
    $relayUrl    = envValue('GEMINI_RELAY_URL', '');
    $relaySecret = envValue('GEMINI_RELAY_SECRET', '');

    if (!empty($relayUrl)) {
        // Route through Cloudflare Worker relay
        return geminiChatViaRelay($relayUrl, $relaySecret, $config['model'], $payload);
    }

    // Direct Gemini API call (localhost / hosts that allow outbound cURL)
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($config['model'])
        . ':generateContent?key=' . rawurlencode($config['api_key']);

    return geminiCurlRequest($url, [], $payload);
}

/**
 * Sends the request through the Cloudflare Worker relay.
 */
function geminiChatViaRelay(string $relayUrl, string $secret, string $model, array $payload): string {
    $body = json_encode([
        'model'   => $model,
        'payload' => $payload,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $headers = ['Content-Type: application/json'];
    if ($secret !== '') {
        $headers[] = 'X-Relay-Secret: ' . $secret;
    }

    return geminiCurlRequest($relayUrl, $headers, null, $body);
}

/**
 * Shared cURL execution + response parsing.
 *
 * @param string   $url
 * @param string[] $extraHeaders
 * @param array|null $jsonPayload  If set, json-encoded and sent as POST body.
 * @param string|null $rawBody     If set (and $jsonPayload is null), sent as-is.
 */
function geminiCurlRequest(string $url, array $extraHeaders, ?array $jsonPayload, string $rawBody = ''): string {
    $headers = array_merge(['Content-Type: application/json'], $extraHeaders);
    $body    = $jsonPayload !== null
        ? json_encode($jsonPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        : $rawBody;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 25,
    ]);

    $raw      = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Gemini request failed: ' . $curlError);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Gemini returned an invalid response.');
    }

    if ($httpCode >= 400) {
        $detail = $decoded['error']['message'] ?? 'Gemini request failed.';
        throw new RuntimeException($detail);
    }

    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text) || trim($text) === '') {
        $finishReason = $decoded['candidates'][0]['finishReason'] ?? 'unknown';
        throw new RuntimeException('Gemini did not return an answer (reason: ' . $finishReason . ').');
    }

    return trim($text);
}
