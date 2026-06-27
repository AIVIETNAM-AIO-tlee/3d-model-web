<?php
require '../config/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    authRedirect('index.php');
}

if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
    $_SESSION['auth_error'] = 'Invalid request token. Please try again.';
    authRedirect('index.php?p=login');
}

authLogoutUser();
authStartSession();
$_SESSION['auth_success'] = 'You have been logged out successfully.';
authRedirect('index.php?p=login');
