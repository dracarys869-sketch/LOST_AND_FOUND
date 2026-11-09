<?php
$pageTitle = 'Browse Items';
$activePage = 'browse';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/item_board.php';
requireRole('student');
$board = loadItemBoard($_GET, BASE_URL . '/student/browse.php', true);
$boardInPortal = true;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>
<main class="main-content" id="main-content" tabindex="-1">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Browse campus items</h1><p class="page-subtitle">Recognize something? Open a claim and share details that show it belongs to you.</p></div>
      <a href="<?= BASE_URL ?>/student/lost_items.php" class="btn btn-outline-secondary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report lost item</a>
    </div>
    <?php include __DIR__ . '/../includes/item_board_view.php'; ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
