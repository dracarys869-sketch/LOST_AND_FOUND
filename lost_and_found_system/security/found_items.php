<?php
/**
 * Security — Found Items Management
 */
$pageTitle  = 'Found Items';
$activePage = 'found';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/item_reports.php';

requireRole('security', 'admin');

$categories = getCategories();
$locations  = getLocations();
$errors     = [];
$formValue = static fn(string $key, string $default = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $validated = reportInput($_POST, 'found', $categories, $locations);
        $errors = $validated['errors'];

        if (empty($errors)) {
            try {
                $saved = saveItemReport('found', (int)$user['id'], $validated['values'], $_FILES['photos'] ?? []);
                $message = 'Found item ' . $saved['report_number'] . ' saved. Add its verification details below.';
                if (!$saved['matching_complete']) $message .= ' Automatic matching is temporarily unavailable; your report is already saved.';
                setFlash($saved['matching_complete'] ? 'success' : 'warning', $message);
                redirect(BASE_URL . '/security/item_verification.php?item_id=' . $saved['id']);
            } catch (InvalidArgumentException $error) {
                $errors[] = $error->getMessage();
            } catch (Throwable $error) {
                error_log('Found intake failed: ' . get_class($error));
                $errors[] = 'Your report could not be saved. Your details are below; please try again.';
            }
        }
    }

    if ($action === 'update_storage') {
        $id  = (int)($_POST['item_id'] ?? 0);
        $loc = sanitize($_POST['storage_location'] ?? '');
        if ($id) { db()->execute('UPDATE found_items SET storage_location=? WHERE id=?',[$loc,$id]); setFlash('success','Storage location updated.'); }
        redirect(BASE_URL.'/security/found_items.php');
    }
}

$showForm = !empty($errors) || (isset($_GET['action']) && $_GET['action'] === 'new');
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));

$sql    = 'SELECT fi.*, c.name AS cat, l.name AS loc FROM found_items fi LEFT JOIN categories c ON fi.category_id=c.id LEFT JOIN locations l ON fi.location_id=l.id WHERE 1=1';
$params = [];
if ($search) { $sql .= ' AND fi.item_name LIKE ?'; $params[] = "%$search%"; }
if ($status) { $sql .= ' AND fi.status=?'; $params[] = $status; }
else { $sql .= ' AND fi.status != "released"'; }
$sql .= ' ORDER BY fi.created_at DESC';
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_' . $user['role'] . '.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm"><?= e(roleLabel()) ?> /</span> Found Items</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title"><?= $showForm ? 'Log a found item' : 'Found items in custody' ?></h1><p class="page-subtitle">Record item details and where they are stored.</p></div>
       <?php if (!$showForm): ?><a href="?action=new" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Log found item</a><?php else: ?><a href="<?= BASE_URL ?>/security/found_items.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to found items</a><?php endif; ?>
    </div>

    <!-- New Found Item Form -->
    <?php if ($showForm): ?>
    <div class="card mb-4 form-width">
      <form method="POST" enctype="multipart/form-data" novalidate data-pending-label="Saving found item and generating its QR code…">
        <?= csrfField() ?><input type="hidden" name="action" value="create">
        <?php if ($errors): ?><div class="card-body pt-0 pb-0"><div class="alert alert-danger mt-3" role="alert"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div></div><?php endif; ?>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="item-name">Item Name *</label><input type="text" name="item_name" id="item-name" class="form-control" required minlength="2" maxlength="150" value="<?= e($formValue('item_name')) ?>" placeholder="e.g. Black canvas backpack"></div>
            <div class="col-md-6"><label class="form-label" for="category">Category *</label><select name="category_id" id="category" class="form-select" required><option value="">Select…</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $formValue('category_id') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="location">Found Location *</label><select name="location_id" id="location" class="form-select" required><option value="">Select…</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>" <?= $formValue('location_id') == $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label" for="storage">Storage Location</label><input type="text" name="storage_location" id="storage" class="form-control" maxlength="200" placeholder="e.g. Security Office — Cabinet A" value="<?= e($formValue('storage_location')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="brand">Brand</label><input type="text" name="brand" id="brand" class="form-control" maxlength="100" value="<?= e($formValue('brand')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="color">Color</label><input type="text" name="color" id="color" class="form-control" maxlength="50" value="<?= e($formValue('color')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="date-found">Date Found *</label><input type="date" name="date_found" id="date-found" class="form-control" required max="<?= date('Y-m-d') ?>" value="<?= e($formValue('date_found', date('Y-m-d'))) ?>"></div>
            <div class="col-12"><label class="form-label" for="description">Private Detailed Description</label><textarea name="description" id="description" class="form-control" rows="3" maxlength="4000"><?= e($formValue('description')) ?></textarea><div class="form-text">Keep the item name generic. Record unique details here; add an independently checked verification reference after saving.</div></div>
            <div class="col-12">
              <label class="form-label" for="photosInput">Item photos (optional)</label>
              <div class="drop-zone" id="dropZone"><i class="bi bi-cloud-upload"></i><p>Drop photos here or click to browse</p></div>
              <input type="file" id="photosInput" name="photos[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" class="d-none">
              <div class="form-text">JPG, PNG, GIF, WebP · up to 5 MB each. Photos appear on the item board; put private identifying details in the description.</div>
              <div class="photo-preview-grid" id="photoPreviewGrid"></div>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
          <a href="<?= BASE_URL ?>/security/found_items.php" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>Log Found Item</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <?php if (!$showForm): ?>
    <!-- Filters -->
    <div class="filters-row">
      <form method="GET" class="d-flex gap-2 flex-wrap w-100">
        <div class="search-bar flex-grow-1"><i class="bi bi-search"></i><input type="search" name="search" class="form-control" aria-label="Search found items" placeholder="Search item…" value="<?= e($search) ?>"></div>
        <select name="status" class="form-select" aria-label="Filter by status">
          <option value="">All in custody</option>
          <?php foreach (['active','matched','claimed','released'] as $s): ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
        <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Report #</th><th>Item</th><th>Location Found</th><th>Storage</th><th>Status</th><th>QR</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $fi): ?>
              <tr>
                <td><code><?= e($fi['report_number']) ?></code></td>
                <td>
                  <div class="fw-600"><?= e($fi['item_name']) ?></div>
                  <div class="text-muted-sm"><?= e($fi['cat']) ?> <?= $fi['color'] ? '· ' . e($fi['color']) : '' ?></div>
                </td>
                <td><?= e($fi['loc']) ?></td>
                <td><?= e($fi['storage_location'] ?? '—') ?></td>
                <td><?= statusBadge($fi['status']) ?></td>
                <td>
                  <?php if ($fi['qr_code_path']): ?>
                  <a href="<?= BASE_URL.'/qr_codes/'.basename($fi['qr_code_path']) ?>" target="_blank"
                     class="btn btn-sm btn-outline-primary py-0 px-2" title="View QR Code">
                    <i class="bi bi-qr-code"></i>
                  </a>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-nowrap"><?= formatDate($fi['date_found']) ?></td>
                <td>
                  <a href="<?= BASE_URL ?>/security/item_verification.php?item_id=<?= $fi['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2" title="Private verification reference"><i class="bi bi-shield-lock"></i> Verify</a>
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                          data-bs-toggle="modal" data-bs-target="#storageModal"
                          data-id="<?= $fi['id'] ?>" data-storage="<?= e($fi['storage_location']??'') ?>" aria-label="Update storage for <?= e($fi['item_name']) ?>">
                    <i class="bi bi-geo-alt"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?><tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox" aria-hidden="true"></i><h3><?= $search || $status ? 'No items match your filters' : 'No items in custody yet' ?></h3><p><?= $search || $status ? 'Try another search or clear the filters to see items in custody.' : 'Log a found item to start its custody record.' ?></p><a href="<?= $search || $status ? '?' : '?action=new' ?>" class="text-link"><?= $search || $status ? 'Clear filters' : 'Log found item' ?></a></div></td></tr><?php endif; ?>
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
    <?php endif; ?>
  </div>
</main>

<!-- Storage Modal -->
<div class="modal fade" id="storageModal" tabindex="-1" aria-labelledby="storage-modal-title">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title" id="storage-modal-title">Update storage location</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="update_storage"><input type="hidden" name="item_id" id="storageItemId">
      <div class="modal-body">
        <label class="form-label" for="storageValue">Storage Location</label>
        <input type="text" name="storage_location" class="form-control" id="storageValue" placeholder="e.g. Security Office — Cabinet A">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initDropZone('dropZone','photosInput');
    initImagePreviews('photosInput','photoPreviewGrid');
});
document.getElementById('storageModal')?.addEventListener('show.bs.modal',function(e){
    const b=e.relatedTarget;
    document.getElementById('storageItemId').value=b.dataset.id;
    document.getElementById('storageValue').value=b.dataset.storage;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
