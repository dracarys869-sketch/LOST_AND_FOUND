<?php
/**
 * Register Page (Student self-registration)
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { redirect(dashboardUrl()); }
if (!getSetting('allow_registration', true)) {
    die('Registration is currently disabled. Please contact the administrator.');
}

$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
</head>
<body>
<div class="auth-wrapper">

  <div class="auth-left d-none d-lg-flex">
    <div class="auth-illustration">
      <div class="auth-logo animate-float"><i class="bi bi-person-plus"></i></div>
      <div class="auth-tagline">Join the Portal</div>
      <p class="auth-desc">Create your student account to report lost items, track found items, and get notified when your belongings are located.</p>
    </div>
  </div>

  <div class="auth-right" style="width:460px;">
    <div style="width:100%;max-width:400px;">
      <div class="mb-4 d-flex align-items-center justify-content-between" style="position: relative;">
        <a href="<?= BASE_URL ?>/" class="btn btn-icon text-muted bg-transparent opacity-75 hover-opacity-100 p-0" 
           style="font-size: 1.5rem; position: absolute; left: 0; top: 50%; transform: translateY(-50%); z-index: 20;"
           title="Back to Public Feed">
          <i class="bi bi-arrow-left-circle"></i>
        </a>
        <div class="w-100 text-center">
          <h1 class="auth-form-title" style="margin-bottom: 0.15rem; margin-top: 0.25rem;">Create Account</h1>
          <p class="auth-form-sub mb-0">Student self-registration</p>
        </div>
      </div>

      <?php if ($errors): ?>
      <div class="alert alert-danger mb-3">
        <i class="bi bi-exclamation-circle me-2"></i>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
          <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" id="name" name="name" class="form-control"
                   placeholder="e.g. Juan dela Cruz"
                   value="<?= e($values['name'] ?? '') ?>" required>
          </div>
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">School Email <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control"
                   placeholder="yourname@school.edu"
                   value="<?= e($values['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <label for="student_id" class="form-label">Student ID</label>
            <input type="text" id="student_id" name="student_id" class="form-control"
                   placeholder="2024-0001"
                   value="<?= e($values['student_id'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label for="phone" class="form-label">Phone Number</label>
            <input type="tel" id="phone" name="phone" class="form-control"
                   placeholder="+63 912 000 0000"
                   value="<?= e($values['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="mb-3">
          <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="At least 8 characters" required minlength="8">
            <button type="button" class="btn btn-outline-secondary" id="togglePwd">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
          <div class="password-strength mt-1" id="strengthBar"></div>
        </div>

        <div class="mb-4">
          <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" id="confirm_password" name="confirm_password"
                   class="form-control" placeholder="Re-enter password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
          <i class="bi bi-person-check me-2"></i> Create Account
        </button>
      </form>

      <p class="text-center" style="font-size:.85rem;color:var(--text-muted);">
        Already have an account? <a href="<?= BASE_URL ?>/login.php" class="fw-600">Sign in</a>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd')?.addEventListener('click', function() {
    const pw = document.getElementById('password');
    const ic = document.getElementById('eyeIcon');
    if (pw.type === 'password') { pw.type = 'text'; ic.className = 'bi bi-eye-slash'; }
    else { pw.type = 'password'; ic.className = 'bi bi-eye'; }
});

// Password strength
document.getElementById('password')?.addEventListener('input', function() {
    const bar = document.getElementById('strengthBar');
    const v   = this.value;
    let s = 0;
    if (v.length >= 8)  s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const labels = ['','Weak','Fair','Good','Strong'];
    const colors = ['','#dc3545','#ffc107','#0dcaf0','#198754'];
    bar.innerHTML = s ? `<div style="height:4px;background:${colors[s]};border-radius:2px;width:${s*25}%;transition:width .3s;"></div><small style="color:${colors[s]};font-size:.7rem;">${labels[s]}</small>` : '';
});
</script>
</body>
</html>
