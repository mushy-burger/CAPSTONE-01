<?php
/**
 * Returns shop context + keyword-matched data for the chatbot.
 * PHP only touches the local DB — no outbound connections needed.
 * The actual Gemini API call is made client-side via the Cloudflare Worker.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/ChatbotService.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body    = json_decode(file_get_contents('php://input') ?: '', true);
$message = trim((string)($body['message'] ?? ''));

$keywords = $message ? chatbotExtractKeywords($message) : [];
$products = $keywords ? chatbotFindProducts($keywords)         : [];
$models   = $keywords ? chatbotFindMotorcycleModels($keywords) : [];
$services = $keywords ? chatbotFindServices($keywords)         : [];

$matchLines = [];
foreach ($products as $p) {
    $stock = (int)$p['stock'] > 0 ? (int)$p['stock'] . ' in stock' : 'currently out of stock';
    $matchLines[] = "Product: {$p['name']} ({$p['brand']}, {$p['category_name']}) - " . formatPrice((float)$p['price']) . ", {$stock}.";
}
foreach ($models as $m) {
    $matchLines[] = "Motorcycle in our catalog: {$m['brand_name']} {$m['model_name']}, {$m['cc']}cc, type {$m['type_name']}.";
}
foreach ($services as $s) {
    $matchLines[] = "Service: {$s['name']} - labor fee " . formatPrice((float)$s['labor_fee']) . ".";
}

echo json_encode([
    'ok'           => true,
    'systemPrompt' => chatbotBuildSystemPrompt(),
    'matchLines'   => $matchLines,
]);
