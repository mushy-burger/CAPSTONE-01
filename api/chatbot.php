<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ChatbotService.php';

header('Content-Type: application/json; charset=UTF-8');

function respondChatbot(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondChatbot(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
$message = trim((string)($body['message'] ?? ''));

if ($message === '') {
    respondChatbot(['ok' => false, 'message' => 'Please type a question.'], 422);
}

// Lightweight per-session rate limit: 20 messages per 10-minute window.
$now = time();
$rate = $_SESSION['chatbot_rate'] ?? ['count' => 0, 'window_start' => $now];
if ($now - $rate['window_start'] > 600) {
    $rate = ['count' => 0, 'window_start' => $now];
}
$rate['count']++;
$_SESSION['chatbot_rate'] = $rate;

if ($rate['count'] > 20) {
    respondChatbot(['ok' => false, 'message' => "You've sent a lot of messages. Please wait a bit before asking again."], 429);
}

$history = $_SESSION['chatbot_history'] ?? [];

try {
    $reply = chatbotGetReply($message, $history);
} catch (Throwable $e) {
    respondChatbot(['ok' => false, 'message' => 'Sorry, the assistant is unavailable right now. Please try again later.'], 502);
}

$history[] = ['role' => 'user', 'text' => $message];
$history[] = ['role' => 'model', 'text' => $reply];
// Keep only the last 10 turns (20 entries) to bound session size and token cost.
$_SESSION['chatbot_history'] = array_slice($history, -20);

respondChatbot(['ok' => true, 'reply' => $reply]);
