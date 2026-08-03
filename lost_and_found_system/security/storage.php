<?php
/**
 * Security — Storage View
 */
$pageTitle  = 'Storage';
$activePage = 'storage';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('security', 'admin');

$search = sanitize($_GET['search'] ?? '');
$sql    = 'SELECT fi.*, c.name AS cat FROM found_items fi LEFT JOIN categories c ON fi.category_id=c.id WHERE fi.status IN ("active","matched","claimed")';
$params = [];
if ($search) { $sql .= ' AND fi.item_name LIKE ?'; $params[] = "%$search%"; }
$sql .= ' ORDER BY fi.date_found DESC';
$items = db()->fetchAll($sql, $params);

// Group by storage location
$grouped = [];
foreach ($items as $item) {
    $key = $item['storage_location'] ?: 'Unassigned';
    $grouped[$key][] = $item;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> Storage</div>
    <div class="topbar-actions"></div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Item Storage</h1>
        <p class="page-subtitle">All items currently in custody — <?= number_format(count($items)) ?> item<?= count($items)!==1?'s':'' ?></p>
      </div>
    </div>

    <div class="filters-row mb-3">
      <form method="GET" class="d-flex gap-2 w-100">
        <div class="search-bar flex-grow-1"><i class="bi bi-search"></i><input type="text" name="search" class="form-control" placeholder="Search items…" value="<?= e($search) ?>"></div>
        <button type="submit" class="btn btn-outline-primary btn-sm">Search</button>
        <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>

    <?php if (empty($grouped)): ?>
    <div class="empty-state"><i class="bi bi-archive"></i><h5>No items in storage</h5><p>All items have been released.</p></div>
    <?php else: ?>
    <?php foreach ($grouped as $location => $locItems): ?>
    <div class="card mb-3">
      <div class="card-header">
        <span class="card-title">
          <i class="bi bi-geo-alt text-primary me-1"></i>
          <?= e($location) ?>
          <span class="badge bg-secondary ms-1"><?= count($locItems) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.83rem;">
            <thead><tr><th>Report #</th><th>Item</th><th>Category</th><th>Date Found</th><th>Status</th><th>QR</th></tr></thead>
            <tbody>
              <?php foreach ($locItems as $fi): ?>
              <tr>
                <td><code style="font-size:.72rem;"><?= e($fi['report_number']) ?></code></td>
                <td>
                  <div class="fw-600"><?= e($fi['item_name']) ?></div>
                  <?php if ($fi['brand'] || $fi['color']): ?><div style="font-size:.72rem;color:var(--text-muted);"><?= e(implode(' · ',array_filter([$fi['brand']??'',$fi['color']??'']))) ?></div><?php endif; ?>
                </td>
                <td><?= e($fi['cat']) ?></td>
                <td style="white-space:nowrap;"><?= formatDate($fi['date_found']) ?></td>
                <td><?= statusBadge($fi['status']) ?></td>
                <td>
                  <?php if ($fi['qr_code_path']): ?>
                  <a href="<?= BASE_URL.'/qr_codes/'.basename($fi['qr_code_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2"><i class="bi bi-qr-code"></i></a>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
