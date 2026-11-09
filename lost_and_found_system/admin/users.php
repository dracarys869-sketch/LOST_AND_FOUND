<?php
/**
 * Admin — User Management
 */
$pageTitle  = 'User Management';
$activePage = 'users';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('admin');

$errors  = [];
$success = '';

// ─── Handle POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name    = sanitize($_POST['name']    ?? '');
        $email   = sanitize($_POST['email']   ?? '');
        $role    = $_POST['role']             ?? 'student';
        $sid     = sanitize($_POST['student_id'] ?? '');
        $phone   = sanitize($_POST['phone']   ?? '');
        $pw      = $_POST['password']         ?? '';

        if (strlen($name) < 2)       $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($pw) < 6)         $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['admin','student','security'])) $errors[] = 'Invalid role.';

        if (empty($errors)) {
            if (db()->fetchOne('SELECT id FROM users WHERE email = ?', [$email])) {
                $errors[] = 'Email already exists.';
            } else {
                $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $uid  = (int)db()->insert(
                    'INSERT INTO users (name, email, password, role, student_id, phone) VALUES (?,?,?,?,?,?)',
                    [$name, $email, $hash, $role, $sid ?: null, $phone ?: null]
                );
                auditLog($user['id'], 'CREATE_USER', 'users', $uid);
                setFlash('success', "User '{$name}' created successfully.");
                redirect(BASE_URL . '/admin/users.php');
            }
        }
    }

    if ($action === 'update_status') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($uid && in_array($status, ['active','inactive','suspended'])) {
            db()->execute('UPDATE users SET status = ? WHERE id = ?', [$status, $uid]);
            auditLog($user['id'], 'UPDATE_USER_STATUS', 'users', $uid);
            setFlash('success', 'User status updated.');
        }
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$user['id']) {
            db()->beginTransaction();
            try {
                // Delete releases where this user is released_to or released_by
                db()->execute('DELETE FROM releases WHERE released_to = ? OR released_by = ?', [$uid, $uid]);

                // Delete releases linked to claims owned by this user
                $claims = db()->fetchAll('SELECT id FROM claims WHERE claimant_id = ?', [$uid]);
                foreach ($claims as $c) {
                    db()->execute('DELETE FROM releases WHERE claim_id = ?', [$c['id']]);
                }

                // Delete claims owned by this user
                db()->execute('DELETE FROM claims WHERE claimant_id = ?', [$uid]);

                // Set reviewer of claims/releases to NULL if they were reviewed by this user
                db()->execute('UPDATE claims SET reviewed_by = NULL WHERE reviewed_by = ?', [$uid]);

                // Finally delete the user
                db()->execute('DELETE FROM users WHERE id = ?', [$uid]);

                auditLog($user['id'], 'DELETE_USER', 'users', $uid);
                db()->commit();
                setFlash('success', 'User deleted.');
            } catch (Exception $e) {
                db()->rollBack();
                setFlash('danger', 'Error deleting user: ' . $e->getMessage());
            }
        }
        redirect(BASE_URL . '/admin/users.php');
    }
}

// ─── Fetch Users ──────────────────────────────────────────────
$search    = sanitize($_GET['search'] ?? '');
$roleFilter = sanitize($_GET['role'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));

$sql    = 'SELECT * FROM users WHERE 1=1';
$params = [];
if ($search) {
    $sql .= ' AND (name LIKE ? OR email LIKE ? OR student_id LIKE ?)';
    $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
}
if ($roleFilter) {
    $sql    .= ' AND role = ?';
    $params[] = $roleFilter;
}
$sql .= ' ORDER BY created_at DESC';
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> User Management</div>
    <div class="topbar-actions">
      
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">User Management</h1>
        <p class="page-subtitle">Manage administrators, students, and security staff.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal" id="btnCreateUser">
        <i class="bi bi-person-plus me-1"></i> Add User
      </button>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
      <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="filters-row">
      <form method="GET" class="d-flex align-items-center gap-2 flex-wrap w-100">
        <div class="search-bar flex-grow-1">
          <i class="bi bi-search"></i>
          <input type="search" name="search" class="form-control" aria-label="Search name, email, or student ID" placeholder="Search name, email, ID…"
                 value="<?= e($search) ?>">
        </div>
        <select name="role" class="form-select" aria-label="Filter by role">
          <option value="">All Roles</option>
          <option value="admin"    <?= $roleFilter==='admin'    ? 'selected':'' ?>>Admin</option>
          <option value="student"  <?= $roleFilter==='student'  ? 'selected':'' ?>>Student</option>
          <option value="security" <?= $roleFilter==='security' ? 'selected':'' ?>>Security</option>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
        <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-people"></i> Users
          <span class="badge bg-secondary ms-1"><?= number_format($pager['total']) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards" id="usersTable">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Student ID</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pager['rows'] as $u): ?>
              <tr>
                <td data-label="#" class="text-muted"><?= $u['id'] ?></td>
                <td data-label="Name">
                  <div class="d-flex align-items-center gap-2">
                    <div class="sidebar-avatar icon-sm">
                      <?= userInitials($u['name']) ?>
                    </div>
                    <span class="fw-600"><?= e($u['name']) ?></span>
                    <?php if ((int)$u['id'] === (int)$user['id']): ?>
                    <span class="badge">You</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td data-label="Email"><?= e($u['email']) ?></td>
                <td data-label="Role">
                  <span class="badge"><?= ucfirst($u['role']) ?></span>
                </td>
                <td data-label="Student ID"><?= e($u['student_id'] ?? '—') ?></td>
                <td data-label="Status"><?= statusBadge($u['status']) ?></td>
                <td data-label="Joined" class="text-nowrap"><?= formatDate($u['created_at'], 'M j, Y') ?></td>
                <td data-label="Actions">
                  <div class="d-flex gap-1">
                    <!-- Status change -->
                    <form method="POST" class="d-inline" data-pending-label="Updating status…">
                      <?= csrfField() ?>
                      <input type="hidden" name="action"  value="update_status">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <select name="status" class="form-select form-select-sm w-auto" aria-label="Status for <?= e($u['name']) ?>"
                              onchange="this.form.requestSubmit()">
                        <?php foreach (['active','inactive','suspended'] as $s): ?>
                        <option value="<?= $s ?>" <?= $u['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                    <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                    <form method="POST" class="d-inline"
                          data-confirm="Delete user <?= e($u['name']) ?>?" onsubmit="return confirmDelete(this.dataset.confirm)">
                      <?= csrfField() ?>
                      <input type="hidden" name="action"  value="delete">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2" aria-label="Delete <?= e($u['name']) ?>" data-pending-label="Deleting…">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr><td colspan="8"><div class="empty-state py-3"><i class="bi bi-people"></i><h5>No users found</h5></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($pager['pages'] > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $pager['from'] ?>–<?= $pager['to'] ?> of <?= $pager['total'] ?></small>
        <?= renderPagination($pager) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="create-user-title">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="create-user-title"><i class="bi bi-person-plus"></i> Add user</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="user-name">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="user-name" class="form-control" placeholder="e.g. Juan dela Cruz" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="user-email">Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="user-email" class="form-control" placeholder="user@school.edu" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="user-role">Role <span class="text-danger">*</span></label>
              <select name="role" id="user-role" class="form-select">
                <option value="student">Student</option>
                <option value="security">Security Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label" for="user-student-id">Student ID</label>
              <input type="text" name="student_id" id="user-student-id" class="form-control" placeholder="2024-0001">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="user-phone">Phone</label>
            <input type="tel" name="phone" id="user-phone" class="form-control" placeholder="+63 912 000 0000">
          </div>
          <div class="mb-3">
            <label class="form-label" for="user-password">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" id="user-password" class="form-control" placeholder="Min. 6 characters" required minlength="6" autocomplete="new-password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
