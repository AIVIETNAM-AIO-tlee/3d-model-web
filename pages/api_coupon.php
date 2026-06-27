<?php
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

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

$couponCode = strtoupper(trim((string) ($payload['coupon_code'] ?? '')));
$items = $payload['items'] ?? [];
if (!is_array($items)) {
    $items = [];
}

$productIds = [];
foreach ($items as $item) {
    if (!is_array($item)) {
        continue;
    }
    $id = (int) ($item['id'] ?? 0);
    if ($id > 0) {
        $productIds[] = $id;
    }
}

if ($productIds === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'No products selected for coupon preview.']);
    exit;
}

$user = authCurrentUser();
$userId = $user ? (int) ($user['id'] ?? 0) : 0;

$pdo = authGetPDO();
$result = previewPremiumPurchase($pdo, $userId, $productIds, $couponCode);
if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string) ($result['message'] ?? 'Unable to preview coupon.')]);
    exit;
}

$coupon = $result['coupon'] ?? null;
$couponError = (string) ($result['coupon_error'] ?? '');

if ($couponCode !== '' && !$coupon) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'error' => $couponError !== '' ? $couponError : 'Coupon is invalid or not applicable.',
        'subtotal_amount' => (float) ($result['subtotal_amount'] ?? 0),
        'discount_amount' => 0,
        'total_amount' => (float) ($result['total_amount'] ?? 0),
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'coupon' => $coupon,
    'subtotal_amount' => (float) ($result['subtotal_amount'] ?? 0),
    'discount_amount' => (float) ($result['discount_amount'] ?? 0),
    'total_amount' => (float) ($result['total_amount'] ?? 0),
    'already_owned_ids' => array_values($result['already_owned_ids'] ?? []),
    'skipped_non_premium_ids' => array_values($result['skipped_non_premium_ids'] ?? []),
    'purchasable_ids' => array_values($result['purchasable_ids'] ?? []),
]);
