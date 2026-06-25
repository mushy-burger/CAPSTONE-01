<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

class MotorcycleApiService
{
    public function searchMotorcycle(string $type, string $brand, string $model): array
    {
        $type = cleanMotorcycleLabel($type);
        $brand = cleanMotorcycleLabel($brand);
        $model = cleanMotorcycleLabel($model);

        if ($type === '' || $brand === '' || $model === '') {
            return [
                'success' => false,
                'message' => 'Type, brand, and model are required.',
                'motorcycle' => [
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                    'cc' => null,
                ],
            ];
        }

        $cached = $this->searchLocalCatalog($type, $brand, $model);
        if ($cached) {
            return [
                'success' => true,
                'motorcycle' => $cached,
                'candidates' => [$cached],
                'cc_range' => $this->ccRangeFromCandidates([$cached]),
                'meta' => [
                    'source' => $cached['source'] ?? 'Local MotoTrack catalog',
                    'confidence' => $cached['confidence'] ?? 0.99,
                    'from_cache' => true,
                ],
            ];
        }

        $apiResult = $this->searchExternalApi($type, $brand, $model);
        if ($apiResult) {
            return [
                'success' => true,
                'motorcycle' => $apiResult['motorcycle'],
                'candidates' => $apiResult['candidates'] ?? [$apiResult['motorcycle']],
                'cc_range' => $this->ccRangeFromCandidates($apiResult['candidates'] ?? [$apiResult['motorcycle']]),
                'meta' => $apiResult['meta'],
            ];
        }

        $patternCc = $this->extractCcValue($model);
        if ($patternCc !== null) {
            return [
                'success' => true,
                'motorcycle' => [
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                    'cc' => $patternCc . 'cc',
                ],
                'candidates' => [[
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                    'cc' => $patternCc . 'cc',
                ]],
                'cc_range' => [$patternCc, $patternCc],
                'meta' => [
                    'source' => 'Model name pattern',
                    'confidence' => 0.65,
                    'from_cache' => false,
                ],
            ];
        }

        return [
            'success' => false,
            'message' => 'No motorcycle specification found.',
            'motorcycle' => [
                'type' => $type,
                'brand' => $brand,
                'model' => $model,
                'cc' => null,
            ],
            'candidates' => [],
            'cc_range' => null,
            'meta' => [
                'source' => null,
                'confidence' => null,
                'from_cache' => false,
            ],
        ];
    }

    private function searchLocalCatalog(string $type, string $brand, string $model): ?array
    {
        $row = fetchOne(
            "SELECT
                mt.name AS type_name,
                mb.name AS brand_name,
                mm.name AS model_name,
                mm.cc,
                mm.cc_source,
                mm.cc_confidence
             FROM motorcycle_models mm
             INNER JOIN motorcycle_types mt ON mt.id = mm.type_id
             INNER JOIN motorcycle_brands mb ON mb.id = mm.brand_id
             WHERE LOWER(mt.name) = LOWER(?)
               AND LOWER(mb.name) = LOWER(?)
               AND LOWER(mm.name) = LOWER(?)
             ORDER BY mm.last_verified_at DESC, mm.id DESC
             LIMIT 1",
            [$type, $brand, $model]
        );

        if (!$row || (int)$row['cc'] <= 0) {
            return null;
        }

        return [
            'type' => $row['type_name'],
            'brand' => $row['brand_name'],
            'model' => $row['model_name'],
            'cc' => (int)$row['cc'] . 'cc',
            'source' => $row['cc_source'] ?: 'Local MotoTrack catalog',
            'confidence' => $row['cc_confidence'] !== null ? (float)$row['cc_confidence'] : 0.99,
            'candidates' => [[
                'type' => $row['type_name'],
                'brand' => $row['brand_name'],
                'model' => $row['model_name'],
                'cc' => (int)$row['cc'] . 'cc',
            ]],
        ];
    }

    private function searchExternalApi(string $type, string $brand, string $model): ?array
    {
        $apiNinjas = $this->searchApiNinjas($type, $brand, $model);
        if ($apiNinjas) {
            return $apiNinjas;
        }

        $configured = $this->searchConfiguredApi($type, $brand, $model);
        if ($configured) {
            return $configured;
        }

        $query = trim($brand . ' ' . $model . ' motorcycle specification engine cc');
        $searchResults = $this->searchWikipedia($query);

        foreach ($searchResults as $result) {
            $textBlob = trim(($result['title'] ?? '') . ' ' . ($result['description'] ?? '') . ' ' . ($result['extract'] ?? ''));
            $cc = $this->extractCcValue($textBlob);
            if ($cc === null) {
                continue;
            }

            return [
                'motorcycle' => [
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                    'cc' => $cc . 'cc',
                ],
                'meta' => [
                    'source' => $result['url'] ?? 'Wikipedia',
                    'confidence' => 0.82,
                    'from_cache' => false,
                ],
            ];
        }

        return null;
    }

    private function searchApiNinjas(string $type, string $brand, string $model): ?array
    {
        $apiKey = envValue('MOTORCYCLE_API_KEY');
        if ($apiKey === null) {
            return null;
        }

        $endpoint = envValue('MOTORCYCLE_API_ENDPOINT', 'https://api.api-ninjas.com/v1/motorcycles');
        $query = http_build_query([
            'make' => $brand,
            'model' => $model,
        ]);
        $response = $this->httpGetJson(
            rtrim($endpoint, '?&') . '?' . $query,
            [
                'Accept: application/json',
                'X-Api-Key: ' . $apiKey,
            ]
        );

        if (!is_array($response) || !$response) {
            return null;
        }

        $candidates = $this->buildApiNinjasCandidates($response, $type, $brand, $model);
        if (!$candidates) {
            return null;
        }

        $bestCandidate = $this->pickBestApiNinjasMatch($response, $type, $brand, $model);
        if (!$bestCandidate) {
            $bestCandidate = [
                'make' => $candidates[0]['brand'] ?? $brand,
                'model' => $candidates[0]['model'] ?? $model,
            ];
        }

        $ccRange = $this->ccRangeFromCandidates($candidates);
        $cc = $ccRange ? (int)$ccRange[0] : null;
        if ($cc === null) {
            return null;
        }

        return [
            'motorcycle' => [
                'type' => $type,
                'brand' => cleanMotorcycleLabel((string)($bestCandidate['make'] ?? $brand)),
                'model' => cleanMotorcycleLabel((string)($bestCandidate['model'] ?? $model)),
                'cc' => $cc . 'cc',
            ],
            'candidates' => $candidates,
            'cc_range' => $ccRange,
            'meta' => [
                'source' => 'API Ninjas Motorcycles API',
                'confidence' => 0.94,
                'from_cache' => false,
            ],
        ];
    }

    private function searchConfiguredApi(string $type, string $brand, string $model): ?array
    {
        $endpoint = envValue('MOTORCYCLE_API_ENDPOINT');
        if ($endpoint === null) {
            return null;
        }

        $query = http_build_query([
            'type' => $type,
            'brand' => $brand,
            'model' => $model,
        ]);
        $url = rtrim($endpoint, '?&') . (str_contains($endpoint, '?') ? '&' : '?') . $query;
        $headers = ['Accept: application/json'];

        $apiKey = envValue('MOTORCYCLE_API_KEY');
        if ($apiKey !== null) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $response = $this->httpGetJson($url, $headers);
        if (!is_array($response)) {
            return null;
        }

        $ccValue = (string)($response['engine_cc'] ?? $response['cc'] ?? '');
        $cc = $this->extractCcValue($ccValue);
        if ($cc === null) {
            return null;
        }

        return [
            'motorcycle' => [
                'type' => $type,
                'brand' => cleanMotorcycleLabel((string)($response['brand'] ?? $brand)),
                'model' => cleanMotorcycleLabel((string)($response['model'] ?? $model)),
                'cc' => $cc . 'cc',
            ],
            'candidates' => [[
                'type' => $type,
                'brand' => cleanMotorcycleLabel((string)($response['brand'] ?? $brand)),
                'model' => cleanMotorcycleLabel((string)($response['model'] ?? $model)),
                'cc' => $cc . 'cc',
            ]],
            'cc_range' => [$cc, $cc],
            'meta' => [
                'source' => $endpoint,
                'confidence' => 0.9,
                'from_cache' => false,
            ],
        ];
    }

    private function pickBestApiNinjasMatch(array $items, string $type, string $brand, string $model): ?array
    {
        $targetType = normalizeMotorcycleText($type);
        $targetBrand = normalizeMotorcycleText($brand);
        $targetModel = normalizeMotorcycleText($model);
        $best = null;
        $bestScore = -1;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $make = normalizeMotorcycleText((string)($item['make'] ?? ''));
            $apiModel = normalizeMotorcycleText((string)($item['model'] ?? ''));
            $apiType = normalizeMotorcycleText((string)($item['type'] ?? ''));
            $score = 0;

            if ($make === $targetBrand) {
                $score += 4;
            } elseif ($make !== '' && str_contains($make, $targetBrand)) {
                $score += 2;
            }

            if ($apiModel === $targetModel) {
                $score += 6;
            } elseif ($apiModel !== '' && (str_contains($apiModel, $targetModel) || str_contains($targetModel, $apiModel))) {
                $score += 3;
            }

            if ($apiType === $targetType) {
                $score += 2;
            }

            if (!empty($item['displacement'])) {
                $score += 1;
            }

            if ($score > $bestScore) {
                $best = $item;
                $bestScore = $score;
            }
        }

        return $bestScore >= 5 ? $best : null;
    }

    private function buildApiNinjasCandidates(array $items, string $type, string $brand, string $model): array
    {
        $targetType = normalizeMotorcycleText($type);
        $targetBrand = normalizeMotorcycleText($brand);
        $targetModel = normalizeMotorcycleText($model);
        $candidates = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $make = normalizeMotorcycleText((string)($item['make'] ?? ''));
            $apiModel = normalizeMotorcycleText((string)($item['model'] ?? ''));
            $apiType = normalizeMotorcycleText((string)($item['type'] ?? ''));

            $score = 0;
            if ($make === $targetBrand) {
                $score += 4;
            } elseif ($make !== '' && str_contains($make, $targetBrand)) {
                $score += 2;
            }
            if ($apiModel === $targetModel) {
                $score += 6;
            } elseif ($apiModel !== '' && (str_contains($apiModel, $targetModel) || str_contains($targetModel, $apiModel))) {
                $score += 3;
            }
            if ($apiType === $targetType) {
                $score += 2;
            }

            $cc = $this->extractCcValue((string)($item['displacement'] ?? ''));
            if ($cc === null || $score < 2) {
                continue;
            }

            $candidates[] = [
                'type' => $type,
                'brand' => cleanMotorcycleLabel((string)($item['make'] ?? $brand)),
                'model' => cleanMotorcycleLabel((string)($item['model'] ?? '')),
                'cc' => $cc . 'cc',
                'year' => (string)($item['year'] ?? ''),
                '_score' => $score,
            ];
        }

        usort($candidates, function (array $left, array $right): int {
            $scoreCompare = ($right['_score'] ?? 0) <=> ($left['_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return ($left['cc'] ?? 0) <=> ($right['cc'] ?? 0);
        });

        foreach ($candidates as &$candidate) {
            unset($candidate['_score']);
        }
        unset($candidate);

        return array_slice($candidates, 0, 12);
    }

    private function ccRangeFromCandidates(array $candidates): ?array
    {
        $values = [];
        foreach ($candidates as $candidate) {
            $cc = $this->extractCcValue((string)($candidate['cc'] ?? ''));
            if ($cc !== null) {
                $values[] = $cc;
            }
        }
        if (!$values) {
            return null;
        }
        sort($values);
        $values = array_values(array_unique($values));
        sort($values);
        return [$values[0], $values[count($values) - 1]];
    }

    private function searchWikipedia(string $query): array
    {
        $searchUrl = 'https://en.wikipedia.org/w/api.php?action=opensearch&search=' . rawurlencode($query) . '&limit=6&namespace=0&format=json';
        $searchData = $this->httpGetJson($searchUrl);
        if (!is_array($searchData) || !isset($searchData[1], $searchData[2], $searchData[3])) {
            return [];
        }

        $results = [];
        foreach ($searchData[1] as $index => $title) {
            $title = cleanMotorcycleLabel((string)$title);
            if ($title === '') {
                continue;
            }

            $url = (string)($searchData[3][$index] ?? '');
            $description = (string)($searchData[2][$index] ?? '');
            $extract = '';

            if ($url !== '') {
                $parts = parse_url($url);
                $pageTitle = isset($parts['path']) ? basename((string)$parts['path']) : '';
                if ($pageTitle !== '') {
                    $summary = $this->httpGetJson('https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($pageTitle));
                    if (is_array($summary)) {
                        $extract = trim((string)($summary['extract'] ?? ''));
                        if ($url === '' && isset($summary['content_urls']['desktop']['page'])) {
                            $url = (string)$summary['content_urls']['desktop']['page'];
                        }
                    }
                }
            }

            $results[] = [
                'title' => $title,
                'description' => cleanMotorcycleLabel($description),
                'extract' => $extract,
                'url' => $url,
            ];
        }

        return $results;
    }

    private function httpGetJson(string $url, array $headers = ['Accept: application/json']): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'MotoTrack/1.0 Motorcycle Search',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
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

    private function extractCcValue(string $text): ?int
    {
        if (preg_match('/\b([1-9][0-9]{1,3})\s?cc\b/i', $text, $match)) {
            return (int)$match[1];
        }

        if (preg_match('/\b([1-9][0-9]{1,3})cc\b/i', $text, $match)) {
            return (int)$match[1];
        }

        if (preg_match('/\b([1-9][0-9]{1,3}(?:\.[0-9]+)?)\s?ccm\b/i', $text, $match)) {
            return (int)round((float)$match[1]);
        }

        return null;
    }
}
