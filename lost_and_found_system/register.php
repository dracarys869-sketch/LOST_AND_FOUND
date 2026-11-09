<?php
/**
 * Register Page (Student self-registration)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { redirect(dashboardUrl()); }
$registrationEnabled = getSetting('allow_registration', true);

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registrationEnabled) {
    verifyCsrf();

    $values = [
        'name'       => sanitize($_POST['name']       ?? ''),
        'email'      => sanitize($_POST['email']      ?? ''),
        'student_id' => sanitize($_POST['student_id'] ?? ''),
        'phone'      => sanitize($_POST['phone']      ?? ''),
        'password'   => $_POST['password']             ?? '',
        'confirm'    => $_POST['confirm_password']     ?? '',
    ];

    // Validation
    if (strlen($values['name']) < 3)            $errors[] = 'Full name must be at least 3 characters.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($values['password']) < 8)        $errors[] = 'Password must be at least 8 characters.';
    if ($values['password'] !== $values['confirm']) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $exists = db()->fetchOne('SELECT id FROM users WHERE email = ?', [$values['email']]);
        if ($exists) $errors[] = 'An account with this email already exists.';
    }

    if (empty($errors)) {
        $hash = password_hash($values['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
        $uid  = (int)db()->insert(
            'INSERT INTO users (name, email, password, role, student_id, phone, status)
             VALUES (?, ?, ?, "student", ?, ?, "active")',
            [$values['name'], $values['email'], $hash, $values['student_id'] ?: null, $values['phone'] ?: null]
        );
        auditLog($uid, 'REGISTER', 'users', $uid);
        setFlash('success', 'Registration successful! You can now sign in.');
        redirect(BASE_URL . '/login.php');
    }
}

$isDark = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Registration — <?= e(getSetting('school_name', APP_NAME)) ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/register.css?v=<?= filemtime(BASE_PATH . '/assets/css/register.css') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to registration</a>
<div class="auth-page register-page">
  <header><a href="<?= BASE_URL ?>/" class="auth-brand"><img src="<?= ASSETS_URL ?>/images/lost-found-logo.png" alt="" width="40" height="40"><span><strong>Campus Lost &amp; Found</strong><small><?= e(getSetting('school_name', APP_NAME)) ?></small></span></a></header>
  <main class="auth-shell" id="main-content" tabindex="-1">
    <aside class="auth-info">
      <span class="eyebrow">LOOKING OUT FOR EACH OTHER</span>
      <h2>Your campus. Your things. Connected.</h2>
      <p>Join your campus lost and found to report an item, follow updates, and help someone else's belongings find their way home.</p>
      <ol class="auth-steps">
        <li><span><strong>One place for your reports</strong>Keep every lost and found report organized.</span></li>
        <li><span><strong>Updates that matter</strong>Follow possible matches and ownership reviews.</span></li>
        <li><span><strong>A community that helps</strong>Small acts of care make a big difference.</span></li>
      </ol>
    </aside>
    <section class="auth-card" aria-labelledby="auth-title">
      <a href="<?= BASE_URL ?>/" class="auth-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to item reports</a>
      <div class="auth-heading">
        <h1 class="auth-form-title" id="auth-title"><?= $registrationEnabled ? 'Create your account' : 'Registration is paused' ?></h1>
        <p class="auth-form-sub"><?= $registrationEnabled ? 'For students. Fields marked * are required.' : 'Contact your campus administrator for help creating an account.' ?></p>
      </div>

      <?php if ($errors): ?>
      <div class="alert alert-danger mb-3" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if ($registrationEnabled): ?>
      <form method="POST" action="" novalidate data-pending-label="Creating your account…">
        <?= csrfField() ?>

        <div class="mb-3">
          <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="e.g. Juan dela Cruz"
                   value="<?= e($values['name'] ?? '') ?>" required minlength="3" maxlength="100" autocomplete="name">
          </div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">School Email <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="yourname@school.edu"
                   value="<?= e($values['email'] ?? '') ?>" required maxlength="150" autocomplete="email" autocapitalize="none" spellcheck="false">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label for="student_id" class="form-label">Student ID</label>
            <input type="text" id="student_id" name="student_id" class="form-control"
                   placeholder="2024-0001"
                   value="<?= e($values['student_id'] ?? '') ?>">
          </div>
          <div class="col-sm-6">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   placeholder="+63 912 000 0000"
                   value="<?= e($values['phone'] ?? '') ?>" autocomplete="tel">
          </div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="At least 8 characters" required minlength="8" autocomplete="new-password" data-strength-meter="strengthBar" aria-describedby="strengthBar">
            <button type="button" class="btn btn-outline-secondary" id="togglePwd" data-password-toggle="password" aria-controls="password" aria-label="Show password" aria-pressed="false">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
          <div class="password-strength mt-1" id="strengthBar" aria-live="polite"></div>
        </div>

        <div class="mb-4">
          <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="confirm_password" name="confirm_password"
                   class="form-control" placeholder="Re-enter password" required autocomplete="new-password" data-match="password">
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          Create account <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
      </form>
      <?php endif; ?>

      <p class="auth-footnote">
        Already have an account? <a href="<?= BASE_URL ?>/login.php" class="fw-600">Sign in</a>
      </p>
    </section>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/main.js?v=<?= filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
</body>
</html>
