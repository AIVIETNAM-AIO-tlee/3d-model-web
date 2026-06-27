<?php
require_once __DIR__ . '/database.php';

// Optional hardcoded MoMo sandbox settings.
// Fill these values directly if you do not want to use environment variables.
// Leave as empty string to keep environment-variable behavior.
const PAYMENT_MOMO_SANDBOX_ENABLED = '1';
const PAYMENT_MOMO_PARTNER_CODE = 'MOMOF02H20260420_TEST';
const PAYMENT_MOMO_ACCESS_KEY = 'wHK9TzXWPSnnxoBO';
const PAYMENT_MOMO_SECRET_KEY = 'Fi6iEkZy5M4ZjkIYVUVyeJnBcEqytB0X';
const PAYMENT_MOMO_RETURN_URL = 'http://localhost:8000/index.php?p=momo_return';
const PAYMENT_MOMO_IPN_URL = '';
const PAYMENT_MOMO_SANDBOX_ENDPOINT = 'https://test-payment.momo.vn/v2/gateway/api/create';
const PAYMENT_MOMO_ORDER_TYPE = '';
const PAYMENT_MOMO_REQUEST_TYPE = '';
const PAYMENT_MOMO_LANG = '';

// Optional VietQR sandbox-like settings for local/testing flow.
// VietQR itself does not provide a callback sandbox like MoMo, so this mode is simulation-oriented.
const PAYMENT_VIETQR_SANDBOX_ENABLED = '1';
const PAYMENT_VIETQR_BANK_CODE = 'OCB';
const PAYMENT_VIETQR_BANK_BIN = '970448';
const PAYMENT_VIETQR_ACCOUNT_NUMBER = '0004100044287004';
const PAYMENT_VIETQR_ACCOUNT_NAME = 'LE QUANG THANH';
const PAYMENT_VIETQR_TEMPLATE = 'compact2';
const PAYMENT_VIETQR_MEDIA = 'jpg';

function paymentSettingOrEnv(string $hardcodedValue, array $envKeys): string {
    $value = trim($hardcodedValue);
    if ($value !== '') {
        return $value;
    }

    return paymentGetFirstSetting($envKeys);
}

function paymentGetFirstSetting(array $keys): string {
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

function paymentNormalizeBool(string $value): bool {
    $normalized = strtolower(trim($value));
    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function paymentResolvePublicBaseUrl(): string {
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $scheme = 'https';
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https' ? 'https' : 'http';
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    return $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
}

function paymentMomoConfig(): array {
    $baseUrl = paymentGetFirstSetting(['APP_PUBLIC_URL', 'APP_URL']);
    if ($baseUrl === '') {
        $baseUrl = paymentResolvePublicBaseUrl();
    }

    $returnUrl = paymentSettingOrEnv(PAYMENT_MOMO_RETURN_URL, ['MOMO_RETURN_URL']);
    if ($returnUrl === '') {
        $returnUrl = $baseUrl . '/index.php?p=momo_return';
    }

    $ipnUrl = paymentSettingOrEnv(PAYMENT_MOMO_IPN_URL, ['MOMO_IPN_URL']);
    if ($ipnUrl === '') {
        $ipnUrl = $returnUrl;
    }

    return [
        'enabled' => paymentNormalizeBool(paymentSettingOrEnv(PAYMENT_MOMO_SANDBOX_ENABLED, ['MOMO_SANDBOX_ENABLED'])),
        'partner_code' => paymentSettingOrEnv(PAYMENT_MOMO_PARTNER_CODE, ['MOMO_PARTNER_CODE', 'MOMO_SANDBOX_PARTNER_CODE']),
        'access_key' => paymentSettingOrEnv(PAYMENT_MOMO_ACCESS_KEY, ['MOMO_ACCESS_KEY', 'MOMO_SANDBOX_ACCESS_KEY']),
        'secret_key' => paymentSettingOrEnv(PAYMENT_MOMO_SECRET_KEY, ['MOMO_SECRET_KEY', 'MOMO_SANDBOX_SECRET_KEY']),
        'endpoint' => paymentSettingOrEnv(PAYMENT_MOMO_SANDBOX_ENDPOINT, ['MOMO_SANDBOX_ENDPOINT']) ?: 'https://test-payment.momo.vn/v2/gateway/api/create',
        'return_url' => $returnUrl,
        'ipn_url' => $ipnUrl,
        'order_type' => paymentSettingOrEnv(PAYMENT_MOMO_ORDER_TYPE, ['MOMO_ORDER_TYPE']) ?: 'momo_wallet',
        'request_type' => paymentSettingOrEnv(PAYMENT_MOMO_REQUEST_TYPE, ['MOMO_REQUEST_TYPE']) ?: 'captureWallet',
        'lang' => paymentSettingOrEnv(PAYMENT_MOMO_LANG, ['MOMO_LANG']) ?: 'vi',
        'base_url' => $baseUrl,
    ];
}

function paymentMomoIsConfigured(array $config = null): bool {
    $config = $config ?: paymentMomoConfig();
    if (empty($config['enabled'])) {
        return false;
    }

    foreach (['partner_code', 'access_key', 'secret_key', 'endpoint', 'return_url'] as $key) {
        if (trim((string) ($config[$key] ?? '')) === '') {
            return false;
        }
    }

    return true;
}

function paymentVietQrConfig(): array {
    return [
        'enabled' => paymentNormalizeBool(paymentSettingOrEnv(PAYMENT_VIETQR_SANDBOX_ENABLED, ['VIETQR_SANDBOX_ENABLED'])),
        'bank_code' => paymentSettingOrEnv(PAYMENT_VIETQR_BANK_CODE, ['VIETQR_BANK_CODE']) ?: 'OCB',
        'bank_bin' => paymentSettingOrEnv(PAYMENT_VIETQR_BANK_BIN, ['VIETQR_BANK_BIN']) ?: '970448',
        'account_number' => paymentSettingOrEnv(PAYMENT_VIETQR_ACCOUNT_NUMBER, ['VIETQR_ACCOUNT_NUMBER']),
        'account_name' => paymentSettingOrEnv(PAYMENT_VIETQR_ACCOUNT_NAME, ['VIETQR_ACCOUNT_NAME']) ?: 'ASSET FORGE 3D',
        'template' => paymentSettingOrEnv(PAYMENT_VIETQR_TEMPLATE, ['VIETQR_TEMPLATE']) ?: 'compact2',
        'media' => paymentSettingOrEnv(PAYMENT_VIETQR_MEDIA, ['VIETQR_MEDIA']) ?: 'jpg',
    ];
}

// Build quick-link in the same style used by vietqr-node: genQuickLink(...)
function paymentVietQrGenerateQuickLink(int $amount, string $memo, array $config = null): string {
    $config = $config ?: paymentVietQrConfig();
    $bank = trim((string) ($config['bank_bin'] ?? ''));
    $accountNumber = trim((string) ($config['account_number'] ?? ''));
    $template = trim((string) ($config['template'] ?? 'compact2'));
    $media = trim((string) ($config['media'] ?? 'jpg'));

    if ($bank === '' || $accountNumber === '') {
        return '';
    }

    $normalizedMedia = strtolower($media);
    if (!in_array($normalizedMedia, ['jpg', 'jpeg', 'png'], true)) {
        $normalizedMedia = 'jpg';
    }

    return 'https://api.vietqr.io/'
        . rawurlencode($bank)
        . '/'
        . rawurlencode($accountNumber)
        . '/'
        . max(0, $amount)
        . '/'
        . rawurlencode($memo)
        . '/'
        . rawurlencode($template)
        . '.'
        . $normalizedMedia
        . '?accountName='
        . rawurlencode((string) ($config['account_name'] ?? 'ASSET FORGE 3D'));
}

function paymentVietQrBuildImageUrl(int $amount, string $addInfo, array $config = null): string {
    $config = $config ?: paymentVietQrConfig();
    $bankCode = trim((string) ($config['bank_code'] ?? 'MB'));
    $accountNumber = trim((string) ($config['account_number'] ?? ''));
    $template = trim((string) ($config['template'] ?? 'compact2'));

    if ($bankCode === '' || $accountNumber === '') {
        return '';
    }

    return 'https://img.vietqr.io/image/'
        . rawurlencode($bankCode)
        . '-'
        . rawurlencode($accountNumber)
        . '-'
        . rawurlencode($template)
        . '.png?amount='
        . max(0, $amount)
        . '&addInfo='
        . rawurlencode($addInfo)
        . '&accountName='
        . rawurlencode((string) ($config['account_name'] ?? 'ASSET FORGE 3D SANDBOX'));
}

function paymentBuildSignatureString(array $data, array $orderedKeys): string {
    $parts = [];
    foreach ($orderedKeys as $key) {
        $parts[] = $key . '=' . (string) ($data[$key] ?? '');
    }

    return implode('&', $parts);
}

function paymentGenerateHmacSignature(array $data, array $orderedKeys, string $secretKey): string {
    return hash_hmac('sha256', paymentBuildSignatureString($data, $orderedKeys), $secretKey);
}

function paymentHttpPostJson(string $url, array $payload, array $headers = []): array {
    $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonBody === false) {
        return [
            'ok' => false,
            'status_code' => 0,
            'body' => '',
            'json' => null,
            'error' => 'Unable to encode JSON payload.',
        ];
    }

    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $headers = array_merge($defaultHeaders, $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'status_code' => $statusCode,
                'body' => '',
                'json' => null,
                'error' => $error !== '' ? $error : 'Unable to reach payment gateway.',
            ];
        }

        $decoded = json_decode((string) $body, true);
        return [
            'ok' => $statusCode >= 200 && $statusCode < 300,
            'status_code' => $statusCode,
            'body' => (string) $body,
            'json' => is_array($decoded) ? $decoded : null,
            'error' => null,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $jsonBody,
            'timeout' => 30,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return [
            'ok' => false,
            'status_code' => 0,
            'body' => '',
            'json' => null,
            'error' => 'Unable to reach payment gateway.',
        ];
    }

    $statusCode = 0;
    if (!empty($http_response_header) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', (string) $http_response_header[0], $matches)) {
        $statusCode = (int) $matches[1];
    }

    $decoded = json_decode((string) $body, true);
    return [
        'ok' => $statusCode >= 200 && $statusCode < 300,
        'status_code' => $statusCode,
        'body' => (string) $body,
        'json' => is_array($decoded) ? $decoded : null,
        'error' => null,
    ];
}

function paymentMomoBuildCreatePayload(array $config, array $orderData): array {
    $amount = (string) max(0, (int) round((float) ($orderData['amount'] ?? 0)));
    $orderReference = (string) ($orderData['order_reference'] ?? '');
    $requestId = (string) ($orderData['request_id'] ?? '');
    $orderInfo = (string) ($orderData['order_info'] ?? 'Asset Forge 3D order');
    $extraData = (string) ($orderData['extra_data'] ?? '');

    $payload = [
        'partnerCode' => $config['partner_code'],
        'accessKey' => $config['access_key'],
        'requestId' => $requestId,
        'amount' => $amount,
        'orderId' => $orderReference,
        'orderInfo' => $orderInfo,
        'orderType' => $config['order_type'],
        'redirectUrl' => $config['return_url'],
        'ipnUrl' => $config['ipn_url'],
        'extraData' => $extraData,
        'requestType' => $config['request_type'],
        'lang' => $config['lang'],
    ];

    $payload['signature'] = paymentGenerateHmacSignature($payload, [
        'accessKey',
        'amount',
        'extraData',
        'ipnUrl',
        'orderId',
        'orderInfo',
        'orderType',
        'partnerCode',
        'redirectUrl',
        'requestId',
        'requestType',
    ], $config['secret_key']);

    return $payload;
}

function paymentMomoGenerateSandboxPayment(PDO $pdo, int $userId, array $productIds, array $options = []): array {
    $config = paymentMomoConfig();
    if (!paymentMomoIsConfigured($config)) {
        return [
            'ok' => false,
            'message' => 'MoMo sandbox is not configured. Set MOMO_SANDBOX_ENABLED, MOMO_PARTNER_CODE, MOMO_ACCESS_KEY, MOMO_SECRET_KEY, MOMO_RETURN_URL, and optionally MOMO_IPN_URL.',
        ];
    }

    $pending = createPendingPremiumOrder($pdo, $userId, $productIds, [
        'coupon_code' => $options['coupon_code'] ?? '',
        'payment_method' => 'momo',
    ]);

    if (empty($pending['ok'])) {
        return $pending;
    }

    $orderId = (int) ($pending['order_id'] ?? 0);
    $paymentReference = (string) ($pending['payment_reference'] ?? '');
    if ($orderId <= 0 || $paymentReference === '') {
        return [
            'ok' => true,
            'order_id' => null,
            'payment_reference' => null,
            'payment_instructions' => null,
            'message' => (string) ($pending['message'] ?? 'No payable premium items found.'),
        ];
    }

    $requestId = 'AF3D-MO-REQ-' . $orderId . '-' . time();
    $extraData = base64_encode(json_encode([
        'order_id' => $orderId,
        'payment_reference' => $paymentReference,
        'user_id' => $userId,
        'coupon_code' => $options['coupon_code'] ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $payload = paymentMomoBuildCreatePayload($config, [
        'amount' => $pending['total_amount'] ?? 0,
        'order_reference' => $paymentReference,
        'request_id' => $requestId,
        'order_info' => 'Asset Forge 3D order #' . $orderId,
        'extra_data' => $extraData,
    ]);

    $gatewayResponse = paymentHttpPostJson($config['endpoint'], $payload);
    $gatewayData = $gatewayResponse['json'];

    if (empty($gatewayResponse['ok']) || !is_array($gatewayData) || (int) ($gatewayData['resultCode'] ?? -1) !== 0) {
        updateOrderPaymentStatusByReference($pdo, $paymentReference, 'failed', 'failed');
        $message = (string) ($gatewayData['message'] ?? $gatewayResponse['error'] ?? 'Unable to initialize MoMo payment.');
        return [
            'ok' => false,
            'message' => $message,
        ];
    }

    $paymentUrl = (string) ($gatewayData['payUrl'] ?? '');
    $qrUrl = (string) ($gatewayData['qrCodeUrl'] ?? $gatewayData['payUrl'] ?? '');
    $deeplink = (string) ($gatewayData['deeplink'] ?? '');

    return [
        'ok' => true,
        'order_id' => $orderId,
        'payment_reference' => $paymentReference,
        'payment_method' => 'momo',
        'subtotal_amount' => (float) ($pending['subtotal_amount'] ?? 0),
        'discount_amount' => (float) ($pending['discount_amount'] ?? 0),
        'total_amount' => (float) ($pending['total_amount'] ?? 0),
        'purchased_ids' => array_values($pending['purchased_ids'] ?? []),
        'already_owned_ids' => array_values($pending['already_owned_ids'] ?? []),
        'skipped_non_premium_ids' => array_values($pending['skipped_non_premium_ids'] ?? []),
        'coupon' => $pending['coupon'] ?? null,
        'payment_instructions' => [
            'provider' => 'momo',
            'display_name' => 'MoMo Sandbox',
            'method' => 'momo',
            'amount' => (float) ($pending['total_amount'] ?? 0),
            'reference' => $paymentReference,
            'transfer_note' => 'AF3D ORDER ' . $orderId,
            'payment_url' => $paymentUrl,
            'qr_url' => $qrUrl,
            'deeplink' => $deeplink,
            'steps' => [
                'Open MoMo Sandbox or scan the QR on the left.',
                'Confirm the exact amount and transfer note.',
                'Complete the payment in the MoMo sandbox flow.',
                'After MoMo redirects back, the order will be finalized automatically.',
            ],
            'payment_status_url' => 'index.php?p=momo_return&order_id=' . $orderId,
            'account_name' => 'MoMo Sandbox',
        ],
        'message' => 'MoMo sandbox payment initialized successfully.',
    ];
}

function paymentMomoSignatureForCallback(array $payload, string $secretKey): string {
    return paymentGenerateHmacSignature($payload, [
        'accessKey',
        'amount',
        'extraData',
        'message',
        'orderId',
        'orderInfo',
        'orderType',
        'partnerCode',
        'payType',
        'requestId',
        'responseTime',
        'resultCode',
        'transId',
    ], $secretKey);
}

function paymentMomoVerifyCallback(array $payload): bool {
    $config = paymentMomoConfig();
    if (trim((string) ($config['secret_key'] ?? '')) === '') {
        return false;
    }

    $signature = (string) ($payload['signature'] ?? '');
    if ($signature === '') {
        return false;
    }

    $expected = paymentMomoSignatureForCallback($payload, $config['secret_key']);
    return hash_equals($expected, $signature);
}

function paymentMomoFinalizeCallback(PDO $pdo, array $payload): array {
    $reference = trim((string) ($payload['orderId'] ?? ''));
    if ($reference === '') {
        return ['ok' => false, 'message' => 'Missing order reference.'];
    }

    $resultCode = (int) ($payload['resultCode'] ?? -1);
    if ($resultCode !== 0) {
        updateOrderPaymentStatusByReference($pdo, $reference, 'failed', 'failed');
        return [
            'ok' => false,
            'message' => (string) ($payload['message'] ?? 'Payment was not completed.'),
        ];
    }

    if (!paymentMomoVerifyCallback($payload)) {
        return ['ok' => false, 'message' => 'Invalid payment signature.'];
    }

    $finalized = finalizePendingPremiumOrder($pdo, $reference, $payload);
    if (empty($finalized['ok'])) {
        return $finalized;
    }

    return [
        'ok' => true,
        'order_id' => (int) ($finalized['order_id'] ?? 0),
        'payment_reference' => (string) ($finalized['payment_reference'] ?? $reference),
        'already_finalized' => !empty($finalized['already_finalized']),
    ];
}
