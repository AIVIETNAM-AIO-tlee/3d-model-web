<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/payment.php';

const AUTH_GOOGLE_CLIENT_ID = '1024622009880-05qjg7r59bp7mb2vg4iive6trq6rk4v0.apps.googleusercontent.com';
const AUTH_MAX_FAILED_ATTEMPTS = 5;
const AUTH_LOCK_MINUTES = 15;

function authIsHttps(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }

    return false;
}

function authStartSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        // When PHP emits output before this point (e.g., oversized POST warning),
        // avoid triggering a cascade of additional session/header warnings.
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name('assetforge_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => authIsHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    if (empty($_SESSION['session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['session_initialized'] = true;
    }
}

function authRedirect(string $url): void {
    if (headers_sent()) {
        $jsonUrl = json_encode($url, JSON_UNESCAPED_SLASHES);
        if ($jsonUrl === false) {
            $jsonUrl = '"index.php"';
        }
        echo '<script>window.location.href=' . $jsonUrl . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit;
    }

    header('Location: ' . $url);
    exit;
}

function authGetPDO(): PDO {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = getDBConnection();
    return $pdo;
}

function authEnsureSchema(PDO $pdo): void {
    static $schemaReady = false;

    if ($schemaReady) {
        return;
    }

    $sql = "CREATE TABLE IF NOT EXISTS User_Auth (
        user_id INT PRIMARY KEY,
        provider ENUM('local', 'google') NOT NULL DEFAULT 'local',
        google_sub VARCHAR(64) NULL UNIQUE,
        email_verified TINYINT(1) NOT NULL DEFAULT 0,
        failed_attempts INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_login_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_user_auth_user FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $pdo->exec($sql);
    $schemaReady = true;
}

function authCSRFToken(): string {
    authStartSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function authVerifyCSRF(?string $token): bool {
    authStartSession();

    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function authNormalizeEmail(string $email): string {
    return strtolower(trim($email));
}

function authCurrentUser(): ?array {
    authStartSession();

    static $resolved = false;
    static $cachedUser = null;

    if ($resolved) {
        return $cachedUser;
    }

    $resolved = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $pdo = authGetPDO();
    $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM Users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cachedUser = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) ($user['role'] ?? 'customer'),
    ];

    return $cachedUser;
}

function authIsLoggedIn(): bool {
    return authCurrentUser() !== null;
}

function authLoginAdmin(int $userId): void {
    authStartSession();
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = $userId;
}

function authLogoutAdmin(): void {
    authStartSession();
    unset($_SESSION['admin_user_id']);
}

function authCurrentAdmin(): ?array {
    authStartSession();

    static $resolved = false;
    static $cachedAdmin = null;

    if ($resolved) {
        return $cachedAdmin;
    }

    $resolved = true;

    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $pdo = authGetPDO();
    $stmt = $pdo->prepare("SELECT id, full_name, email, role FROM Users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([(int) $_SESSION['admin_user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        unset($_SESSION['admin_user_id']);
        return null;
    }

    $cachedAdmin = [
        'id' => (int) $user['id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => 'admin',
    ];

    return $cachedAdmin;
}

function authIsAdminLoggedIn(): bool {
    return authCurrentAdmin() !== null;
}

function authRequireAdmin(string $redirectTo = 'index.php?p=admin_login'): void {
    if (!authIsAdminLoggedIn()) {
        authRedirect($redirectTo);
    }
}

function authDigitalOrderStatus(string $status): array {
    $normalized = strtolower(trim($status));

    switch ($normalized) {
        case 'queued':
        case 'processing':
            return ['label' => 'Queued', 'class' => 'bg-warning text-dark'];
        case 'preparing_download':
        case 'shipped':
            return ['label' => 'Preparing Download', 'class' => 'bg-info text-dark'];
        case 'delivered':
            return ['label' => 'Delivered', 'class' => 'bg-success'];
        case 'cancelled':
            return ['label' => 'Cancelled', 'class' => 'bg-danger'];
        case 'failed':
            return ['label' => 'Failed', 'class' => 'bg-secondary'];
        case 'pending':
            return ['label' => 'Pending Payment', 'class' => 'bg-warning text-dark'];
        default:
            return ['label' => ucwords(str_replace(['_', '-'], ' ', $normalized)), 'class' => 'bg-secondary'];
    }
}

function authRequireLogin(string $redirectTo = 'index.php?p=login'): void {
    if (!authIsLoggedIn()) {
        authRedirect($redirectTo);
    }
}

function authLoginUser(int $userId): void {
    authStartSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function authLogoutUser(): void {
    authStartSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function authFindUserByEmail(PDO $pdo, string $email): ?array {
    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM Users WHERE email = ? LIMIT 1');
    $stmt->execute([authNormalizeEmail($email)]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function authFindUserByEmailAndRole(PDO $pdo, string $email, string $role): ?array {
    $stmt = $pdo->prepare('SELECT id, full_name, email, password_hash, role FROM Users WHERE email = ? AND role = ? LIMIT 1');
    $stmt->execute([authNormalizeEmail($email), $role]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function authGetAuthRecord(PDO $pdo, int $userId): array {
    authEnsureSchema($pdo);

    $stmt = $pdo->prepare('SELECT provider, google_sub, email_verified, failed_attempts, locked_until FROM User_Auth WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        return $record;
    }

    $insert = $pdo->prepare('INSERT INTO User_Auth (user_id, provider, email_verified) VALUES (?, ?, ?)');
    $insert->execute([$userId, 'local', 1]);

    return [
        'provider' => 'local',
        'google_sub' => null,
        'email_verified' => 1,
        'failed_attempts' => 0,
        'locked_until' => null,
    ];
}

function authRegisterLocalUser(PDO $pdo, string $fullName, string $email, string $password): array {
    authEnsureSchema($pdo);

    $fullName = trim($fullName);
    $email = authNormalizeEmail($email);

    if ($fullName === '' || mb_strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Please enter a valid full name.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Please enter a valid email address.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password must be at least 8 characters.'];
    }

    if (authFindUserByEmail($pdo, $email)) {
        return ['ok' => false, 'message' => 'Email is already in use.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('INSERT INTO Users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$fullName, $email, $passwordHash, 'customer']);

        $userId = (int) $pdo->lastInsertId();
        $authStmt = $pdo->prepare('INSERT INTO User_Auth (user_id, provider, email_verified, failed_attempts) VALUES (?, ?, ?, 0)');
        $authStmt->execute([$userId, 'local', 1]);

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Registration failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Registration failed. Please try again.'];
    }
}

function authAttemptLocalLogin(PDO $pdo, string $email, string $password): array {
    authEnsureSchema($pdo);

    $email = authNormalizeEmail($email);
    $genericError = 'Invalid email or password.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return ['ok' => false, 'message' => $genericError];
    }

    $user = authFindUserByEmail($pdo, $email);
    if (!$user) {
        return ['ok' => false, 'message' => $genericError];
    }

    $userId = (int) $user['id'];
    $auth = authGetAuthRecord($pdo, $userId);

    if (!empty($auth['locked_until']) && strtotime((string) $auth['locked_until']) > time()) {
        return ['ok' => false, 'message' => 'Account temporarily locked. Please try again later.'];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $failedAttempts = (int) ($auth['failed_attempts'] ?? 0) + 1;

        if ($failedAttempts >= AUTH_MAX_FAILED_ATTEMPTS) {
            $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE user_id = ?');
            $update->execute([AUTH_LOCK_MINUTES, $userId]);
            return ['ok' => false, 'message' => 'Too many failed attempts. Account locked for ' . AUTH_LOCK_MINUTES . ' minutes.'];
        }

        $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = ?, locked_until = NULL WHERE user_id = ?');
        $update->execute([$failedAttempts, $userId]);

        return ['ok' => false, 'message' => $genericError];
    }

    $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = 0, locked_until = NULL, provider = ?, last_login_at = NOW() WHERE user_id = ?');
    $update->execute(['local', $userId]);

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $pdo->prepare('UPDATE Users SET password_hash = ? WHERE id = ?');
        $rehash->execute([$newHash, $userId]);
    }

    return ['ok' => true, 'user_id' => $userId];
}

function authAttemptAdminLogin(PDO $pdo, string $email, string $password): array {
    authEnsureSchema($pdo);

    $email = authNormalizeEmail($email);
    $genericError = 'Invalid admin email or password.';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return ['ok' => false, 'message' => $genericError];
    }

    $user = authFindUserByEmailAndRole($pdo, $email, 'admin');
    if (!$user) {
        return ['ok' => false, 'message' => $genericError];
    }

    $userId = (int) $user['id'];
    $auth = authGetAuthRecord($pdo, $userId);

    if (!empty($auth['locked_until']) && strtotime((string) $auth['locked_until']) > time()) {
        return ['ok' => false, 'message' => 'Admin account temporarily locked. Please try again later.'];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        $failedAttempts = (int) ($auth['failed_attempts'] ?? 0) + 1;

        if ($failedAttempts >= AUTH_MAX_FAILED_ATTEMPTS) {
            $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE user_id = ?');
            $update->execute([AUTH_LOCK_MINUTES, $userId]);
            return ['ok' => false, 'message' => 'Too many failed attempts. Admin account locked for ' . AUTH_LOCK_MINUTES . ' minutes.'];
        }

        $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = ?, locked_until = NULL WHERE user_id = ?');
        $update->execute([$failedAttempts, $userId]);

        return ['ok' => false, 'message' => $genericError];
    }

    $update = $pdo->prepare('UPDATE User_Auth SET failed_attempts = 0, locked_until = NULL, provider = ?, last_login_at = NOW() WHERE user_id = ?');
    $update->execute(['local', $userId]);

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehash = $pdo->prepare('UPDATE Users SET password_hash = ? WHERE id = ?');
        $rehash->execute([$newHash, $userId]);
    }

    return ['ok' => true, 'user_id' => $userId];
}

function authVerifyGoogleIdToken(string $idToken): array {
    if (AUTH_GOOGLE_CLIENT_ID === '') {
        return ['ok' => false, 'message' => 'Google Sign-In is not configured yet.'];
    }

    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
    $response = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            // Local dev environments on Windows often miss CA roots for cURL.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'message' => 'Could not verify Google token: ' . $error];
        }

        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 7,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['ok' => false, 'message' => 'Could not verify Google token.'];
        }
    }

    $payload = json_decode($response, true);
    if (!is_array($payload)) {
        return ['ok' => false, 'message' => 'Invalid Google response.'];
    }

    if (($payload['aud'] ?? '') !== AUTH_GOOGLE_CLIENT_ID) {
        return ['ok' => false, 'message' => 'Invalid Google audience.'];
    }

    $issuer = (string) ($payload['iss'] ?? '');
    if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
        return ['ok' => false, 'message' => 'Invalid Google issuer.'];
    }

    if (($payload['email_verified'] ?? 'false') !== 'true' && (string) ($payload['email_verified'] ?? '') !== '1') {
        return ['ok' => false, 'message' => 'Google account email is not verified.'];
    }

    $sub = (string) ($payload['sub'] ?? '');
    $email = authNormalizeEmail((string) ($payload['email'] ?? ''));

    if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Google token missing required fields.'];
    }

    return [
        'ok' => true,
        'sub' => $sub,
        'email' => $email,
        'full_name' => trim((string) ($payload['name'] ?? 'Google User')),
    ];
}

function authLoginOrRegisterGoogle(PDO $pdo, array $googleUser): array {
    authEnsureSchema($pdo);

    $pdo->beginTransaction();

    try {
        $findBySub = $pdo->prepare('SELECT u.id, u.email FROM Users u INNER JOIN User_Auth ua ON ua.user_id = u.id WHERE ua.google_sub = ? LIMIT 1');
        $findBySub->execute([$googleUser['sub']]);
        $user = $findBySub->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $findByEmail = $pdo->prepare('SELECT id, email FROM Users WHERE email = ? LIMIT 1');
            $findByEmail->execute([$googleUser['email']]);
            $user = $findByEmail->fetch(PDO::FETCH_ASSOC);
        }

        if ($user) {
            $userId = (int) $user['id'];
        } else {
            $randomPassword = bin2hex(random_bytes(24));
            $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);
            $fullName = $googleUser['full_name'] !== '' ? $googleUser['full_name'] : 'Google User';

            $insertUser = $pdo->prepare('INSERT INTO Users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $insertUser->execute([$fullName, $googleUser['email'], $passwordHash, 'customer']);
            $userId = (int) $pdo->lastInsertId();
        }

        $upsertAuth = $pdo->prepare('INSERT INTO User_Auth (user_id, provider, google_sub, email_verified, failed_attempts, locked_until, last_login_at)
            VALUES (?, ?, ?, 1, 0, NULL, NOW())
            ON DUPLICATE KEY UPDATE provider = VALUES(provider), google_sub = VALUES(google_sub), email_verified = 1, failed_attempts = 0, locked_until = NULL, last_login_at = NOW()');
        $upsertAuth->execute([$userId, 'google', $googleUser['sub']]);

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Google login failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Google Sign-In failed. Please try again.'];
    }
}

authStartSession();
