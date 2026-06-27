<?php
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    error_log('api_purchase exception: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Server error while processing purchase. Please try again.',
    ]);
    exit;
});

register_shutdown_function(function (): void {
    $lastError = error_get_last();
    if (!is_array($lastError)) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    error_log('api_purchase fatal: ' . (($lastError['message'] ?? 'unknown fatal error')));
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Server error while processing purchase. Please try again.',
        ]);
    }
});

function buildPaymentSimulationInstructions($method, $amount, $orderId, $paymentReference) {
    $normalizedMethod = normalizePaymentMethod($method);
    $roundedAmount = max(0, (int) round((float) $amount));
    $transferNote = 'AF3D ORDER ' . (int) $orderId;
    $reference = (string) $paymentReference;

    $base = [
        'method' => $normalizedMethod,
        'amount' => (float) $amount,
        'transfer_note' => $transferNote,
        'reference' => $reference,
        'steps' => [
            'Open your selected payment app.',
            'Transfer the exact amount shown.',
            'Use the transfer note exactly as provided.',
            'After transfer, click Complete Payment Simulation.',
        ],
    ];

    if ($normalizedMethod === 'momo') {
        $qrData = rawurlencode('MOMO|WALLET:0909123456|AMOUNT:' . $roundedAmount . '|NOTE:' . $transferNote . '|REF:' . $reference);
        return array_merge($base, [
            'display_name' => 'MoMo Wallet',
            'account_name' => 'ASSET FORGE 3D',
            'wallet_id' => '0909123456',
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=640x640&margin=16&data=' . $qrData,
        ]);
    }

    if ($normalizedMethod === 'zalopay') {
        $qrData = rawurlencode('ZALOPAY|WALLET:0909988776|AMOUNT:' . $roundedAmount . '|NOTE:' . $transferNote . '|REF:' . $reference);
        return array_merge($base, [
            'display_name' => 'ZaloPay Wallet',
            'account_name' => 'ASSET FORGE 3D',
            'wallet_id' => '0909988776',
            'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=640x640&margin=16&data=' . $qrData,
        ]);
    }

    $vietQrConfig = paymentVietQrConfig();
    $vietQrUrl = paymentVietQrGenerateQuickLink($roundedAmount, $transferNote, $vietQrConfig);

    if ($vietQrUrl === '') {
        $fallbackData = rawurlencode('VIETQR|AMOUNT:' . $roundedAmount . '|NOTE:' . $transferNote . '|REF:' . $reference);
        $vietQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=800x800&margin=10&data=' . $fallbackData;
    }

    return array_merge($base, [
        'display_name' => 'VietQR Bank Transfer',
        'bank_name' => ((string) ($vietQrConfig['bank_code'] ?? 'MB')) . ' Bank',
        'bank_bin' => (string) ($vietQrConfig['bank_bin'] ?? '970422'),
        'account_name' => (string) ($vietQrConfig['account_name'] ?? 'ASSET FORGE 3D SANDBOX'),
        'account_number' => (string) ($vietQrConfig['account_number'] ?? ''),
        'sandbox_mode' => !empty($vietQrConfig['enabled']),
        'steps' => [
            'Open your banking app and scan the QR on the left.',
            'Confirm the exact amount and transfer note.',
            'Complete transfer in your app.',
            'Return here and click Complete Payment.',
        ],
        'payment_url' => $vietQrUrl,
        'qr_url' => $vietQrUrl,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$user = authCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please sign in to purchase premium products.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$csrfToken = (string) ($payload['csrf_token'] ?? '');
if (!authVerifyCSRF($csrfToken)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request token. Please refresh and try again.']);
    exit;
}

$mode = (string) ($payload['mode'] ?? 'cart');

if ($mode === 'confirm_payment') {
    $paymentReference = trim((string) ($payload['payment_reference'] ?? ''));
    if ($paymentReference === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing payment reference.']);
        exit;
    }

    $pdo = authGetPDO();
    $order = getOrderByPaymentReference($pdo, $paymentReference);
    if (!$order || (int) ($order['user_id'] ?? 0) !== (int) $user['id']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Order not found.']);
        exit;
    }

    $finalized = finalizePendingPremiumOrder($pdo, $paymentReference, [
        'confirmed_by' => 'customer',
        'confirmed_at' => date('c'),
    ]);

    if (empty($finalized['ok'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => (string) ($finalized['message'] ?? 'Unable to confirm payment.')]);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'order_id' => (int) ($finalized['order_id'] ?? 0),
        'payment_reference' => (string) ($finalized['payment_reference'] ?? $paymentReference),
        'already_finalized' => !empty($finalized['already_finalized']),
        'redirect_url' => 'index.php?p=inventory',
    ]);
    exit;
}

if ($mode === 'payment_status') {
    $paymentReference = trim((string) ($payload['payment_reference'] ?? ''));
    if ($paymentReference === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Missing payment reference.']);
        exit;
    }

    $pdo = authGetPDO();
    $order = getOrderByPaymentReference($pdo, $paymentReference);
    if (!$order || (int) ($order['user_id'] ?? 0) !== (int) $user['id']) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Order not found.']);
        exit;
    }

    $paymentStatus = strtolower(trim((string) ($order['payment_status'] ?? 'pending')));
    $isCompleted = $paymentStatus === 'completed';

    echo json_encode([
        'ok' => true,
        'order_id' => (int) ($order['id'] ?? 0),
        'payment_reference' => (string) ($order['payment_reference'] ?? $paymentReference),
        'payment_status' => $paymentStatus,
        'is_completed' => $isCompleted,
        'redirect_url' => 'index.php?p=inventory',
    ]);
    exit;
}

$productIds = [];
$couponCode = strtoupper(trim((string) ($payload['coupon_code'] ?? '')));
$paymentMethod = normalizePaymentMethod($payload['payment_method'] ?? 'manual');

if ($mode === 'buy_now') {
    $productId = (int) ($payload['product_id'] ?? 0);
    if ($productId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Product id is required for buy now.']);
        exit;
    }
    $productIds[] = $productId;
} else {
    $items = $payload['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = (int) ($item['id'] ?? 0);
        if ($id > 0) {
            $productIds[] = $id;
        }
    }
}

if ($productIds === []) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'No products selected for purchase.']);
    exit;
}

$pdo = authGetPDO();

if (in_array($paymentMethod, ['momo', 'zalopay'], true)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => ucfirst($paymentMethod) . ' payment is coming soon. Please use VietQR for now.',
    ]);
    exit;
}

if ($paymentMethod !== 'vietqr') {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Unsupported payment method. Please use VietQR.',
    ]);
    exit;
}

$result = createPendingPremiumOrder($pdo, (int) $user['id'], $productIds, [
    'coupon_code' => $couponCode,
    'payment_method' => $paymentMethod,
]);
if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => (string) ($result['message'] ?? 'Unable to process purchase.')]);
    exit;
}

$finalMethod = (string) ($result['payment_method'] ?? $paymentMethod);
$paymentInstructions = null;
$orderId = (int) ($result['order_id'] ?? 0);
if ($orderId > 0 && $finalMethod === 'vietqr') {
    $paymentInstructions = buildPaymentSimulationInstructions(
        $finalMethod,
        (float) ($result['total_amount'] ?? 0),
        $orderId,
        (string) ($result['payment_reference'] ?? '')
    );
}

echo json_encode([
    'ok' => true,
    'order_id' => $orderId > 0 ? $orderId : null,
    'purchased_ids' => array_values($result['purchased_ids'] ?? []),
    'already_owned_ids' => array_values($result['already_owned_ids'] ?? []),
    'skipped_non_premium_ids' => array_values($result['skipped_non_premium_ids'] ?? []),
    'subtotal_amount' => (float) ($result['subtotal_amount'] ?? 0),
    'discount_amount' => (float) ($result['discount_amount'] ?? 0),
    'total_amount' => (float) ($result['total_amount'] ?? 0),
    'payment_method' => $finalMethod,
    'payment_reference' => (string) ($result['payment_reference'] ?? ''),
    'coupon' => $result['coupon'] ?? null,
    'payment_instructions' => $paymentInstructions,
    'message' => $result['message'] ?? 'Purchase completed successfully.',
    'redirect_url' => 'index.php?p=inventory',
    'csrf_token' => authCSRFToken(),
]);
