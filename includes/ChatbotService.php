<?php
require_once __DIR__ . '/gemini.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const CHATBOT_STOPWORDS = [
    'the', 'and', 'for', 'you', 'your', 'have', 'has', 'with', 'that', 'this',
    'what', 'where', 'when', 'how', 'does', 'can', 'could', 'would', 'should',
    'are', 'is', 'do', 'my', 'me', 'a', 'an', 'to', 'of', 'in', 'on', 'it',
    'about', 'please', 'need', 'want', 'like', 'shop', 'motortrack', 'mototrack',
];

function chatbotExtractKeywords(string $message): array {
    $words = preg_split('/[^a-z0-9]+/i', strtolower($message)) ?: [];
    $keywords = [];
    foreach ($words as $word) {
        if (strlen($word) < 3 || in_array($word, CHATBOT_STOPWORDS, true)) {
            continue;
        }
        $keywords[$word] = true;
    }
    return array_slice(array_keys($keywords), 0, 6);
}

function chatbotFindProducts(array $keywords, int $limit = 5): array {
    if (!$keywords) {
        return [];
    }

    $conditions = [];
    $params = [];
    foreach ($keywords as $keyword) {
        $conditions[] = '(p.name LIKE ? OR p.brand LIKE ? OR c.name LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like);
    }
    $params[] = $limit;

    return fetchAllRows(
        "SELECT p.name, p.brand, p.price, p.stock, c.name AS category_name
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.status != 'out_of_stock' AND (" . implode(' OR ', $conditions) . ")
         ORDER BY p.featured DESC, p.name
         LIMIT ?",
        $params
    );
}

function chatbotFindMotorcycleModels(array $keywords, int $limit = 5): array {
    if (!$keywords) {
        return [];
    }

    $conditions = [];
    $params = [];
    foreach ($keywords as $keyword) {
        $conditions[] = '(mb.name LIKE ? OR mm.name LIKE ? OR mt.name LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like, $like);
    }
    $params[] = $limit;

    return fetchAllRows(
        "SELECT mm.name AS model_name, mm.cc, mb.name AS brand_name, mt.name AS type_name
         FROM motorcycle_models mm
         JOIN motorcycle_brands mb ON mb.id = mm.brand_id
         JOIN motorcycle_types mt ON mt.id = mm.type_id
         WHERE mm.is_active = 1 AND (" . implode(' OR ', $conditions) . ")
         ORDER BY mm.name
         LIMIT ?",
        $params
    );
}

function chatbotFindServices(array $keywords, int $limit = 5): array {
    if (!$keywords) {
        return [];
    }

    $conditions = [];
    $params = [];
    foreach ($keywords as $keyword) {
        $conditions[] = '(name LIKE ? OR description LIKE ?)';
        $like = '%' . $keyword . '%';
        array_push($params, $like, $like);
    }
    $params[] = $limit;

    return fetchAllRows(
        "SELECT name, description, labor_fee FROM service_types
         WHERE " . implode(' OR ', $conditions) . "
         ORDER BY name
         LIMIT ?",
        $params
    );
}

function chatbotBuildShopContext(): string {
    $categories = fetchAllRows("SELECT name FROM categories ORDER BY name LIMIT 20");
    $services = fetchAllRows("SELECT name, labor_fee FROM service_types ORDER BY name LIMIT 20");
    $brands = fetchAllRows("SELECT name FROM motorcycle_brands WHERE is_active = 1 ORDER BY name LIMIT 20");

    $lines = [];
    if ($categories) {
        $lines[] = 'Product categories we carry: ' . implode(', ', array_column($categories, 'name')) . '.';
    }
    if ($services) {
        $serviceLines = array_map(
            fn($s) => $s['name'] . ' (labor fee ' . formatPrice((float)$s['labor_fee']) . ')',
            $services
        );
        $lines[] = 'Services we offer: ' . implode(', ', $serviceLines) . '.';
    }
    if ($brands) {
        $lines[] = 'Motorcycle brands in our system: ' . implode(', ', array_column($brands, 'name')) . '.';
    }

    return implode("\n", $lines);
}

function chatbotBuildSystemPrompt(): string {
    $shopContext = chatbotBuildShopContext();

    return "You are the MotoTrack Assistant, a friendly and knowledgeable virtual assistant for MotoTrack, "
        . "a motorcycle parts and service shop.\n\n"
        . "Your job:\n"
        . "- Answer motorcycle technical questions (tuning, gearing/sprocket combinations, CVT components, torque specs, maintenance intervals, "
        . "part compatibility, riding advice, etc.) thoroughly and specifically, using your own expert knowledge. "
        . "Give concrete numbers, sizes, and specs whenever they're relevant (e.g. sprocket tooth counts, common spring rates, RPM ranges, torque values, "
        . "recommended intervals) instead of vague generalities — answer like a knowledgeable mechanic would, not like a cautious customer service script.\n"
        . "- The ONLY thing you must not invent is MotoTrack's own business data — this shop's specific prices, stock levels, SKUs, or whether a specific "
        . "service is offered here. For that, rely only on the \"Shop data\" section below; if it's not listed there, say you're not sure and suggest "
        . "checking the Shop page or contacting staff. This restriction does NOT apply to general motorcycle technical knowledge — feel free to state "
        . "real-world specs, part numbers, and figures confidently.\n"
        . "- Write in plain text only — this reply is shown as-is in a chat bubble with no markdown rendering. "
        . "Do not use **bold**, *italics*, #headers, or markdown numbered/bulleted lists (no \"1.\" or \"-\" list syntax). "
        . "For lists, just write plain sentences or separate items with line breaks and a dash-free label, e.g. \"For more acceleration: ...\".\n"
        . "- Favor being complete and specific over being short — a technical question deserves a full technical answer, not a one-liner.\n"
        . "- If asked something unrelated to motorcycles or the shop, politely redirect back to motorcycle/shop topics.\n"
        . "- Never claim to process orders, payments, or bookings yourself — direct the customer to the relevant page (Shop, Book a Service, Cart) to do that.\n\n"
        . "Shop data (only reference if relevant to the question):\n" . $shopContext;
}

/**
 * Generates a chatbot reply, optionally grounded in matching shop data.
 *
 * @param string $userMessage
 * @param array $history Prior turns as [['role' => 'user'|'model', 'text' => string], ...]
 * @return string
 */
function chatbotGetReply(string $userMessage, array $history = []): string {
    $userMessage = trim($userMessage);
    if ($userMessage === '') {
        throw new InvalidArgumentException('Message cannot be empty.');
    }
    if (mb_strlen($userMessage) > 800) {
        $userMessage = mb_substr($userMessage, 0, 800);
    }

    $keywords = chatbotExtractKeywords($userMessage);
    $products = chatbotFindProducts($keywords);
    $models = chatbotFindMotorcycleModels($keywords);
    $services = chatbotFindServices($keywords);

    $matchLines = [];
    foreach ($products as $p) {
        $stockNote = (int)$p['stock'] > 0 ? (int)$p['stock'] . ' in stock' : 'currently out of stock';
        $matchLines[] = "Product: {$p['name']} ({$p['brand']}, {$p['category_name']}) - " . formatPrice((float)$p['price']) . ", {$stockNote}.";
    }
    foreach ($models as $m) {
        $matchLines[] = "Motorcycle in our catalog: {$m['brand_name']} {$m['model_name']}, {$m['cc']}cc, type {$m['type_name']}.";
    }
    foreach ($services as $s) {
        $matchLines[] = "Service: {$s['name']} - labor fee " . formatPrice((float)$s['labor_fee']) . '.';
    }

    $systemPrompt = chatbotBuildSystemPrompt();
    if ($matchLines) {
        $systemPrompt .= "\n\nMatches found for this question:\n" . implode("\n", $matchLines);
    }

    return geminiChat($systemPrompt, $history, $userMessage);
}
