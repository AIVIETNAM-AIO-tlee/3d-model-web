<?php
require '../components/header.php';

if (authIsLoggedIn()) {
    authRedirect('index.php');
}

$next = $_GET['next'] ?? 'home';
if (!preg_match('/^[a-z0-9_\-]+$/i', $next)) {
    $next = 'home';
}

$error = null;
$success = null;

if (!empty($_SESSION['auth_success'])) {
    $success = (string) $_SESSION['auth_success'];
    unset($_SESSION['auth_success']);
}

if (!empty($_SESSION['auth_error'])) {
    $error = (string) $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        try {
            $pdo = authGetPDO();
            $result = authAttemptLocalLogin(
                $pdo,
                (string) ($_POST['email'] ?? ''),
                (string) ($_POST['password'] ?? '')
            );

            if ($result['ok']) {
                authLoginUser((int) $result['user_id']);
                authRedirect('index.php?p=' . rawurlencode($next));
            }

            $error = $result['message'] ?? 'Login failed.';
        } catch (Throwable $e) {
            error_log('Login page error: ' . $e->getMessage());
            $error = 'Unable to login right now. Please try again later.';
        }
    }
}
?>
<?php require '../components/navBar.php'; ?>

<main class="container py-4 py-lg-5" style="max-width: 560px;">
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4 p-lg-5">
      <p class="text-uppercase small fw-bold text-secondary mb-2">Account</p>
      <h1 class="h3 mb-2">Sign in</h1>
      <p class="text-secondary mb-4">Access your account securely.</p>

      <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="index.php?p=login&amp;next=<?php echo rawurlencode($next); ?>" class="d-grid gap-3" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <div>
          <label for="login-email" class="form-label">Email</label>
          <input id="login-email" name="email" type="email" class="form-control" required autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div>
          <label for="login-password" class="form-label">Password</label>
          <input id="login-password" name="password" type="password" class="form-control" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-dark w-100">Sign in</button>
      </form>

      <div class="position-relative text-center my-4">
        <span class="px-2 bg-white text-secondary small">or</span>
      </div>

      <?php if (AUTH_GOOGLE_CLIENT_ID !== ''): ?>
        <div id="g_id_onload"
             data-client_id="<?php echo htmlspecialchars(AUTH_GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8'); ?>"
             data-context="signin"
             data-ux_mode="popup"
             data-callback="handleGoogleSignIn"
             data-auto_prompt="false">
        </div>
        <div class="d-flex justify-content-center mb-3">
          <div class="g_id_signin" data-type="standard" data-size="large" data-theme="outline" data-text="continue_with" data-shape="rectangular" data-logo_alignment="left"></div>
        </div>

        <form id="google-login-form" method="post" action="index.php?p=auth_google" class="d-none">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="credential" id="google-credential-input" value="">
          <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">
        </form>

        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
          function handleGoogleSignIn(response) {
            if (!response || !response.credential) {
              return;
            }

            var credentialInput = document.getElementById('google-credential-input');
            var form = document.getElementById('google-login-form');
            if (!credentialInput || !form) {
              return;
            }

            credentialInput.value = response.credential;
            form.submit();
          }
        </script>
      <?php else: ?>
        <div class="alert alert-warning mb-3">
          <div class="fw-semibold mb-1">Google Sign-In is not configured yet.</div>
          <div class="small mb-2">You can use Google login on localhost, but first you need to set up a Google OAuth Client ID.</div>
          <ul class="small mb-0 ps-3">
            <li>Add your Client ID to <span class="fw-semibold">AUTH_GOOGLE_CLIENT_ID</span> in <span class="fw-semibold">config/auth.php</span>.</li>
            <li>In Google Cloud Console, add <span class="fw-semibold">http://localhost:8000</span> to Authorized JavaScript origins.</li>
            <li>If you use another port, add that exact origin too.</li>
          </ul>
        </div>
      <?php endif; ?>

      <p class="mb-0 text-center text-secondary">Don't have an account? <a href="index.php?p=register" class="fw-semibold">Create one</a></p>
    </div>
  </div>
</main>

<?php require '../components/footer.php'; ?>
