<?php
/**
 * Safe checker for the SMS configuration.
 *
 * Command line only — refuses to run over the web so the API key can never be
 * probed through a browser.
 *
 *   php database/sms_test.php                    show config + normalisation checks
 *   php database/sms_test.php 09171234567        send a test message to that number
 *
 * With SEMAPHORE_DRY_RUN=true nothing is actually sent: the message is written
 * to storage/logs/sms.log instead, so the pipeline can be exercised without
 * spending credits.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This tool is command-line only.\n");
}

require_once __DIR__ . '/../includes/SmsProvider.php';

$config = smsConfig();

$credentialKey = $config['provider'] === 'mocean' ? 'MOCEAN_API_TOKEN' : 'SEMAPHORE_API_KEY';

echo "MotoTrack SMS configuration\n";
echo "---------------------------\n";
echo "Provider     : " . $config['provider'] . "\n";
// The credential itself is never printed — only whether one is present.
echo "Credential   : " . ($config['api_key'] !== ''
        ? 'set (' . strlen($config['api_key']) . ' characters)'
        : 'NOT SET — add ' . $credentialKey . ' to .env') . "\n";
echo "Sender name  : " . ($config['sender_name'] !== '' ? $config['sender_name'] : '(default)') . "\n";
echo "Dry run      : " . ($config['dry_run'] ? 'ON — messages are logged, not sent' : 'OFF — messages are really sent') . "\n";
echo "Endpoint     : " . $config['endpoint'] . "\n";
echo "cURL         : " . (function_exists('curl_init') ? 'available' : 'MISSING') . "\n\n";

// Number normalisation, so formatting problems are obvious before sending.
echo "Number normalisation\n";
echo "--------------------\n";
foreach (['09171234567', '+639171234567', '639171234567', '9171234567', '0917 123 4567', '12345', ''] as $sample) {
    $normalised = smsNormalizePhNumber($sample);
    printf("  %-16s -> %s\n", $sample !== '' ? $sample : '(empty)', $normalised !== '' ? $normalised : 'INVALID (SMS would be skipped)');
}
echo "\n";

// Live account check — validates the credential and reports sending readiness.
// Read-only: this never sends a message and costs nothing.
if ($config['provider'] === 'mocean' && $config['api_key'] !== '' && function_exists('curl_init')) {
    echo "Mocean account\n";
    echo "--------------\n";
    $balance = smsAccountBalance();
    if ($balance['ok']) {
        echo "  Token        : VALID\n";
        echo "  Balance      : " . $balance['balance'] . " credit(s)\n";
        echo "\n  Sending      : " . ((float)$balance['balance'] > 0
            ? 'READY'
            : 'BLOCKED — balance is 0, top up to send') . "\n";
        echo "\n  Note: until the first payment, Mocean only delivers to the\n";
        echo "        numbers listed under Test Numbers in your dashboard.\n";
    } else {
        echo "  Token        : REJECTED — " . ($balance['error'] ?? 'unknown error') . "\n";
    }
    echo "\n";
}

if ($config['provider'] === 'semaphore' && $config['api_key'] !== '' && function_exists('curl_init')) {
    echo "Semaphore account\n";
    echo "-----------------\n";

    $fetch = static function (string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$code, (string)$body];
    };

    [$code, $body] = $fetch('https://api.semaphore.co/api/v4/account?apikey=' . urlencode($config['api_key']));
    $account = json_decode($body, true);

    if ($code === 200 && is_array($account) && isset($account['account_name'])) {
        $status  = (string)($account['status'] ?? 'Unknown');
        $credits = (string)($account['credit_balance'] ?? '0');
        echo "  Key          : VALID\n";
        echo "  Account      : " . $account['account_name'] . "\n";
        echo "  Status       : {$status}\n";
        echo "  Credits      : {$credits}\n";

        [, $sendersBody] = $fetch('https://api.semaphore.co/api/v4/account/sendernames?apikey=' . urlencode($config['api_key']));
        $senders = json_decode($sendersBody, true);
        $names = [];
        if (is_array($senders)) {
            foreach ($senders as $s) {
                if (is_array($s) && isset($s['name'])) {
                    $names[] = $s['name'] . ' (' . ($s['status'] ?? '?') . ')';
                }
            }
        }
        echo "  Sender names : " . ($names ? implode(', ', $names) : 'none registered — Semaphore will use its default') . "\n";

        // Explain plainly whether sending can work yet.
        $blockers = [];
        if (strcasecmp($status, 'Active') !== 0) {
            $blockers[] = "account status is '{$status}' (Semaphore must approve it)";
        }
        if ((float)$credits <= 0) {
            $blockers[] = 'credit balance is 0 (top up to send)';
        }
        echo "\n  Sending      : " . ($blockers ? 'BLOCKED — ' . implode('; ', $blockers) : 'READY') . "\n";
    } elseif ($code === 200 && is_array($account)) {
        $reason = '';
        foreach ($account as $field => $messages) {
            if (is_array($messages) && isset($messages[0])) { $reason = $field . ': ' . $messages[0]; break; }
        }
        echo "  Key          : REJECTED" . ($reason !== '' ? ' (' . $reason . ')' : '') . "\n";
    } else {
        echo "  Key check    : HTTP {$code} — " . mb_substr(trim(strip_tags($body)), 0, 160) . "\n";
    }
    echo "\n";
}

$target = $argv[1] ?? '';
if ($target === '') {
    echo "To send a test message:  php database/sms_test.php 09XXXXXXXXX\n";
    exit(0);
}

if (!smsIsValidPhNumber($target)) {
    echo "'{$target}' is not a valid Philippine mobile number. Nothing sent.\n";
    exit(1);
}

if ($config['api_key'] === '' && !$config['dry_run']) {
    echo "No API key configured and dry run is off. Nothing sent.\n";
    echo "Add SEMAPHORE_API_KEY to .env, or set SEMAPHORE_DRY_RUN=true to test safely.\n";
    exit(1);
}

echo "Sending test message to " . smsMaskNumber(smsNormalizePhNumber($target)) . " ...\n";
$result = smsSend($target, 'MotoTrack: test message. If you received this, SMS notifications are working.');

echo "  status     : " . $result['status'] . "\n";
echo "  provider   : " . $result['provider'] . "\n";
echo "  message id : " . ($result['message_id'] ?? '—') . "\n";
echo "  error      : " . ($result['error'] ?? 'none') . "\n";
echo "\n" . ($result['ok'] ? "OK — provider accepted the message.\n" : "FAILED — see the error above.\n");
exit($result['ok'] ? 0 : 1);
