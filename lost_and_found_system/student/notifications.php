<?php
/**
 * Student — Notifications
 */
$pageTitle  = 'Notifications';
$activePage = 'notifications';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('student', 'admin', 'security');

$uid = $user['id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    markAllNotificationsRead($uid);
    redirect(BASE_URL . '/student/notifications.php');
}

// Mark single as read
if (isset($_GET['read'])) {
    markNotificationRead((int)$_GET['read'], $uid);
    redirect(BASE_URL . '/student/notifications.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$sql     = 'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC';
$pager   = paginate($sql, [$uid], $page, 15);
$unread  = unreadCount($uid);

include __DIR__ . '/../includes/header.php';
$sidebar = match($user['role']) {
    'admin'    => '../includes/sidebar_admin.php',
    'security' => '../includes/sidebar_security.php',
    default    => '../includes/sidebar_student.php',
};
include __DIR__ . '/' . $sidebar;
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Account /</span> Notifications</div>
    <div class="topbar-actions">
      <?php if ($unread > 0): ?>
      <a href="?mark_all=1" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-check-all me-1"></i> Mark all read
      </a>
      <?php endif; ?>
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Notifications
          <?php if ($unread > 0): ?>
          <span class="badge bg-danger ms-2" style="font-size:.75rem;"><?= $unread ?> new</span>
          <?php endif; ?>
        </h1>
        <p class="page-subtitle">Stay updated on matches, claims, and system alerts.</p>
      </div>
    </div>

    <div class="card">
      <?php if (empty($pager['rows'])): ?>
      <div class="card-body">
        <div class="empty-state py-4">
          <i class="bi bi-bell"></i>
          <h5>No notifications</h5>
          <p>You're all caught up! Notifications will appear here when there's activity.</p>
        </div>
      </div>
      <?php else: ?>
        <?php foreach ($pager['rows'] as $n): ?>
        <?php $icons = ['match'=>'bi-link-45deg','success'=>'bi-check-circle','warning'=>'bi-exclamation-triangle','info'=>'bi-info-circle','claim'=>'bi-file-earmark-check']; ?>
        <a href="?read=<?= $n['id'] ?>" class="text-decoration-none d-block notif-item <?= $n['is_read']?'':'unread' ?>">
          <div class="notif-icon <?= e($n['type']) ?>">
            <i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?>"></i>
          </div>
          <div class="notif-body" style="flex:1;">
            <div class="notif-title" style="font-size:.88rem;"><?= e($n['title']) ?></div>
            <div class="notif-msg"><?= e($n['message']) ?></div>
            <span class="notif-time"><?= timeAgo($n['created_at']) ?> · <?= formatDate($n['created_at'],'M j, Y h:i A') ?></span>
          </div>
          <?php if (!$n['is_read']): ?><div class="unread-dot"></div><?php endif; ?>
        </a>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($pager['pages'] > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $pager['from'] ?>–<?= $pager['to'] ?> of <?= $pager['total'] ?></small>
        <?= renderPagination($pager) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
