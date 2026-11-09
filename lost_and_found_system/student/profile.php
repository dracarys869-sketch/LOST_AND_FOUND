<?php
/**
 * Student — Profile
 */
$pageTitle  = 'My Profile';
$activePage = 'profile';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('student', 'admin', 'security');

$uid    = $user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = sanitize($_POST['name']       ?? '');
        $sid   = sanitize($_POST['student_id'] ?? '');
        $phone = sanitize($_POST['phone']      ?? '');

        if (strlen($name) < 2) $errors[] = 'Name is required.';

        if (empty($errors)) {
            // Profile photo
            $photoPath = $user['profile_photo'];
            if (!empty($_FILES['profile_photo']['name'])) {
                $newPath = uploadFile($_FILES['profile_photo'], 'profiles');
                if ($newPath) $photoPath = $newPath;
            }

            db()->execute(
                'UPDATE users SET name=?,student_id=?,phone=?,profile_photo=? WHERE id=?',
                [$name, $sid ?: null, $phone ?: null, $photoPath, $uid]
            );
            $_SESSION['name'] = $name;
            auditLog($uid, 'UPDATE_PROFILE', 'users', $uid);
            setFlash('success', 'Profile updated successfully.');
            redirect(BASE_URL . '/student/profile.php');
        }
    }

    if ($action === 'change_password') {
        $current  = $_POST['current_password']  ?? '';
        $newPw    = $_POST['new_password']       ?? '';
        $confirm  = $_POST['confirm_password']   ?? '';

        $row = db()->fetchOne('SELECT password FROM users WHERE id=?',[$uid]);
        if (!password_verify($current, $row['password'])) { $errors[] = 'Current password is incorrect.'; }
        if (strlen($newPw) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($newPw !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            db()->execute('UPDATE users SET password=? WHERE id=?', [$hash, $uid]);
            auditLog($uid, 'CHANGE_PASSWORD', 'users', $uid);
            setFlash('success', 'Password changed successfully.');
            redirect(BASE_URL . '/student/profile.php');
        }
    }
}

// Re-fetch fresh user data
$profileUser = db()->fetchOne('SELECT * FROM users WHERE id=?', [$uid]);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Account /</span> My Profile</div>
    <div class="topbar-actions">
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">My Profile</h1><p class="page-subtitle">Manage your account information.</p></div>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
      <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">
      <!-- Profile Card -->
      <div class="col-lg-4">
        <div class="card text-center">
          <div class="card-body py-4">
            <?php if ($profileUser['profile_photo']): ?>
            <img src="<?= photoUrl($profileUser['profile_photo']) ?>" alt="Your profile photo" class="profile-avatar mb-3">
            <?php else: ?>
            <div class="sidebar-avatar profile-avatar mx-auto mb-3">
              <?= userInitials($profileUser['name']) ?>
            </div>
            <?php endif; ?>
            <h5 class="fw-700 mb-1"><?= e($profileUser['name']) ?></h5>
            <p class="text-muted-sm"><?= e($profileUser['email']) ?></p>
            <span class="badge"><?= ucfirst($profileUser['role']) ?></span>
            <div class="mt-3 text-muted-sm numeric">
              <?php if ($profileUser['student_id']): ?>
              <div><i class="bi bi-person-badge me-1"></i><?= e($profileUser['student_id']) ?></div>
              <?php endif; ?>
              <?php if ($profileUser['phone']): ?>
              <div><i class="bi bi-phone me-1"></i><?= e($profileUser['phone']) ?></div>
              <?php endif; ?>
              <div><i class="bi bi-calendar me-1"></i>Joined <?= formatDate($profileUser['created_at'], 'M Y') ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile -->
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-person-gear"></i> Edit Profile</span></div>
          <form method="POST" enctype="multipart/form-data" data-pending-label="Saving profile…">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="profile-name">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" id="profile-name" class="form-control" required value="<?= e($profileUser['name']) ?>" autocomplete="name">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="student-id">Student ID</label>
                  <input type="text" name="student_id" id="student-id" class="form-control" value="<?= e($profileUser['student_id'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="profile-phone">Phone</label>
                  <input type="tel" name="phone" id="profile-phone" class="form-control" value="<?= e($profileUser['phone'] ?? '') ?>" autocomplete="tel">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="profile-photo">Profile Photo</label>
                <input type="file" name="profile_photo" id="profile-photo" class="form-control" accept="image/*">
              </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
              <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Changes</button>
            </div>
          </form>
        </div>

        <!-- Change Password -->
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="bi bi-shield-lock"></i> Change Password</span></div>
          <form method="POST" data-pending-label="Changing password…">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="current-password">Current Password</label>
                <input type="password" name="current_password" id="current-password" class="form-control" required autocomplete="current-password">
              </div>
              <div class="mb-3">
                <label class="form-label" for="new-password">New Password</label>
                <input type="password" name="new_password" id="new-password" class="form-control" required minlength="8" autocomplete="new-password">
              </div>
              <div class="mb-3">
                <label class="form-label" for="confirm-password">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm-password" class="form-control" required autocomplete="new-password">
              </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
              <button type="submit" class="btn btn-outline-primary"><i class="bi bi-lock me-1"></i>Change Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
