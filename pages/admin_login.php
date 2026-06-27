<?php
require_once '../config/auth.php';

if (authIsAdminLoggedIn()) {
    authRedirect('index.php?p=admin_dashboard');
}

$error = null;
$success = null;

if (!empty($_SESSION['admin_success'])) {
    $success = (string) $_SESSION['admin_success'];
    unset($_SESSION['admin_success']);
}

if (!empty($_SESSION['admin_error'])) {
    $error = (string) $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        try {
            $pdo = authGetPDO();
            $result = authAttemptAdminLogin(
                $pdo,
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? '')
            );

            if ($result['ok']) {
                authLoginAdmin((int) $result['user_id']);
                authRedirect('index.php?p=admin_dashboard');
            }

            $error = $result['message'] ?? 'Login failed.';
        } catch (Throwable $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = 'Unable to login right now. Please try again later.';
        }
    }
}

require '../components/header.php';
?>

<main class="admin-shell d-flex align-items-center justify-content-center px-3 py-5">
  <div class="card border-0 shadow-sm rounded-4" style="max-width: 520px; width: 100%;">
    <div class="card-body p-4 p-lg-5">
      <div class="text-center mb-4">
        <span class="badge rounded-pill text-bg-primary mb-3">Admin Portal</span>
        <h1 class="h3 mb-2">Sign in</h1>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="index.php?p=admin_login" class="d-grid gap-3" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <div>
          <label for="admin-email" class="form-label">Admin email</label>
          <input id="admin-email" name="email" type="email" class="form-control" required autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div>
          <label for="admin-password" class="form-label">Password</label>
          <input id="admin-password" name="password" type="password" class="form-control" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-dark w-100">Login as Admin</button>
      </form>

      <div class="alert alert-light border mt-4 mb-0 small text-secondary">
        ONLY <span class="fw-semibold">admin</span> can access.
      </div>
    </div>
  </div>
</main>

</body>
</html>
