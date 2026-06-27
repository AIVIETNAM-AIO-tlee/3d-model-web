<?php
require '../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('index.php?p=admin_login');
}

if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
    $_SESSION['admin_error'] = 'Invalid request token. Please try again.';
    authRedirect('index.php?p=admin_login');
}

authLogoutAdmin();
authStartSession();
$_SESSION['admin_success'] = 'You have been logged out successfully.';
authRedirect('index.php?p=admin_login');
