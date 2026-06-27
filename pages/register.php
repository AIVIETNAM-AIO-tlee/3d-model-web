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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authVerifyCSRF($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid request token. Please refresh and try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($password !== $confirmPassword) {
            $error = 'Password confirmation does not match.';
        } else {
            try {
                $pdo = authGetPDO();
                $result = authRegisterLocalUser(
                    $pdo,
                    (string) ($_POST['full_name'] ?? ''),
                    (string) ($_POST['email'] ?? ''),
                    $password
                );

                if ($result['ok']) {
                    authLoginUser((int) $result['user_id']);
                    $_SESSION['auth_success'] = 'Your account has been created successfully.';
                    authRedirect('index.php?p=' . rawurlencode($next));
                }

                $error = $result['message'] ?? 'Registration failed.';
            } catch (Throwable $e) {
                error_log('Register page error: ' . $e->getMessage());
                $error = 'Unable to register right now. Please try again later.';
            }
        }
    }
}
?>
<?php require '../components/navBar.php'; ?>

<main class="container py-4 py-lg-5" style="max-width: 620px;">
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-4 p-lg-5">
      <p class="text-uppercase small fw-bold text-secondary mb-2">Account</p>
      <h1 class="h3 mb-2">Create your account</h1>
      <p class="text-secondary mb-4">Sign up to manage purchases and save your preferences.</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="post" action="index.php?p=register&amp;next=<?php echo rawurlencode($next); ?>" class="d-grid gap-3" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(authCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">

        <div>
          <label for="register-name" class="form-label">Full name</label>
          <input id="register-name" name="full_name" type="text" class="form-control" required autocomplete="name" value="<?php echo htmlspecialchars((string) ($_POST['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div>
          <label for="register-email" class="form-label">Email</label>
          <input id="register-email" name="email" type="email" class="form-control" required autocomplete="email" value="<?php echo htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label for="register-password" class="form-label">Password</label>
            <input id="register-password" name="password" type="password" class="form-control" required minlength="8" autocomplete="new-password">
          </div>
          <div class="col-12 col-md-6">
            <label for="register-password-confirm" class="form-label">Confirm password</label>
            <input id="register-password-confirm" name="confirm_password" type="password" class="form-control" required minlength="8" autocomplete="new-password">
          </div>
        </div>

        <button type="submit" class="btn btn-dark w-100">Create account</button>
      </form>

      <div class="position-relative text-center my-4">
        <span class="px-2 bg-white text-secondary small">or</span>
      </div>

      <?php if (AUTH_GOOGLE_CLIENT_ID !== ''): ?>
        <div id="g_id_onload"
             data-client_id="<?php echo htmlspecialchars(AUTH_GOOGLE_CLIENT_ID, ENT_QUOTES, 'UTF-8'); ?>"
             data-context="signup"
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
          <div class="small mb-0">Set your Client ID in <span class="fw-semibold">config/auth.php</span> to enable this option.</div>
        </div>
      <?php endif; ?>

      <p class="mb-0 mt-3 text-center text-secondary">Already have an account? <a href="index.php?p=login" class="fw-semibold">Sign in</a></p>
    </div>
  </div>
</main>

<?php require '../components/footer.php'; ?>
