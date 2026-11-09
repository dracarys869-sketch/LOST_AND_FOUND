<?php
/**
 * Admin — System Settings
 */
$pageTitle  = 'Settings';
$activePage = 'settings';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $keys = ['school_name','school_address','school_email','school_phone',
             'match_threshold','session_timeout','max_upload_size','items_per_page',
             'allow_registration','maintenance_mode'];
    $checkboxes = ['allow_registration','maintenance_mode'];
    foreach ($keys as $key) {
        if (in_array($key, $checkboxes, true)) {
            // Unchecked checkboxes are absent from POST — persist "0" explicitly.
            $val = isset($_POST[$key]) ? '1' : '0';
        } elseif (isset($_POST[$key])) {
            $val = sanitize($_POST[$key]);
        } else {
            continue;
        }
        db()->execute('UPDATE settings SET setting_value=? WHERE setting_key=?', [$val, $key]);
    }
    auditLog($user['id'], 'UPDATE_SETTINGS', 'settings', null);
    setFlash('success', 'Settings saved successfully.');
    redirect(BASE_URL . '/admin/settings.php');
}

// Load all settings
$allSettings = [];
$rows = db()->fetchAll('SELECT setting_key, setting_value FROM settings');
foreach ($rows as $r) { $allSettings[$r['setting_key']] = $r['setting_value']; }

$sv = fn(string $k, mixed $d='') => $allSettings[$k] ?? $d;

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Settings</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">System Settings</h1><p class="page-subtitle">Configure application-wide preferences.</p></div>
    </div>

    <form method="POST" data-pending-label="Saving settings…">
      <?= csrfField() ?>
      <div class="row g-4">
        <!-- School Info -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-building"></i> School Information</span></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="school-name">School Name</label>
                <input type="text" name="school_name" id="school-name" class="form-control" value="<?= e($sv('school_name', APP_NAME)) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="school-address">Address</label>
                <textarea name="school_address" id="school-address" class="form-control" rows="2"><?= e($sv('school_address')) ?></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label" for="school-email">Email</label>
                <input type="email" name="school_email" id="school-email" class="form-control" value="<?= e($sv('school_email')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="school-phone">Phone</label>
                <input type="text" name="school_phone" id="school-phone" class="form-control" value="<?= e($sv('school_phone')) ?>">
              </div>
            </div>
          </div>
        </div>

        <!-- System Settings -->
        <div class="col-lg-6">
          <div class="card">
            <div class="card-header"><span class="card-title"><i class="bi bi-gear"></i> System Preferences</span></div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label" for="match-threshold">Match Threshold (%)</label>
                <input type="number" name="match_threshold" id="match-threshold" class="form-control" min="50" max="100"
                       value="<?= e($sv('match_threshold', '75')) ?>">
                <div class="form-text">Minimum similarity score to flag as a possible match (50–100).</div>
              </div>
              <div class="mb-3">
                <label class="form-label" for="session-timeout">Session Timeout (minutes)</label>
                <input type="number" name="session_timeout" id="session-timeout" class="form-control" min="5" max="480"
                       value="<?= e($sv('session_timeout', '30')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="max-upload-size">Max Upload Size (MB)</label>
                <input type="number" name="max_upload_size" id="max-upload-size" class="form-control" min="1" max="20"
                       value="<?= e($sv('max_upload_size', '5')) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label" for="items-per-page">Items Per Page</label>
                <select name="items_per_page" id="items-per-page" class="form-select">
                  <?php foreach ([5,10,15,20,25,50] as $n): ?>
                  <option value="<?= $n ?>" <?= (int)$sv('items_per_page', '10') === $n ? 'selected' : '' ?>><?= $n ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="allowReg" name="allow_registration"
                       value="1" <?= $sv('allow_registration','1')==='1'?'checked':'' ?>>
                <label class="form-check-label" for="allowReg">Allow Student Self-Registration</label>
              </div>
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="maintMode" name="maintenance_mode"
                       value="1" <?= $sv('maintenance_mode','0')==='1'?'checked':'' ?>>
                <label class="form-check-label" for="maintMode">Maintenance Mode</label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-3 d-flex justify-content-end">
        <button type="submit" class="btn btn-primary px-4">
          <i class="bi bi-check-lg me-2"></i> Save Settings
        </button>
      </div>
    </form>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
