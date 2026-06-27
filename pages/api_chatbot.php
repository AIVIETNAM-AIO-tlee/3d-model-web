<?php
require_once '../config/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$message = trim((string) ($payload['message'] ?? ''));
if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is required']);
    exit;
}

if (mb_strlen($message) > 600) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is too long']);
    exit;
}

$history = $payload['history'] ?? [];
if (!is_array($history)) {
    $history = [];
}

$hardcodedGeminiApiKey = '';
$hardcodedGeminiModel = 'gemini-2.5-flash';

$apiKey = getFirstAvailableSetting(['GEMINI_API_KEY', 'GOOGLE_API_KEY']);
if ($apiKey === '' && $hardcodedGeminiApiKey !== '') {
    $apiKey = trim($hardcodedGeminiApiKey);
}

$model = getFirstAvailableSetting(['GEMINI_MODEL']);
if ($model === '' && $hardcodedGeminiModel !== '') {
    $model = trim($hardcodedGeminiModel);
}
if ($model === '') {
    $model = 'gemini-2.5-flash';
}

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'error' => 'AI assistant is not configured. Set GEMINI_API_KEY to enable responses.'
    ]);
    exit;
}

$systemPrompt = "You are the AI assistant for Asset Forge 3D, a commercial marketplace for downloadable 3D assets. Keep answers concise, practical, and focused on products, purchase flow, formats, licensing basics, and support guidance.";
$currentUser = authCurrentUser();
$storeContext = buildStoreContext(authGetPDO(), $message, $currentUser);

if ($storeContext !== '') {
    $systemPrompt .= "\n\nUse the following live store context from database for factual details."
        . " If context has relevant details, prioritize it over generic assumptions."
        . " If information is missing, say so clearly and suggest where the user can check."
        . "\n\n" . $storeContext;
}

$contents = [];

$safeHistory = array_slice($history, -8);
foreach ($safeHistory as $item) {
    if (!is_array($item)) {
        continue;
    }

    $role = (string) ($item['role'] ?? '');
    $content = trim((string) ($item['content'] ?? ''));

    if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
        continue;
    }

    $geminiRole = $role === 'assistant' ? 'model' : 'user';
    $contents[] = [
        'role' => $geminiRole,
        'parts' => [
            ['text' => mb_substr($content, 0, 1200)],
        ],
    ];
}

$contents[] = [
    'role' => 'user',
    'parts' => [
        ['text' => $message],
    ],
];

$requestBody = [
    'systemInstruction' => [
        'parts' => [
            ['text' => $systemPrompt],
        ],
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.4,
        'maxOutputTokens' => 260,
    ],
];

[$statusCode, $responseBody, $transportError] = sendGeminiGenerateContentRequest($apiKey, $model, $requestBody);

if ($transportError !== null) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $transportError]);
    exit;
}

$decoded = json_decode((string) $responseBody, true);
if (!is_array($decoded)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Invalid response from AI provider']);
    exit;
}

if ($statusCode >= 400) {
    $providerError = $decoded['error']['message'] ?? 'AI provider returned an error';
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => $providerError]);
    exit;
}

$replyParts = $decoded['candidates'][0]['content']['parts'] ?? [];
$reply = '';
if (is_array($replyParts)) {
    foreach ($replyParts as $part) {
        if (!is_array($part)) {
            continue;
        }

        $reply .= (string) ($part['text'] ?? '');
    }
}

$reply = trim($reply);
if ($reply === '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'AI reply was empty']);
    exit;
}

echo json_encode(['ok' => true, 'reply' => $reply]);
exit;

function sendGeminiGenerateContentRequest(string $apiKey, string $model, array $requestBody): array {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($apiKey);
    $headers = [
        'Content-Type: application/json',
    ];

    $jsonBody = json_encode($requestBody);
    if ($jsonBody === false) {
        return [0, null, 'Failed to encode AI request body'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return [0, null, 'AI request failed: ' . $error];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, $response, null];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $jsonBody,
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return [0, null, 'AI request failed'];
    }

    $statusCode = 200;
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }
    }

    return [$statusCode, $response, null];
}

function buildStoreContext(PDO $pdo, string $message, ?array $currentUser): string {
    $contextLines = [];

    try {
        $totalProducts = (int) ($pdo->query('SELECT COUNT(*) FROM Products')->fetchColumn() ?: 0);
        $contextLines[] = 'Catalog total products: ' . $totalProducts;
    } catch (Throwable $e) {
        // Ignore missing table/schema issues to keep chatbot available.
    }

    $keywords = extractSearchKeywords($message);
    $products = findRelevantProducts($pdo, $keywords);
    if ($products !== []) {
        $contextLines[] = 'Relevant products:';
        foreach ($products as $product) {
            $isPremium = !empty($product['is_premium']);
            $displayPrice = $isPremium
                ? formatCurrencyVND($product['price'] ?? 0)
                : 'Free';
            $contextLines[] = '- ' . (string) $product['name']
                . ' | Category: ' . (string) ($product['category_name'] ?? 'Uncategorized')
                . ' | Price: ' . $displayPrice
                . ' | Tags: ' . (string) ($product['tags'] ?? 'n/a');
        }
    }

    try {
        $categoryStmt = $pdo->query('SELECT COALESCE(c.name, "Uncategorized") AS category_name, COUNT(p.id) AS product_count FROM Products p LEFT JOIN Categories c ON c.id = p.category_id GROUP BY COALESCE(c.name, "Uncategorized") ORDER BY product_count DESC LIMIT 6');
        $categoryRows = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($categoryRows) && $categoryRows !== []) {
            $parts = [];
            foreach ($categoryRows as $row) {
                $parts[] = (string) $row['category_name'] . ' (' . (int) $row['product_count'] . ')';
            }
            $contextLines[] = 'Top categories by product count: ' . implode(', ', $parts);
        }
    } catch (Throwable $e) {
        // Ignore if categories table is unavailable.
    }

    if (is_array($currentUser) && !empty($currentUser['id'])) {
        try {
            $orderStmt = $pdo->prepare('SELECT id, total_amount, delivery_status, created_at FROM Orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
            $orderStmt->execute([(int) $currentUser['id']]);
            $orders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

            if (is_array($orders) && $orders !== []) {
                $contextLines[] = 'Recent orders for current user:';
                foreach ($orders as $order) {
                    $contextLines[] = '- Order #' . (int) $order['id']
                        . ' | Total: ' . formatCurrencyVND($order['total_amount'] ?? 0)
                        . ' | Status: ' . (string) ($order['delivery_status'] ?? 'unknown')
                        . ' | Date: ' . (string) ($order['created_at'] ?? '');
                }
            }
        } catch (Throwable $e) {
            // Ignore if orders table is unavailable.
        }
    }

    return implode("\n", $contextLines);
}

function findRelevantProducts(PDO $pdo, array $keywords): array {
    $baseSql = 'SELECT p.name, p.price, p.is_premium, p.tags, COALESCE(c.name, "Uncategorized") AS category_name FROM Products p LEFT JOIN Categories c ON c.id = p.category_id';

    if ($keywords === []) {
        $stmt = $pdo->query($baseSql . ' ORDER BY p.created_at DESC LIMIT 4');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    $conditions = [];
    $params = [];
    foreach ($keywords as $kw) {
        $conditions[] = '(LOWER(p.name) LIKE ? OR LOWER(COALESCE(p.tags, "")) LIKE ? OR LOWER(COALESCE(p.description, "")) LIKE ? OR LOWER(COALESCE(c.name, "")) LIKE ?)';
        $like = '%' . $kw . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = $baseSql . ' WHERE ' . implode(' OR ', $conditions) . ' ORDER BY p.created_at DESC LIMIT 5';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
        return [];
    }

    return $rows;
}

function extractSearchKeywords(string $message): array {
    preg_match_all('/[a-z0-9]{3,}/i', strtolower($message), $matches);
    $rawKeywords = $matches[0] ?? [];
    $stopWords = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'have',
        'what', 'how', 'can', 'you', 'about', 'need', 'find', 'show', 'want',
        'toi', 'ban', 'cho', 'minh', 'cua', 'san', 'pham', 'gia', 'bao',
    ];

    $keywords = [];
    foreach ($rawKeywords as $word) {
        if (in_array($word, $stopWords, true)) {
            continue;
        }

        $keywords[$word] = true;
        if (count($keywords) >= 6) {
            break;
        }
    }

    return array_keys($keywords);
}

function getFirstAvailableSetting(array $keys): string {
    foreach ($keys as $key) {
        $candidates = [
            getenv($key),
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            $_SERVER['REDIRECT_' . $key] ?? null,
        ];

        if (function_exists('apache_getenv')) {
            $candidates[] = apache_getenv($key, true);
            $candidates[] = apache_getenv('REDIRECT_' . $key, true);
        }

        foreach ($candidates as $value) {
            if (!is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}
