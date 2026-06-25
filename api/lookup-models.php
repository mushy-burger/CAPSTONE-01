<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

requireAdminOrStaff();

function respondJsonModels(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function httpGetJsonModels(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'MotoTrack/1.0 Model Lookup',
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

function extractCcValue(string $text): ?int {
    if (preg_match('/\b([1-9][0-9]{1,3})\s?cc\b/i', $text, $match)) {
        return (int)$match[1];
    }
    if (preg_match('/\b([1-9][0-9]{1,3})(i|r|mx|z1)?\b/i', $text, $match)) {
        $value = (int)$match[1];
        if ($value >= 80 && $value <= 2000) {
            return $value;
        }
    }
    return null;
}

$brand = trim($_GET['brand'] ?? '');

if ($brand === '') {
    respondJsonModels(['ok' => false, 'message' => 'Brand is required.'], 422);
}

$brandId = fetchOne("SELECT id FROM motorcycle_brands WHERE LOWER(name) = LOWER(?) LIMIT 1", [$brand])['id'] ?? null;
$localModels = [];
if ($brandId) {
    $rows = fetchAllRows(
        "SELECT name, cc, cc_source, cc_confidence FROM motorcycle_models WHERE brand_id = ? ORDER BY name",
        [$brandId]
    );
    foreach ($rows as $row) {
        $localModels[] = [
            'name' => $row['name'],
            'cc' => (int)$row['cc'],
            'source' => $row['cc_source'] ?: 'Local MotoTrack catalog',
            'confidence' => $row['cc_confidence'] !== null ? (float)$row['cc_confidence'] : 0.99,
            'from_cache' => true,
        ];
    }
}

if ($localModels) {
    respondJsonModels(['ok' => true, 'models' => $localModels, 'source' => 'local']);
}

$query = trim($brand . ' motorcycle');
$wikiSearchUrl = 'https://en.wikipedia.org/w/api.php?action=opensearch&search=' . rawurlencode($query) . '&limit=10&namespace=0&format=json';
$searchResults = httpGetJsonModels($wikiSearchUrl);
$results = [];

if (is_array($searchResults) && isset($searchResults[1], $searchResults[3]) && is_array($searchResults[1]) && is_array($searchResults[3])) {
    foreach ($searchResults[1] as $index => $title) {
        $titleText = trim((string)$title);
        if ($titleText === '' || stripos($titleText, $brand) === false) {
            continue;
        }

        $pageUrl = $searchResults[3][$index] ?? '';
        $cc = extractCcValue($titleText);
        if (!$cc) {
            $summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $titleText));
            $summary = httpGetJsonModels($summaryUrl);
            $text = trim(($summary['extract'] ?? '') . ' ' . ($summary['description'] ?? ''));
            $cc = extractCcValue($text);
            if ($pageUrl === '' && isset($summary['content_urls']['desktop']['page'])) {
                $pageUrl = $summary['content_urls']['desktop']['page'];
            }
        }

        if ($cc) {
            $results[$titleText] = [
                'name' => $titleText,
                'cc' => $cc,
                'source' => $pageUrl !== '' ? $pageUrl : 'Wikipedia search',
                'confidence' => 0.78,
                'from_cache' => false,
            ];
        }
    }
}

if ($results) {
    respondJsonModels(['ok' => true, 'models' => array_values($results), 'source' => 'online']);
}

respondJsonModels([
    'ok' => false,
    'message' => 'No online model suggestions found for this brand yet.',
], 404);
