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
                $htmlBody .= '<body style="margin:0;padding:0;background:#f5f5f0;font-family:Arial,sans-serif;">';
                $htmlBody .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f0;padding:40px 0;">';
                $htmlBody .= '<tr><td align="center">';
                $htmlBody .= '<table width="520" cellpadding="0" cellspacing="0" style="width:100%;max-width:520px;background:#fff;border-radius:16px;overflow:hidden;">';
                $htmlBody .= '<tr><td style="background:#0b6b3a;border-bottom:4px solid #d6a532;padding:32px 40px;text-align:center;">';
                $htmlBody .= '<h1 style="margin:0;color:#fff;font-size:22px;font-weight:700;">Password Reset OTP</h1>';
                $htmlBody .= '<p style="margin:8px 0 0;color:#fff;font-size:14px;">' . htmlspecialchars($appName, ENT_QUOTES) . '</p>';
                $htmlBody .= '</td></tr>';
                $htmlBody .= '<tr><td style="padding:36px 40px;">';
                $htmlBody .= '<p style="margin:0 0 16px;color:#374151;font-size:15px;">Hello, <strong>' . $userName . '</strong>!</p>';
                $htmlBody .= '<p style="margin:0 0 24px;color:#6b7280;font-size:14px;line-height:1.6;">Use the One-Time PIN below to reset your password. It expires in <strong>10 minutes</strong>.</p>';
                $htmlBody .= '<div style="text-align:center;margin:0 0 28px;">';
                $htmlBody .= '<div style="display:inline-block;background:#eaf5ee;border-radius:8px;padding:16px 24px;">';
                $htmlBody .= '<span style="font-size:40px;font-weight:700;color:#0b6b3a;letter-spacing:8px;font-variant-numeric:tabular-nums;">' . $otp . '</span>';
                $htmlBody .= '</div></div>';
                $htmlBody .= '<p style="margin:0;color:#536158;font-size:14px;text-align:center;">If you did not request this, please ignore this email.</p>';
                $htmlBody .= '</td></tr>';
                $htmlBody .= '<tr><td style="background:#f9fafb;padding:20px 40px;border-top:1px solid #e5e7eb;text-align:center;">';
                $htmlBody .= '<p style="margin:0;color:#536158;font-size:12px;">&copy; ' . htmlspecialchars($appName, ENT_QUOTES) . '. Do not reply to this email.</p>';
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
        } elseif (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters long.';
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
<body>
<a class="skip-link" href="#main-content">Skip to password reset</a>
<div class="auth-page">
  <header><a href="<?= BASE_URL ?>/" class="auth-brand"><img src="<?= ASSETS_URL ?>/images/lost-found-logo.png" alt="" width="40" height="40"><span><strong>Campus Lost &amp; Found</strong><small><?= e(getSetting('school_name', APP_NAME)) ?></small></span></a></header>
  <main class="auth-single" id="main-content" tabindex="-1">
    <section class="auth-card" aria-labelledby="auth-title">
          <a href="<?= BASE_URL ?>/" class="auth-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to item reports</a>
          <div class="auth-heading">
            <h1 class="auth-form-title" id="auth-title"><?= $step === 1 ? 'Reset your password' : 'Set a new password' ?></h1>
            <p class="auth-form-sub"><?= $step === 1 ? 'Enter your registered email to receive a one-time reset code.' : 'Enter the six-digit code from your email and choose a new password.' ?></p>
          </div>

          <?php if ($error): ?>
          <div class="alert alert-danger mb-3" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i> <?= e($error) ?>
          </div>
          <?php endif; ?>

          <?php if ($success): ?>
          <div class="alert alert-success mb-3" role="status">
            <i class="bi bi-check-circle me-2"></i> <?= e($success) ?>
          </div>
          <?php endif; ?>

          <?php if ($devOtp): ?>
          <div class="alert alert-warning mb-3 text-center">
            <div>Test OTP code</div>
            <div class="otp-value"><?= e($devOtp) ?></div>
          </div>
          <?php endif; ?>



          <?php if ($step === 1): ?>
          <!-- Step 1: Request OTP Form -->
          <form method="POST" action="" data-pending-label="Sending reset code…">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="request_otp">

            <div class="mb-3">
              <label for="email" class="form-label fw-600">Registered Email Address</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-envelope"></i></span>
                <input type="email" id="email" name="email"
                       class="form-control border-start-0" placeholder="you@school.edu"
                       value="<?= e($_POST['email'] ?? $_SESSION['reset_email'] ?? '') ?>" required autocomplete="email">
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3 fw-600">
              Send reset code <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
          </form>

          <?php else: ?>
          <!-- Step 2: Verify OTP and Reset Form -->
           <form method="POST" action="" novalidate data-pending-label="Resetting password…">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password">

            <div class="mb-3">
              <label for="otp" class="form-label fw-600">6-Digit OTP Code</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-shield-lock"></i></span>
                <input type="text" id="otp" name="otp"
                       class="form-control border-start-0" placeholder="000000"
                        maxlength="6" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}">
              </div>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label fw-600">New Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" id="password" name="password"
                        class="form-control border-start-0" placeholder="At least 8 characters" required minlength="8" autocomplete="new-password">
                <button type="button" class="btn btn-outline-secondary" data-password-toggle="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
              </div>
            </div>

            <div class="mb-3">
              <label for="confirm_password" class="form-label fw-600">Confirm New Password</label>
              <div class="input-group">
                <span class="input-group-text border-end-0"><i class="bi bi-check-lg"></i></span>
                <input type="password" id="confirm_password" name="confirm_password"
                        class="form-control border-start-0" placeholder="Re-type your password" required autocomplete="new-password" data-match="password">
              </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 mb-3">
              <i class="bi bi-check-circle me-2"></i> Reset Password
            </button>
          </form>
          <?php endif; ?>

          <div class="auth-footnote">
            <a href="<?= BASE_URL ?>/login.php" class="text-link">
              <i class="bi bi-arrow-left me-1"></i> Back to Sign In
            </a>
          </div>
    </section>
  </main>
</div>
<script src="<?= ASSETS_URL ?>/js/main.js?v=<?= filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
</body>
</html>
