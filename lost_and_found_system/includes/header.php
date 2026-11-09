<?php
/**
 * Shared HTML Header — included by every authenticated page.
 *
 * Required variables (set before include):
 *   $pageTitle  (string)  — tab/page title
 *   $activePage (string)  — nav highlight key
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireLogin();

// Prevent browser caching of dynamic portal pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$user         = currentUser();
$notifCount   = unreadCount($user['id']);
$schoolName   = getSetting('school_name', APP_NAME);
$isDark       = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
$themeAttr    = $isDark ? 'data-theme="dark"' : '';
$documentTitle = isset($pageTitle) ? e($pageTitle) . ' — ' . e($schoolName) : e($schoolName);
?>
<!DOCTYPE html>
<html lang="en" <?= $themeAttr ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="<?= e($schoolName) ?> Lost and Found Management System">
  <title><?= $documentTitle ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Custom Theme -->
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">

  <script>window.BASE_URL = <?= json_encode(BASE_URL, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>
<div class="visually-hidden" id="interactionStatus" role="status" aria-atomic="true"></div>

<?php
// Render flash message as a JS toast after DOM loads
$flash = getFlash();
if ($flash) {
    $ft = json_encode($flash['type'] === 'danger' ? 'error' : $flash['type'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $fm = json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo "<script>document.addEventListener('DOMContentLoaded',()=>showToast({$ft},{$fm}));</script>";
}
?>

<div class="app-wrapper">
