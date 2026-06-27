<?php
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['resultCode' => 1, 'message' => 'Method not allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload = [];
if (is_string($rawInput) && trim($rawInput) !== '') {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if ($payload === []) {
    $payload = $_POST;
}

if (!is_array($payload) || $payload === []) {
    http_response_code(400);
    echo json_encode(['resultCode' => 1, 'message' => 'Invalid payload']);
    exit;
}

$pdo = authGetPDO();
$result = paymentMomoFinalizeCallback($pdo, $payload);

if (empty($result['ok'])) {
    http_response_code(400);
    echo json_encode([
        'resultCode' => 1,
        'message' => (string) ($result['message'] ?? 'Unable to finalize payment.'),
    ]);
    exit;
}

echo json_encode([
    'resultCode' => 0,
    'message' => 'OK',
    'orderId' => (string) ($result['payment_reference'] ?? ($payload['orderId'] ?? '')),
    'order_id' => (int) ($result['order_id'] ?? 0),
]);
