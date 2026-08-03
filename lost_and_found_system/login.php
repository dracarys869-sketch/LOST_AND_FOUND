<?php
/**
 * Login Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in?
if (isLoggedIn()) { redirect(dashboardUrl()); }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $remember = !empty($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $user = db()->fetchOne(
            'SELECT * FROM users WHERE email = ? LIMIT 1',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Invalid email or password.';
            auditLog(null, 'LOGIN_FAILED', 'users', null, null, ['email' => $email]);
        } elseif ($user['status'] !== 'active') {
            $error = 'Your account is not active. Please contact the administrator.';
        } else {
            loginUser($user);

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                db()->execute('UPDATE users SET remember_token = ? WHERE id = ?', [$token, $user['id']]);
                setcookie('remember_token', $token, time() + (30 * 86400), '/', '', false, true);
            }

            $redirect = $_GET['redirect'] ?? dashboardUrl();
            redirect($redirect);
        }
    }
}

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Sign In — <?= e(getSetting('school_name', APP_NAME)) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body><div class="modern-auth-wrapper">
  <!-- Glowing abstract background blobs -->
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="container" style="position: relative; z-index: 10;">
    <div class="row align-items-center justify-content-center g-5">
      <!-- Left side info (visible on large screens) -->
      <div class="col-lg-6 d-none d-lg-block text-lg-start auth-info-col">
        <div class="auth-info-content">
          <div class="auth-logo-icon animate-float">
            <i class="bi bi-search-heart text-primary"></i>
          </div>
          <h2 class="fw-800 text-primary mb-2 auth-info-title"><?= e(getSetting('school_name', 'School')) ?></h2>
          <p class="auth-info-desc">Lost &amp; Found Portal — A modern, automated system to easily report, match, and reclaim lost items on campus.</p>

          <div class="d-flex flex-column gap-3">
            <?php foreach ([
                ['bi-link-45deg','Smart Matching','Auto-pairs lost & found items'],
                ['bi-qr-code-scan','QR Tracking','Every item gets a scannable code'],
                ['bi-bell','Notifications','Real-time status updates'],
            ] as [$ic,$tl,$ds]): ?>
              <div class="d-flex align-items-center gap-3 auth-feature-item">
                <div class="auth-feature-icon-wrapper">
                  <i class="bi <?= $ic ?> text-primary" style="font-size: 1.25rem;"></i>
                </div>
                <div>
                  <div class="fw-700 text-primary" style="font-size: 0.88rem;"><?= $tl ?></div>
                  <div style="font-size: 0.75rem; color: var(--text-muted);"><?= $ds ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Right side card -->
      <div class="col-lg-5 col-md-8 col-sm-10 d-flex justify-content-center">
        <div class="card border-0 shadow-lg p-4 p-md-5 auth-card" style="width: 100%;">
          <div class="mb-4 d-flex align-items-center justify-content-between" style="position: relative;">
            <a href="<?= BASE_URL ?>/" class="btn btn-icon text-primary bg-transparent opacity-75 hover-opacity-100 p-0 auth-back-btn" 
               title="Back to Public Feed">
              <i class="bi bi-arrow-left-circle"></i>
            </a>
            <div class="w-100 text-center">
              <h1 class="auth-form-title fw-800 text-primary" style="font-size: 1.6rem; margin-bottom: 0.25rem; margin-top: 0.25rem;">Welcome Back</h1>
              <p class="text-muted-sm mb-0" style="color: var(--text-muted) !important; font-size: 0.85rem;">Sign in to your portal to continue</p>
            </div>
          </div>

          <?php if ($timeout): ?>
          <div class="alert alert-warning border-0 bg-warning bg-opacity-10 text-warning mb-3" style="font-size:0.8rem; border-radius: 8px;">
            <i class="bi bi-clock me-2"></i> Your session expired. Please sign in again.
          </div>
          <?php endif; ?>

          <?php if ($error): ?>
          <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-3" style="font-size:0.8rem; border-radius: 8px;">
            <i class="bi bi-exclamation-circle me-2"></i> <?= e($error) ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate id="loginForm">
            <?= csrfField() ?>

            <div class="mb-3">
              <label for="email" class="form-label fw-600">Email Address</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control border-start-0" placeholder="you@school.edu"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       required autocomplete="email">
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-600">Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control border-start-0 border-end-0" placeholder="Enter your password"
                       required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary border-start-0 bg-transparent text-muted" id="togglePwd"
                        data-bs-toggle="tooltip" title="Show/hide password" style="border: 1.5px solid var(--border); border-left: none !important;">
                  <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
              </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-4">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                <label class="form-check-label" for="remember_me" style="font-size:.82rem; color: var(--text-muted);">Remember me</label>
              </div>
              <a href="<?= BASE_URL ?>/forgot_password.php" class="text-decoration-none fw-600" style="font-size:.82rem; color: var(--primary);">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 mb-3 fw-600" id="loginBtn">
              <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
            </button>
          </form>

          <?php if (getSetting('allow_registration', true)): ?>
          <p class="text-center mb-0" style="font-size:.85rem;color: var(--text-muted);">
            Don't have an account?
            <a href="<?= BASE_URL ?>/register.php" class="fw-600 text-decoration-none">Register as Student</a>
          </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show/hide password
document.getElementById('togglePwd')?.addEventListener('click', function() {
    const pw  = document.getElementById('password');
    const eye = document.getElementById('eyeIcon');
    if (pw.type === 'password') { pw.type = 'text';     eye.className = 'bi bi-eye-slash'; }
    else                        { pw.type = 'password'; eye.className = 'bi bi-eye'; }
});

// Tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>
</body>
</html>
