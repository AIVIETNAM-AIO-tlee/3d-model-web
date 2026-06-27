<?php
require '../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('index.php?p=login');
}

$next = $_POST['next'] ?? 'home';
if (!preg_match('/^[a-z0-9_\-]+$/i', (string) $next)) {
    $next = 'home';
}

if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
    $_SESSION['auth_error'] = 'Invalid request token. Please refresh and try again.';
    authRedirect('index.php?p=login');
}

$idToken = (string) ($_POST['credential'] ?? '');
if ($idToken === '') {
    $_SESSION['auth_error'] = 'Google credential is missing.';
    authRedirect('index.php?p=login');
}

$verifyResult = authVerifyGoogleIdToken($idToken);
if (!$verifyResult['ok']) {
    $_SESSION['auth_error'] = $verifyResult['message'] ?? 'Google Sign-In failed.';
    authRedirect('index.php?p=login');
}

$pdo = authGetPDO();
$loginResult = authLoginOrRegisterGoogle($pdo, $verifyResult);
if (!$loginResult['ok']) {
    $_SESSION['auth_error'] = $loginResult['message'] ?? 'Google Sign-In failed.';
    authRedirect('index.php?p=login');
}

authLoginUser((int) $loginResult['user_id']);
authRedirect('index.php?p=' . rawurlencode((string) $next));
