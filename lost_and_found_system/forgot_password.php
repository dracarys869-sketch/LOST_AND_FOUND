<?php
/**
 * Forgot / Reset Password with OTP
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

// Already logged in?
if (isLoggedIn()) { redirect(dashboardUrl()); }

$step = 1; // 1: request OTP, 2: verify OTP & change password
$error = '';
$success = '';
$devOtp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'request_otp') {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter your registered email address.';
        } else {
            $user = db()->fetchOne('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

            if (!$user) {
                $error = 'Email address not found in our records.';
            } elseif ($user['status'] !== 'active') {
                $error = 'Your account is inactive. Please contact the administrator.';
            } else {
                // Generate a 6-digit OTP
                $otp     = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                $expires = time() + 600; // 10 minutes

                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_otp']     = $otp;
                $_SESSION['reset_expires'] = $expires;

                // ── Build HTML email ─────────────────────────────────────
                $appName  = getSetting('school_name', APP_NAME);
                $userName = htmlspecialchars($user['name'], ENT_QUOTES);
                $subject  = "Your Password Reset OTP — {$appName}";

                $htmlBody  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>';
                $htmlBody .= '<body style="margin:0;padding:0;background:#f0f4f8;font-family:Arial,sans-serif;">';
                $htmlBody .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f4f8;padding:40px 0;">';
                $htmlBody .= '<tr><td align="center">';
                $htmlBody .= '<table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">';
                $htmlBody .= '<tr><td style="background:linear-gradient(135deg,#1a7a4a,#0d5c36);padding:32px 40px;text-align:center;">';
                $htmlBody .= '<h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;">Password Reset OTP</h1>';
                $htmlBody .= '<p style="margin:6px 0 0;color:#a7d9b8;font-size:13px;">' . htmlspecialchars($appName, ENT_QUOTES) . '</p>';
                $htmlBody .= '</td></tr>';
                $htmlBody .= '<tr><td style="padding:36px 40px;">';
                $htmlBody .= '<p style="margin:0 0 16px;color:#374151;font-size:15px;">Hello, <strong>' . $userName . '</strong>!</p>';
                $htmlBody .= '<p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.6;">Use the One-Time PIN below to reset your password. It expires in <strong>10 minutes</strong>.</p>';
                $htmlBody .= '<div style="text-align:center;margin:0 0 28px;">';
                $htmlBody .= '<div style="display:inline-block;background:#f0fdf4;border:2px dashed #22c55e;border-radius:12px;padding:20px 48px;">';
                $htmlBody .= '<p style="margin:0 0 6px;color:#6b7280;font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:600;">Your OTP Code</p>';
                $htmlBody .= '<span style="font-size:42px;font-weight:800;color:#16a34a;letter-spacing:10px;">' . $otp . '</span>';
                $htmlBody .= '</div></div>';
                $htmlBody .= '<p style="margin:0;color:#9ca3af;font-size:13px;text-align:center;">If you did not request this, please ignore this email.</p>';
                $htmlBody .= '</td></tr>';
                $htmlBody .= '<tr><td style="background:#f9fafb;padding:20px 40px;border-top:1px solid #e5e7eb;text-align:center;">';
                $htmlBody .= '<p style="margin:0;color:#9ca3af;font-size:12px;">&copy; ' . htmlspecialchars($appName, ENT_QUOTES) . '. Do not reply to this email.</p>';
                $htmlBody .= '</td></tr></table></td></tr></table></body></html>';

                // ── Send via PHPMailer SMTP ───────────────────────────────
                $mailSent  = false;
                $mailError = '';
                try {
                    sendMail($email, $user['name'], $subject, $htmlBody);
                    $mailSent = true;
                } catch (\Exception $e) {
                    $mailError = $e->getMessage();
                    error_log("OTP mail failed: {$mailError}");
                }

                if ($mailSent) {
                    $success = 'A One-Time PIN (OTP) has been sent to your registered email address.';
                } else {
                    // Fallback: show OTP on screen when SMTP is not yet configured
                    $_SESSION['dev_otp'] = $otp;
                    $success = 'OTP generated. Email delivery failed — check SMTP settings. Code is shown below for testing.';
                }

                $step = 2;
            }
        }
    } elseif ($action === 'reset_password') {
        $otpInput = trim($_POST['otp'] ?? '');
        $newPass  = $_POST['password'] ?? '';
        $confPass = $_POST['confirm_password'] ?? '';
        $email    = $_SESSION['reset_email'] ?? '';
        $savedOtp = $_SESSION['reset_otp'] ?? '';
        $expires  = $_SESSION['reset_expires'] ?? 0;

        if (empty($email) || empty($savedOtp)) {
            $error = 'Session expired. Please request a new OTP.';
            $step = 1;
        } elseif (empty($otpInput) || empty($newPass) || empty($confPass)) {
            $error = 'Please fill in all fields.';
            $step = 2;
        } elseif ($otpInput !== $savedOtp) {
            $error = 'Invalid OTP code. Please check your email and try again.';
            $step = 2;
        } elseif (time() > $expires) {
            $error = 'OTP has expired. Please request a new one.';
            $step = 1;
        } elseif (strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $step = 2;
        } elseif ($newPass !== $confPass) {
            $error = 'Passwords do not match.';
            $step = 2;
        } else {
            // Update password
            $user = db()->fetchOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
            if ($user) {
                $hashed = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                db()->execute('UPDATE users SET password = ?, remember_token = NULL WHERE id = ?', [$hashed, $user['id']]);

                // Clear reset session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp']);
                unset($_SESSION['reset_expires']);
                unset($_SESSION['dev_otp']);

                auditLog($user['id'], 'PASSWORD_RESET_OTP', 'users', $user['id']);
                setFlash('success', 'Your password has been changed successfully. You can now log in.');
                redirect(BASE_URL . '/login.php');
            } else {
                $error = 'User not found.';
                $step = 1;
            }
        }
    }
}

// Check for developer OTP fallback
if ($step === 2 && !empty($_SESSION['dev_otp'])) {
    $devOtp = $_SESSION['dev_otp'];
}

$isDark = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Reset Password — <?= e(getSetting('school_name', APP_NAME)) ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body><div class="modern-auth-wrapper">
  <div class="blob blob-1"></div>
  <div class="blob blob-2"></div>
  <div class="blob blob-3"></div>

  <div class="container" style="position: relative; z-index: 10;">
    <div class="row align-items-center justify-content-center">
      <div class="col-lg-5 col-md-8 col-sm-10">
        <div class="card border-0 shadow-lg p-4 p-md-5 auth-card" style="width: 100%;">
          <div class="mb-4 d-flex align-items-center justify-content-between" style="position: relative;">
            <a href="<?= BASE_URL ?>/" class="btn btn-icon bg-transparent p-0 auth-back-btn"
               style="font-size: 1.5rem; position: absolute; left: 0; top: 50%; transform: translateY(-50%); z-index: 20;"
               title="Back to Public Feed">
              <i class="bi bi-arrow-left-circle"></i>
            </a>
            <div class="w-100 text-center">
              <h1 class="auth-form-title fw-800" style="font-size: 1.6rem; margin-bottom: 0.25rem; margin-top: 0.25rem; color: var(--text);">Reset Password</h1>
              <p class="text-muted-sm mb-0" style="color: var(--text-muted) !important; font-size: 0.85rem;">
                <?= $step === 1 ? 'Enter email to request One-Time PIN' : 'Verify OTP and set a new password' ?>
              </p>
            </div>
          </div>

          <?php if ($error): ?>
          <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger mb-3" style="font-size:0.8rem; border-radius: 8px;">
            <i class="bi bi-exclamation-circle me-2"></i> <?= e($error) ?>
          </div>
          <?php endif; ?>

          <?php if ($success): ?>
          <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success mb-3" style="font-size:0.8rem; border-radius: 8px;">
            <i class="bi bi-check-circle me-2"></i> <?= e($success) ?>
          </div>
          <?php endif; ?>

          <?php if ($devOtp): ?>
          <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mb-3 text-center" style="border-radius: 10px;">
            <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9A5B00;">Test OTP Code</div>
            <div style="font-size:1.6rem;font-weight:800;letter-spacing:.35em;color:#9A5B00;margin-top:.25rem;"><?= e($devOtp) ?></div>
          </div>
          <?php endif; ?>



          <?php if ($step === 1): ?>
          <!-- Step 1: Request OTP Form -->
          <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="request_otp">

            <div class="mb-3">
              <label for="email" class="form-label fw-600">Registered Email Address</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control border-start-0" placeholder="you@school.edu"
                       value="<?= e($_POST['email'] ?? $_SESSION['reset_email'] ?? '') ?>" required>
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-600">
              <i class="bi bi-envelope-paper me-2"></i> Send OTP Code
            </button>
          </form>

          <?php else: ?>
          <!-- Step 2: Verify OTP and Reset Form -->
          <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">

            <div class="mb-3">
              <label for="otp" class="form-label fw-600">6-Digit OTP Code</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-shield-lock"></i></span>
                <input type="text" id="otp" name="otp"
                       class="form-control border-start-0" placeholder="000000"
                       maxlength="6" required autocomplete="off">
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-600">New Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                       class="form-control border-start-0" placeholder="At least 6 characters" required>
              </div>
            </div>

            <div class="mb-3">
              <label for="confirm_password" class="form-label fw-600">Confirm New Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-check-lg"></i></span>
                <input type="password" id="confirm_password" name="confirm_password"
                       class="form-control border-start-0" placeholder="Re-type your password" required>
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 py-2 mb-3 fw-600">
              <i class="bi bi-check-circle me-2"></i> Reset Password
            </button>
          </form>
          <?php endif; ?>

          <div class="text-center mt-2">
            <a href="<?= BASE_URL ?>/login.php" class="text-decoration-none fw-600" style="font-size: .85rem; color: var(--primary);">
              <i class="bi bi-arrow-left me-1"></i> Back to Sign In
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
