<?php
function geminiConfig(): array {
    return require __DIR__ . '/../config/gemini.php';
}

/**
 * Sends a chat request to the Gemini API.
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
        throw new RuntimeException('Gemini API key is missing. Add GEMINI_API_KEY to your local .env file.');
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
        'contents' => $contents,
        'generationConfig' => [
            'temperature' => 0.4,
            'maxOutputTokens' => 2048,
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode($config['model'])
        . ':generateContent?key=' . rawurlencode($config['api_key']);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 25,
    ]);

    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
