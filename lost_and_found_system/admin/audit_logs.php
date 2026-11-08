<?php
/**
 * Admin — Audit Logs
 */
$pageTitle  = 'Audit Logs';
$activePage = 'audit';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('admin');

$search = sanitize($_GET['search'] ?? '');
$action = sanitize($_GET['action'] ?? '');
$table = sanitize($_GET['table'] ?? '');
$userId = max(0, (int)($_GET['user_id'] ?? 0));
$recordId = max(0, (int)($_GET['record_id'] ?? 0));
$page   = max(1, (int)($_GET['page'] ?? 1));

$sql    = 'SELECT al.*, u.name AS user_name, u.role AS user_role FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id WHERE 1=1';
$params = [];
if ($search) { $sql .= ' AND (al.action LIKE ? OR u.name LIKE ? OR al.table_name LIKE ?)'; $params = ["%$search%","%$search%","%$search%"]; }
if ($action) { $sql .= ' AND al.action = ?'; $params[] = $action; }
if ($table) { $sql .= ' AND al.table_name = ?'; $params[] = $table; }
if ($userId) { $sql .= ' AND al.user_id = ?'; $params[] = $userId; }
if ($recordId) { $sql .= ' AND al.record_id = ?'; $params[] = $recordId; }
$sql .= ' ORDER BY al.created_at DESC';
$pager = paginate($sql, $params, $page);
$filterActions = db()->fetchAll('SELECT DISTINCT action FROM audit_logs ORDER BY action');
$filterTables = db()->fetchAll('SELECT DISTINCT table_name FROM audit_logs WHERE table_name IS NOT NULL ORDER BY table_name');
$filterUsers = db()->fetchAll('SELECT id,name,role FROM users ORDER BY name');

$actionIcons = [
    'LOGIN'               => ['bi-box-arrow-in-right','text-success'],
    'LOGOUT'              => ['bi-box-arrow-left','text-warning'],
    'LOGIN_FAILED'        => ['bi-exclamation-circle','text-danger'],
    'REGISTER'            => ['bi-person-plus','text-primary'],
    'CREATE_USER'         => ['bi-person-plus','text-primary'],
    'DELETE_USER'         => ['bi-person-dash','text-danger'],
    'CREATE_LOST_ITEM'    => ['bi-search','text-warning'],
    'CREATE_FOUND_ITEM'   => ['bi-box-seam','text-success'],
    'UPDATE_LOST_STATUS'  => ['bi-pencil','text-info'],
    'UPDATE_FOUND_STATUS' => ['bi-pencil','text-info'],
    'SYSTEM_MATCH_CREATE' => ['bi-link-45deg','text-primary'],
    'APPROVE_MATCH'       => ['bi-check-circle','text-success'],
    'REJECT_MATCH'        => ['bi-x-circle','text-danger'],
    'APPROVE_CLAIM'       => ['bi-check-circle','text-success'],
    'REJECT_CLAIM'        => ['bi-x-circle','text-danger'],
    'RELEASE_ITEM'        => ['bi-check2-circle','text-success'],
];

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Audit Logs</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Audit Logs</h1><p class="page-subtitle">Full history of all system actions.</p></div>
    </div>

    <div class="filters-row">
      <form method="GET" class="d-flex gap-2 w-100">
        <div class="search-bar flex-grow-1">
          <i class="bi bi-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Search action, user, table…" value="<?= e($search) ?>">
        </div>
        <select name="action" class="form-select form-select-sm" aria-label="Filter by action"><option value="">All actions</option><?php foreach ($filterActions as $row): ?><option value="<?= e($row['action']) ?>" <?= $action === $row['action'] ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $row['action'])) ?></option><?php endforeach; ?></select>
        <select name="table" class="form-select form-select-sm" aria-label="Filter by table"><option value="">All tables</option><?php foreach ($filterTables as $row): ?><option value="<?= e($row['table_name']) ?>" <?= $table === $row['table_name'] ? 'selected' : '' ?>><?= e($row['table_name']) ?></option><?php endforeach; ?></select>
        <select name="user_id" class="form-select form-select-sm" aria-label="Filter by user"><option value="0">All users</option><?php foreach ($filterUsers as $row): ?><option value="<?= (int)$row['id'] ?>" <?= $userId === (int)$row['id'] ? 'selected' : '' ?>><?= e($row['name']) ?> (<?= e($row['role']) ?>)</option><?php endforeach; ?></select>
        <input type="number" name="record_id" class="form-control form-control-sm" min="1" placeholder="Record ID" value="<?= $recordId ?: '' ?>">
        <button type="submit" class="btn btn-outline-primary btn-sm">Search</button>
        <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.82rem;">
            <thead><tr><th>#</th><th>Action</th><th>User</th><th>Table</th><th>Record</th><th>Details</th><th>IP</th><th>Time</th></tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $log): ?>
              <?php [$icon,$iconClass] = $actionIcons[$log['action']] ?? ['bi-dot','text-muted']; ?>
              <tr>
                <td style="font-size:.72rem;"><?= $log['id'] ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi <?= $icon ?> <?= $iconClass ?>"></i>
                    <span class="fw-600"><?= e(str_replace('_',' ',$log['action'])) ?></span>
                  </div>
                </td>
                <td>
                  <?php if ($log['user_name']): ?>
                  <div><?= e($log['user_name']) ?></div>
                  <div style="font-size:.72rem;color:var(--text-muted);"><?= ucfirst($log['user_role'] ?? '') ?></div>
                  <?php else: ?>
                  <span class="text-muted">System</span>
                  <?php endif; ?>
                </td>
                <td><?= e($log['table_name'] ?? '—') ?></td>
                <td><?= $log['record_id'] ? '#'.$log['record_id'] : '—' ?></td>
                <td><?php if ($log['old_values'] || $log['new_values']): ?><details><summary class="small">View</summary><pre class="small mb-0"><?= e(json_encode(['old' => json_decode($log['old_values'] ?? 'null', true), 'new' => json_decode($log['new_values'] ?? 'null', true)], JSON_PRETTY_PRINT)) ?></pre></details><?php else: ?>—<?php endif; ?></td>
                <td style="font-family:monospace;font-size:.72rem;"><?= e($log['ip_address'] ?? '—') ?></td>
                <td style="white-space:nowrap;" title="<?= e($log['created_at']) ?>">
                  <?= timeAgo($log['created_at']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr><td colspan="8"><div class="empty-state py-3"><i class="bi bi-journal-text"></i><h5>No logs found</h5></div></td></tr>
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
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
