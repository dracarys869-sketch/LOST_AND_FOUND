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
require_once __DIR__ . '/../includes/item_reports.php';

requireRole('student', 'admin', 'security');
$reportRole = currentUser()['role'];
$reportsUrl = match ($reportRole) {
    'security' => BASE_URL . '/security/found_items.php',
    'admin' => BASE_URL . '/admin/reports.php?type=found',
    default => BASE_URL . '/student/my_reports.php?type=found',
};

$categories = getCategories();
$locations  = getLocations();
$errors = [];
$formValue = static fn(string $key, string $default = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $validated = reportInput($_POST, 'found', $categories, $locations);
    $errors = $validated['errors'];

    if (empty($errors)) {
        try {
            $saved = saveItemReport('found', (int)$user['id'], $validated['values'], $_FILES['photos'] ?? []);
            $message = 'Found report ' . $saved['report_number'] . ' saved. ' . ($saved['qr_generated'] ? 'Its QR code is available in your reports.' : 'Staff can look it up using the report number.');
            if (!$saved['matching_complete']) $message .= ' Automatic matching is temporarily unavailable; there is no need to submit again.';
            setFlash($saved['matching_complete'] ? 'success' : 'warning', $message);
            redirect($reportsUrl);
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            error_log('Found report failed: ' . get_class($error));
            $errors[] = 'Your report could not be saved. Your details are below; please try again.';
        }
    }
}

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_' . $reportRole . '.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm"><?= e(roleLabel()) ?> /</span> Report Found Item</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Report a Found Item</h1>
        <p class="page-subtitle">A small act of kindness. Share the details so its owner can find it.</p>
      </div>
      <a href="<?= e($reportsUrl) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> View reports
      </a>
    </div>

    <?php if ($errors): ?>
    <div class="alert alert-danger mb-3" role="alert">
      <ul class="mb-0 ps-3"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate data-pending-label="Submitting found report…">
      <?= csrfField() ?>
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card">
            <div class="card-header"><h2 class="card-title"><span class="form-section-number">01</span> What did you find?</h2></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="item-name">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" id="item-name" class="form-control" required minlength="2" maxlength="150"
                       placeholder="e.g. Blue Nike Jacket" value="<?= e($formValue('item_name')) ?>">
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="category">Category <span class="text-danger">*</span></label>
                  <select name="category_id" id="category" class="form-select" required>
                    <option value="">Select category…</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $formValue('category_id') == $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="location">Found Location <span class="text-danger">*</span></label>
                  <select name="location_id" id="location" class="form-select" required>
                    <option value="">Select location…</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= $formValue('location_id') == $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="brand">Brand</label>
                  <input type="text" name="brand" id="brand" class="form-control" maxlength="100" placeholder="e.g. Nike, Samsung" value="<?= e($formValue('brand')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="color">Color</label>
                  <input type="text" name="color" id="color" class="form-control" maxlength="50" placeholder="e.g. Blue, Red" value="<?= e($formValue('color')) ?>">
                </div>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label" for="date-found">Date Found <span class="text-danger">*</span></label>
                  <input type="date" name="date_found" id="date-found" class="form-control" required
                         max="<?= date('Y-m-d') ?>" value="<?= e($formValue('date_found', date('Y-m-d'))) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="time-found">Time Found</label>
                  <input type="time" name="time_found" id="time-found" class="form-control" value="<?= e($formValue('time_found')) ?>">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="storage">Storage Location</label>
                <input type="text" name="storage_location" id="storage" class="form-control" maxlength="200"
                       placeholder="e.g. Handed in at the Security Office" value="<?= e($formValue('storage_location')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="description">Private Detailed Description</label>
                <textarea name="description" id="description" class="form-control" rows="4" maxlength="4000"
                           placeholder="Describe the item: condition, contents, identifying marks…"><?= e($formValue('description')) ?></textarea>
                <div class="form-text">This description is for staff review. Item names and photos appear on the board, so keep unique identifying details here.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card">
            <div class="card-header"><h2 class="card-title"><span class="form-section-number">02</span> Add photos <span class="text-muted-sm">(optional)</span></h2></div>
            <div class="card-body">
              <div class="drop-zone" id="dropZone">
                <i class="bi bi-cloud-upload"></i>
                <p>Drag & drop or click to upload</p>
                <small class="text-muted">JPG, PNG, GIF, WebP · up to 5 MB each</small>
              </div>
              <input type="file" id="photosInput" name="photos[]" multiple accept="image/jpeg,image/png,image/gif,image/webp" class="d-none" aria-label="Item photos">
              <div class="photo-preview-grid" id="photoPreviewGrid"></div>
            </div>
          </div>
          <aside class="support-note">
              <h2>After you submit</h2>
              <p>
                A unique QR code will be generated for this item after submission. Security staff can scan it to quickly look up the item.
              </p>
          </aside>
        </div>
      </div>

      <div class="form-footer">
        <p><i class="bi bi-info-circle" aria-hidden="true"></i> Fields marked * are required.</p>
        <div class="action-row">
        <a href="<?= e($reportsUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-send me-2"></i> Submit Found Report
        </button>
        </div>
      </div>
    </form>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    initDropZone('dropZone', 'photosInput');
    initImagePreviews('photosInput', 'photoPreviewGrid');
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
