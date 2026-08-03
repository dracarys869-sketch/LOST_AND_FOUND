<?php
/**
 * Student — Report Lost Item
 */
$pageTitle  = 'Report Lost Item';
$activePage = 'lost';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/matching_engine.php';

requireRole('student', 'admin');

$categories = getCategories();
$locations  = getLocations();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $itemName   = sanitize($_POST['item_name']   ?? '');
    $categoryId = (int)($_POST['category_id']    ?? 0);
    $locationId = (int)($_POST['location_id']    ?? 0);
    $brand      = sanitize($_POST['brand']       ?? '');
    $color      = sanitize($_POST['color']       ?? '');
    $description= sanitize($_POST['description'] ?? '');
    $dateLost   = sanitize($_POST['date_lost']   ?? '');
    $timeLost   = sanitize($_POST['time_lost']   ?? '');

    if (strlen($itemName) < 2) $errors[] = 'Item name is required.';
    if (!$categoryId)          $errors[] = 'Please select a category.';
    if (!$locationId)          $errors[] = 'Please select a location.';
    if (!$dateLost)            $errors[] = 'Date lost is required.';

    if (empty($errors)) {
        db()->beginTransaction();
        try {
            $reportNum = generateReportNumber(LOST_PREFIX, 'lost_items');
            $itemId    = (int)db()->insert(
                'INSERT INTO lost_items (report_number, user_id, category_id, location_id, item_name, brand, color, description, date_lost, time_lost)
                 VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$reportNum, $user['id'], $categoryId, $locationId, $itemName, $brand ?: null, $color ?: null, $description ?: null, $dateLost, $timeLost ?: null]
            );
            // Update status to active
            db()->execute('UPDATE lost_items SET status="active" WHERE id=?', [$itemId]);

            // Upload photos
            if (!empty($_FILES['photos']['name'][0])) {
                $paths = uploadMultipleFiles($_FILES['photos'], 'lost_items');
                foreach ($paths as $i => $path) {
                    db()->insert(
                        'INSERT INTO item_photos (item_type, item_id, photo_path, is_primary) VALUES ("lost",?,?,?)',
                        [$itemId, $path, $i === 0 ? 1 : 0]
                    );
                }
            }

            auditLog($user['id'], 'CREATE_LOST_ITEM', 'lost_items', $itemId);
            db()->commit();

            // Run matching engine
            MatchingEngine::runForLostItem($itemId);

            setFlash('success', "Lost item report {$reportNum} submitted successfully! We'll notify you if a match is found.");
            redirect(BASE_URL . '/student/my_reports.php');
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
    <div class="topbar-title"><span class="text-muted-sm">Student /</span> Report Lost Item</div>
    <div class="topbar-actions">
      
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Report a Lost Item</h1>
        <p class="page-subtitle">Fill in as much detail as possible to improve matching accuracy.</p>
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

    <form method="POST" enctype="multipart/form-data" id="lostItemForm" novalidate>
      <?= csrfField() ?>
      <div class="row g-4">
        <!-- Left: Item Details -->
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-clipboard-data"></i> Item Details</span></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" class="form-control" required
                       placeholder="e.g. Black JBL Wireless Earphones"
                       value="<?= e($_POST['item_name'] ?? '') ?>">
                <div class="form-text">Be specific: brand, model, or distinctive features.</div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Category <span class="text-danger">*</span></label>
                  <select name="category_id" class="form-select" required>
                    <option value="">Select category…</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id']??'')==$cat['id']?'selected':'' ?>>
                      <?= e($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last Seen Location <span class="text-danger">*</span></label>
                  <select name="location_id" class="form-select" required>
                    <option value="">Select location…</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= ($_POST['location_id']??'')==$loc['id']?'selected':'' ?>>
                      <?= e($loc['name']) ?><?= $loc['building'] ? ' — '.$loc['building'] : '' ?>
                    </option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Brand / Manufacturer</label>
                  <input type="text" name="brand" class="form-control" placeholder="e.g. JBL, Samsung, Nike"
                         value="<?= e($_POST['brand'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Color</label>
                  <input type="text" name="color" class="form-control" placeholder="e.g. Black, Navy Blue"
                         value="<?= e($_POST['color'] ?? '') ?>">
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Date Lost <span class="text-danger">*</span></label>
                  <input type="date" name="date_lost" class="form-control" required
                         max="<?= date('Y-m-d') ?>"
                         value="<?= e($_POST['date_lost'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Approximate Time Lost</label>
                  <input type="time" name="time_lost" class="form-control"
                         value="<?= e($_POST['time_lost'] ?? '') ?>">
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"
                          placeholder="Describe the item in detail: size, condition, identifying marks, what was inside, etc."><?= e($_POST['description'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Photos -->
        <div class="col-lg-4">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-images"></i> Photos (Optional)</span></div>
            <div class="card-body">
              <div class="drop-zone" id="dropZone">
                <i class="bi bi-cloud-upload"></i>
                <p>Drag & drop photos here<br>or click to browse</p>
                <small style="color:var(--text-light);">JPG, PNG up to 5MB each</small>
              </div>
              <input type="file" id="photosInput" name="photos[]" multiple accept="image/*" class="d-none">
              <div class="photo-preview-grid" id="photoPreviewGrid"></div>
            </div>
          </div>

          <!-- Tips card -->
          <div class="card mt-3" style="background:var(--primary-ultra);border-color:var(--border);">
            <div class="card-body">
              <h6 class="fw-700" style="color:var(--primary);"><i class="bi bi-lightbulb me-2"></i>Matching Tips</h6>
              <ul class="mb-0" style="font-size:.8rem;color:var(--text-muted);">
                <li>Add brand and color for better matches</li>
                <li>Describe unique features (stickers, scratches)</li>
                <li>Include what was inside (wallet, bag)</li>
                <li>Mention the exact area within the location</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= BASE_URL ?>/student/my_reports.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-send me-2"></i> Submit Lost Report
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
