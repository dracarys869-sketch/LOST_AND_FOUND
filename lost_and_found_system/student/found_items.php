<?php
/**
 * Student — Report Found Item
 */
$pageTitle  = 'Report Found Item';
$activePage = 'found';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/matching_engine.php';
require_once __DIR__ . '/../includes/qr_generator.php';

requireRole('student', 'admin', 'security');

$categories = getCategories();
$locations  = getLocations();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $itemName        = sanitize($_POST['item_name']       ?? '');
    $categoryId      = (int)($_POST['category_id']        ?? 0);
    $locationId      = (int)($_POST['location_id']        ?? 0);
    $brand           = sanitize($_POST['brand']           ?? '');
    $color           = sanitize($_POST['color']           ?? '');
    $description     = sanitize($_POST['description']     ?? '');
    $dateFound       = sanitize($_POST['date_found']      ?? '');
    $timeFound       = sanitize($_POST['time_found']      ?? '');
    $storageLocation = sanitize($_POST['storage_location']?? '');

    if (strlen($itemName) < 2) $errors[] = 'Item name is required.';
    if (!$categoryId)          $errors[] = 'Please select a category.';
    if (!$locationId)          $errors[] = 'Please select a location.';
    if (!$dateFound)           $errors[] = 'Date found is required.';

    if (empty($errors)) {
        db()->beginTransaction();
        try {
            $reportNum = generateReportNumber(FOUND_PREFIX, 'found_items');
            $itemId    = (int)db()->insert(
                'INSERT INTO found_items (report_number, user_id, category_id, location_id, item_name, brand, color, description, date_found, time_found, storage_location, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,"active")',
                [$reportNum, $user['id'], $categoryId, $locationId, $itemName, $brand ?: null, $color ?: null, $description ?: null, $dateFound, $timeFound ?: null, $storageLocation ?: null]
            );

            // Generate QR code
            $qrPath = generateQRCode($itemId, $reportNum);
            if ($qrPath) {
                db()->execute('UPDATE found_items SET qr_code_path=? WHERE id=?', [$qrPath, $itemId]);
            }

            // Upload photos
            if (!empty($_FILES['photos']['name'][0])) {
                $paths = uploadMultipleFiles($_FILES['photos'], 'found_items');
                foreach ($paths as $i => $path) {
                    db()->insert(
                        'INSERT INTO item_photos (item_type, item_id, photo_path, is_primary) VALUES ("found",?,?,?)',
                        [$itemId, $path, $i === 0 ? 1 : 0]
                    );
                }
            }

            auditLog($user['id'], 'CREATE_FOUND_ITEM', 'found_items', $itemId);
            db()->commit();

            // Run matching engine
            MatchingEngine::runForFoundItem($itemId);

            setFlash('success', "Found item report {$reportNum} submitted! A QR code has been generated.");
            redirect(BASE_URL . '/student/my_reports.php?type=found');
        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'An error occurred. Please try again.';
            if (APP_DEBUG) $errors[] = $e->getMessage();
        }
    }
}

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Student /</span> Report Found Item</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Report a Found Item</h1>
        <p class="page-subtitle">Log an item you found on campus. A QR code will be generated automatically.</p>
      </div>
      <a href="<?= BASE_URL ?>/student/my_reports.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> My Reports
      </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger mb-3">
      <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
      <?= csrfField() ?>
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-clipboard-data"></i> Item Details</span></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" class="form-control" required
                       placeholder="e.g. Blue Nike Jacket" value="<?= e($_POST['item_name'] ?? '') ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Category <span class="text-danger">*</span></label>
                  <select name="category_id" class="form-select" required>
                    <option value="">Select category…</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Found Location <span class="text-danger">*</span></label>
                  <select name="location_id" class="form-select" required>
                    <option value="">Select location…</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>"><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Brand</label>
                  <input type="text" name="brand" class="form-control" placeholder="e.g. Nike, Samsung">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Color</label>
                  <input type="text" name="color" class="form-control" placeholder="e.g. Blue, Red">
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Date Found <span class="text-danger">*</span></label>
                  <input type="date" name="date_found" class="form-control" required
                         max="<?= date('Y-m-d') ?>" value="<?= e($_POST['date_found'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Time Found</label>
                  <input type="time" name="time_found" class="form-control">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Storage Location</label>
                <input type="text" name="storage_location" class="form-control"
                       placeholder="e.g. Security Office — Cabinet A">
              </div>
              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Describe the item: condition, contents, identifying marks…"></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-images"></i> Photos</span></div>
            <div class="card-body">
              <div class="drop-zone" id="dropZone">
                <i class="bi bi-cloud-upload"></i>
                <p>Drag & drop or click to upload</p>
              </div>
              <input type="file" id="photosInput" name="photos[]" multiple accept="image/*" class="d-none">
              <div class="photo-preview-grid" id="photoPreviewGrid"></div>
            </div>
          </div>
          <div class="card mt-3" style="background:rgba(13,202,240,.08);border-color:rgba(13,202,240,.3);">
            <div class="card-body">
              <h6 class="fw-700" style="color:#0c7590;"><i class="bi bi-qr-code me-2"></i>QR Code Auto-Generated</h6>
              <p style="font-size:.8rem;color:var(--text-muted);margin:0;">
                A unique QR code will be generated for this item after submission. Security staff can scan it to quickly look up the item.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= BASE_URL ?>/student/my_reports.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-send me-2"></i> Submit Found Report
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initDropZone('dropZone', 'photosInput');
    initImagePreviews('photosInput', 'photoPreviewGrid');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
