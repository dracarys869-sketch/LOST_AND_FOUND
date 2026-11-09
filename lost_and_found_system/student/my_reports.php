<?php
/**
 * Student — My Reports
 */
$pageTitle  = 'My Reports';
$activePage = 'my_reports';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('student', 'admin');

$type   = ($_GET['type'] ?? '') === 'found' ? 'found' : 'lost';
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

$uid = $user['id'];

if ($type === 'found') {
    $sql    = 'SELECT fi.*, c.name AS cat, l.name AS loc FROM found_items fi
               LEFT JOIN categories c ON fi.category_id=c.id LEFT JOIN locations l ON fi.location_id=l.id
               WHERE fi.user_id=?';
    $params = [$uid];
    if ($search) { $sql .= ' AND fi.item_name LIKE ?'; $params[] = "%$search%"; }
    if ($status) { $sql .= ' AND fi.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY fi.created_at DESC';
} else {
    $sql    = 'SELECT li.*, c.name AS cat, l.name AS loc FROM lost_items li
               LEFT JOIN categories c ON li.category_id=c.id LEFT JOIN locations l ON li.location_id=l.id
               WHERE li.user_id=?';
    $params = [$uid];
    if ($search) { $sql .= ' AND li.item_name LIKE ?'; $params[] = "%$search%"; }
    if ($status) { $sql .= ' AND li.status=?'; $params[] = $status; }
    $sql .= ' ORDER BY li.created_at DESC';
}
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Student /</span> My Reports</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">My reports</h1><p class="page-subtitle">Check an item's status or find a previous report.</p></div>
      <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/student/lost_items.php" class="btn <?= $type === 'found' ? 'btn-outline-secondary' : 'btn-primary' ?>">
          <i class="bi bi-plus me-1"></i> Report Lost
        </a>
        <a href="<?= BASE_URL ?>/student/found_items.php" class="btn <?= $type === 'found' ? 'btn-primary' : 'btn-outline-secondary' ?>">
          <i class="bi bi-plus me-1"></i> Report Found
        </a>
      </div>
    </div>

    <!-- Type Tabs -->
    <ul class="nav nav-pills mb-3">
      <li class="nav-item"><a class="nav-link <?= $type==='lost'?'active':'' ?>" href="?type=lost"><i class="bi bi-search me-1"></i>Lost Items</a></li>
      <li class="nav-item"><a class="nav-link <?= $type==='found'?'active':'' ?>" href="?type=found"><i class="bi bi-box-seam me-1"></i>Found Items</a></li>
    </ul>

    <!-- Filters -->
    <div class="filters-row">
      <form method="GET" class="d-flex gap-2 flex-wrap w-100">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="search-bar flex-grow-1">
          <i class="bi bi-search"></i>
          <input type="search" name="search" class="form-control" aria-label="Search item name" placeholder="Search item name…" value="<?= e($search) ?>">
        </div>
        <select name="status" class="form-select" aria-label="Filter by status">
          <option value="">All Status</option>
          <?php $statuses = $type==='lost' ? ['pending','active','matched','claimed','closed'] : ['pending','active','matched','claimed','released'];
                foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
        <a href="?type=<?= $type ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead>
              <tr>
                <th>Report #</th>
                <th>Item</th>
                <th>Category</th>
                <th>Location</th>
                <th>Date <?= $type==='lost'?'Lost':'Found' ?></th>
                <th>Status</th>
                <?php if ($type==='found'): ?><th>QR</th><?php endif; ?>
                <th>Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pager['rows'] as $item): ?>
              <tr>
                <td data-label="Report #"><code><?= e($item['report_number']) ?></code></td>
                <td data-label="Item">
                  <div class="fw-600"><?= e($item['item_name']) ?></div>
                  <?php if ($item['brand'] || $item['color']): ?>
                  <div class="text-muted-sm">
                    <?= e(implode(' · ', array_filter([$item['brand'] ?? '', $item['color'] ?? '']))) ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td data-label="Category"><?= e($item['cat']) ?></td>
                <td data-label="Location"><?= e($item['loc']) ?></td>
                <td data-label="Date" class="text-nowrap"><?= formatDate($type==='lost'?$item['date_lost']:$item['date_found']) ?></td>
                <td data-label="Status"><?= statusBadge($item['status']) ?></td>
                <?php if ($type==='found'): ?>
                <td data-label="QR">
                  <?php if ($item['qr_code_path']): ?>
                  <a href="<?= BASE_URL.'/qr_codes/'.basename($item['qr_code_path']) ?>" target="_blank"
                     class="btn btn-sm btn-outline-primary py-0 px-2" title="View QR Code">
                    <i class="bi bi-qr-code"></i>
                  </a>
                  <?php else: ?>
                  <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <?php endif; ?>
                <td data-label="Submitted" class="text-nowrap"><?= timeAgo($item['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr>
                <td colspan="<?= $type==='found'?8:7 ?>">
                  <div class="empty-state py-4">
                    <i class="bi bi-inbox"></i>
                    <h3><?= $search !== '' || $status !== '' ? 'No reports match your filters' : 'No ' . $type . ' reports yet' ?></h3>
                    <p><?= $search !== '' || $status !== '' ? 'Try another item name or clear the filters to see your reports.' : 'Your submitted reports and their status will appear here.' ?></p>
                    <a href="<?= $search !== '' || $status !== '' ? '?type=' . $type : BASE_URL . '/student/' . $type . '_items.php' ?>" class="text-link">
                      <?= $search !== '' || $status !== '' ? 'Clear filters' : 'Report ' . ucfirst($type) . ' Item' ?>
                    </a>
                  </div>
                </td>
              </tr>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
