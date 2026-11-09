<?php
/**
 * Login Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in?
if (isLoggedIn()) { redirect(safeLoginRedirect($_GET['redirect'] ?? null, currentUser()['role'])); }

$error = '';

if (!isset($_SESSION['login_captcha_a'], $_SESSION['login_captcha_b'])) {
    $_SESSION['login_captcha_a'] = random_int(1, 9);
    $_SESSION['login_captcha_b'] = random_int(1, 9);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password']          ?? '';
    $captcha  = trim((string)($_POST['captcha'] ?? ''));
    $remember = !empty($_POST['remember_me']);

    $captchaExpected = (int)$_SESSION['login_captcha_a'] + (int)$_SESSION['login_captcha_b'];
    $captchaValid = $captcha !== '' && ctype_digit($captcha) && (int)$captcha === $captchaExpected;
    unset($_SESSION['login_captcha_a'], $_SESSION['login_captcha_b']);

    if (!$captchaValid) {
        $error = 'Please solve the arithmetic CAPTCHA correctly.';
    } elseif (empty($email) || empty($password)) {
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
                rememberUser((int)$user['id']);
            } else {
                forgetRememberedUser((int)$user['id']);
            }

            $redirect = safeLoginRedirect($_GET['redirect'] ?? null, $user['role']);
            redirect($redirect);
        }
    }

    if (!isLoggedIn()) {
        $_SESSION['login_captcha_a'] = random_int(1, 9);
        $_SESSION['login_captcha_b'] = random_int(1, 9);
    }
}

$timeout = isset($_GET['timeout']);
$isDark  = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
$captchaQuestion = (int)$_SESSION['login_captcha_a'] . ' + ' . (int)$_SESSION['login_captcha_b'];
$schoolName = getSetting('school_name', APP_NAME);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Sign In — <?= e(getSetting('school_name', APP_NAME)) ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to sign in</a>
<div class="auth-page">
  <header><a href="<?= BASE_URL ?>/" class="auth-brand"><img src="<?= ASSETS_URL ?>/images/lost-found-logo.png" alt="" width="40" height="40"><span class="auth-brand-login"><strong>Campus Lost &amp; Found</strong><small><?= e($schoolName) ?></small></span></a></header>
  <main class="auth-shell" id="main-content" tabindex="-1">
    <aside class="auth-info" aria-label="How the portal works">
      <span class="eyebrow">A LITTLE HELP FROM YOUR CAMPUS</span>
      <h2>Good things find their way back.</h2>
      <p>Sign in to keep track of your reports and get one step closer to finding what's missing.</p>
      <ol class="auth-steps">
        <li><span><strong>Tell us what went missing</strong>A few details help your item stand out.</span></li>
        <li><span><strong>Keep an eye on your matches</strong>Get updates when a similar item is found.</span></li>
        <li><span><strong>Make it a happy return</strong>Verify ownership and collect your item.</span></li>
      </ol>
    </aside>
    <section class="auth-card" aria-labelledby="auth-title">
          <a href="<?= BASE_URL ?>/" class="auth-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to item reports</a>
          <div class="auth-heading">
            <h1 class="auth-form-title" id="auth-title">Welcome back</h1>
            <p class="auth-form-sub">Sign in with your student or staff account.</p>
          </div>

          <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
          <?php endif; ?>

          <?php if ($timeout): ?>
          <div class="alert alert-warning mb-3" role="status">
            <i class="bi bi-clock me-2"></i> Your session expired. Please sign in again.
          </div>
          <?php endif; ?>

          <?php if ($error): ?>
          <div class="alert alert-danger mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i> <?= e($error) ?>
          </div>
          <?php endif; ?>

          <form method="POST" action="" novalidate id="loginForm" data-pending-label="Signing in…">
            <?= csrfField() ?>

            <div class="mb-3">
              <label for="email" class="form-label fw-600">Email Address</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control border-start-0" placeholder="you@school.edu"
                       value="<?= e($_POST['email'] ?? '') ?>"
                       required autocomplete="username" maxlength="150" autocapitalize="none" spellcheck="false">
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-600">Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control border-start-0 border-end-0" placeholder="Enter your password"
                       required autocomplete="current-password">
                <button type="button" class="btn btn-outline-secondary border-start-0" id="togglePwd"
                        data-password-toggle="password" aria-controls="password" aria-label="Show password" aria-pressed="false">
                  <i class="bi bi-eye" id="eyeIcon"></i>
                </button>
              </div>
            </div>

            <div class="mb-3">
              <label for="captcha" class="form-label fw-600">Security Check</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-calculator"></i></span>
                <span class="input-group-text captcha-question" id="captchaQuestion"><?= e($captchaQuestion) ?> =</span>
                <input type="text" id="captcha" name="captcha"
                       class="form-control border-start-0" placeholder="Answer"
                       inputmode="numeric" pattern="[0-9]+" maxlength="2" required autocomplete="off" aria-describedby="captchaQuestion">
              </div>
            </div>

            <div class="auth-options">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me" <?= !empty($_POST['remember_me']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="remember_me">Remember me for 30 days</label>
              </div>
              <a href="<?= BASE_URL ?>/forgot_password.php" class="text-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100" id="loginBtn">
              Sign in <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
          </form>

          <?php if (getSetting('allow_registration', true)): ?>
          <p class="auth-footnote">
            Don't have an account?
            <a href="<?= BASE_URL ?>/register.php">Create a student account</a>
          </p>
          <?php endif; ?>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/main.js?v=<?= filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
</body>
</html>
