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
require_once __DIR__ . '/../includes/matching_engine.php';
require_once __DIR__ . '/../includes/qr_generator.php';

requireRole('security', 'admin');

$categories = getCategories();
$locations  = getLocations();
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $itemName        = sanitize($_POST['item_name']        ?? '');
        $categoryId      = (int)($_POST['category_id']         ?? 0);
        $locationId      = (int)($_POST['location_id']         ?? 0);
        $brand           = sanitize($_POST['brand']            ?? '');
        $color           = sanitize($_POST['color']            ?? '');
        $description     = sanitize($_POST['description']      ?? '');
        $dateFound       = sanitize($_POST['date_found']       ?? '');
        $timeFound       = sanitize($_POST['time_found']       ?? '');
        $storageLocation = sanitize($_POST['storage_location'] ?? '');

        if (strlen($itemName) < 2) $errors[] = 'Item name required.';
        if (!$categoryId)          $errors[] = 'Category required.';
        if (!$locationId)          $errors[] = 'Location required.';
        if (!$dateFound)           $errors[] = 'Date found required.';

        if (empty($errors)) {
            db()->beginTransaction();
            try {
                $reportNum = generateReportNumber(FOUND_PREFIX, 'found_items');
                $itemId    = (int)db()->insert(
                    'INSERT INTO found_items (report_number,user_id,category_id,location_id,item_name,brand,color,description,date_found,time_found,storage_location,status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,"active")',
                    [$reportNum,$user['id'],$categoryId,$locationId,$itemName,$brand?:null,$color?:null,$description?:null,$dateFound,$timeFound?:null,$storageLocation?:null]
                );

                // Generate QR
                $qrPath = generateQRCode($itemId, $reportNum);
                if ($qrPath) db()->execute('UPDATE found_items SET qr_code_path=? WHERE id=?', [$qrPath,$itemId]);

                // Photos
                if (!empty($_FILES['photos']['name'][0])) {
                    $paths = uploadMultipleFiles($_FILES['photos'], 'found_items');
                    foreach ($paths as $i => $p) {
                        db()->insert('INSERT INTO item_photos (item_type,item_id,photo_path,is_primary) VALUES ("found",?,?,?)', [$itemId,$p,$i===0?1:0]);
                    }
                }

                auditLog($user['id'],'CREATE_FOUND_ITEM','found_items',$itemId);
                db()->commit();
                MatchingEngine::runForFoundItem($itemId);
                setFlash('success',"Found item {$reportNum} logged successfully. QR code generated.");
                redirect(BASE_URL.'/security/found_items.php');
            } catch (Exception $e) {
                db()->rollBack();
                $errors[] = 'Error: '.$e->getMessage();
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

$showForm = isset($_GET['action']) && $_GET['action'] === 'new';
$search   = sanitize($_GET['search'] ?? '');
$status   = sanitize($_GET['status'] ?? '');
$page     = max(1,(int)($_GET['page'] ?? 1));

$sql    = 'SELECT fi.*, c.name AS cat, l.name AS loc FROM found_items fi LEFT JOIN categories c ON fi.category_id=c.id LEFT JOIN locations l ON fi.location_id=l.id WHERE fi.status != "released"';
$params = [];
if ($search) { $sql .= ' AND fi.item_name LIKE ?'; $params[] = "%$search%"; }
if ($status) { $sql .= ' AND fi.status=?'; $params[] = $status; }
$sql .= ' ORDER BY fi.created_at DESC';
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> Found Items</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Found Items</h1><p class="page-subtitle">Log and manage found items in custody.</p></div>
      <a href="?action=new" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>Log Found Item</a>
    </div>

    <!-- New Found Item Form -->
    <?php if ($showForm): ?>
    <div class="card mb-4">
      <div class="card-header"><span class="card-title"><i class="bi bi-plus-circle"></i> Log New Found Item</span></div>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?><input type="hidden" name="action" value="create">
        <?php if ($errors): ?><div class="card-body pt-0 pb-0"><div class="alert alert-danger mt-3"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div></div><?php endif; ?>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Item Name *</label><input type="text" name="item_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Category *</label><select name="category_id" class="form-select" required><option value="">Select…</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Found Location *</label><select name="location_id" class="form-select" required><option value="">Select…</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Storage Location</label><input type="text" name="storage_location" class="form-control" placeholder="e.g. Security Office — Cabinet A"></div>
            <div class="col-md-4"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Color</label><input type="text" name="color" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Date Found *</label><input type="date" name="date_found" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            <div class="col-12">
              <label class="form-label">Photos</label>
              <div class="drop-zone" id="dropZone"><i class="bi bi-cloud-upload"></i><p>Drop photos here or click to browse</p></div>
              <input type="file" id="photosInput" name="photos[]" multiple accept="image/*" class="d-none">
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

    <!-- Filters -->
    <div class="filters-row">
      <form method="GET" class="d-flex gap-2 flex-wrap w-100">
        <div class="search-bar flex-grow-1"><i class="bi bi-search"></i><input type="text" name="search" class="form-control" placeholder="Search item…" value="<?= e($search) ?>"></div>
        <select name="status" class="form-select" style="max-width:160px;">
          <option value="">All Status</option>
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
                <td><code style="font-size:.72rem;"><?= e($fi['report_number']) ?></code></td>
                <td>
                  <div class="fw-600" style="font-size:.85rem;"><?= e($fi['item_name']) ?></div>
                  <div style="font-size:.72rem;color:var(--text-muted);"><?= e($fi['cat']) ?> <?= $fi['color']?'· '.$fi['color']:'' ?></div>
                </td>
                <td style="font-size:.8rem;"><?= e($fi['loc']) ?></td>
                <td style="font-size:.8rem;"><?= e($fi['storage_location'] ?? '—') ?></td>
                <td><?= statusBadge($fi['status']) ?></td>
                <td>
                  <?php if ($fi['qr_code_path']): ?>
                  <a href="<?= BASE_URL.'/qr_codes/'.basename($fi['qr_code_path']) ?>" target="_blank"
                     class="btn btn-sm btn-outline-primary py-0 px-2" title="View QR Code">
                    <i class="bi bi-qr-code"></i>
                  </a>
                  <?php else: ?><span class="text-muted" style="font-size:.75rem;">—</span><?php endif; ?>
                </td>
                <td style="font-size:.78rem;white-space:nowrap;"><?= formatDate($fi['date_found']) ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                          data-bs-toggle="modal" data-bs-target="#storageModal"
                          data-id="<?= $fi['id'] ?>" data-storage="<?= e($fi['storage_location']??'') ?>">
                    <i class="bi bi-geo-alt"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?><tr><td colspan="8"><div class="empty-state py-3"><i class="bi bi-inbox"></i><h5>No found items</h5></div></td></tr><?php endif; ?>
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

<!-- Storage Modal -->
<div class="modal fade" id="storageModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title"><i class="bi bi-geo-alt"></i> Update Storage Location</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="update_storage"><input type="hidden" name="item_id" id="storageItemId">
      <div class="modal-body">
        <label class="form-label">Storage Location</label>
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
