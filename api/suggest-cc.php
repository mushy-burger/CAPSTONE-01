<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

requireAdminOrStaff();

function respondJson(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function extractCcFromText(string $text): ?int {
    if (preg_match('/\b([1-9][0-9]{1,3})\s?cc\b/i', $text, $match)) {
        return (int)$match[1];
    }
    if (preg_match('/\b([1-9][0-9]{1,3})cc\b/i', $text, $match)) {
        return (int)$match[1];
    }
    return null;
}

function httpGetJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'MotoTrack/1.0 CC Lookup',
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300 || !$response) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

$brand = trim($_GET['brand'] ?? '');
$model = trim($_GET['model'] ?? '');

if ($brand === '' || $model === '') {
    respondJson(['ok' => false, 'message' => 'Brand and model are required.'], 422);
}

$local = fetchOne(
    "SELECT cc, cc_source, cc_confidence
     FROM motorcycle_models
     WHERE LOWER(name) = LOWER(?) AND brand_id IN (
         SELECT id FROM motorcycle_brands WHERE LOWER(name) = LOWER(?)
     )
     ORDER BY last_verified_at DESC, id DESC
     LIMIT 1",
    [$model, $brand]
);

if ($local && (int)$local['cc'] > 0) {
    respondJson([
        'ok' => true,
        'cc' => (int)$local['cc'],
        'source' => $local['cc_source'] ?: 'Local MotoTrack catalog',
        'confidence' => $local['cc_confidence'] !== null ? (float)$local['cc_confidence'] : 0.99,
        'from_cache' => true,
    ]);
}

$query = trim($brand . ' ' . $model . ' motorcycle');
$wikiSearchUrl = 'https://en.wikipedia.org/w/api.php?action=opensearch&search=' . rawurlencode($query) . '&limit=5&namespace=0&format=json';
$searchResults = httpGetJson($wikiSearchUrl);

if (is_array($searchResults) && isset($searchResults[3]) && is_array($searchResults[3])) {
    foreach ($searchResults[3] as $pageUrl) {
        $parts = parse_url($pageUrl);
        $title = isset($parts['path']) ? basename($parts['path']) : '';
        if ($title === '') {
            continue;
        }

        $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($title);
        $summary = httpGetJson($summaryUrl);
        $text = trim(($summary['extract'] ?? '') . ' ' . ($summary['description'] ?? ''));
        $cc = extractCcFromText($text);
        if ($cc) {
            respondJson([
                'ok' => true,
                'cc' => $cc,
                'source' => $summary['content_urls']['desktop']['page'] ?? $pageUrl,
                'confidence' => 0.82,
                'from_cache' => false,
            ]);
        }
    }
}

$patternCc = extractCcFromText($model);
if ($patternCc) {
    respondJson([
        'ok' => true,
        'cc' => $patternCc,
        'source' => 'Model name pattern',
        'confidence' => 0.65,
        'from_cache' => false,
    ]);
}

respondJson([
    'ok' => false,
    'message' => 'No cc suggestion found. Enter it manually.',
], 404);
