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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (($_POST['action'] ?? '') === 'mark_all') {
        markAllNotificationsRead($uid);
        setFlash('success', 'All notifications marked as read.');
    } elseif (($_POST['action'] ?? '') === 'read') {
        markNotificationRead((int)($_POST['notification_id'] ?? 0), $uid);
    }
    $returnPage = max(1, (int)($_POST['page'] ?? 1));
    redirect(BASE_URL . '/student/notifications.php?page=' . $returnPage);
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

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Account /</span> Notifications</div>
    <div class="topbar-actions">
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Notifications
          <?php if ($unread > 0): ?>
          <span class="badge ms-2"><?= $unread ?> unread</span>
          <?php endif; ?>
        </h1>
        <p class="page-subtitle">Updates on matches, ownership reviews, and item returns.</p>
      </div>
       <?php if ($unread > 0): ?><form method="POST" data-pending-label="Marking as read…"><?= csrfField() ?><input type="hidden" name="action" value="mark_all"><input type="hidden" name="page" value="<?= $page ?>"><button type="submit" class="btn btn-primary"><i class="bi bi-check-all" aria-hidden="true"></i> Mark all read</button></form><?php endif; ?>
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
        <article class="notif-item <?= $n['is_read']?'':'unread' ?>">
          <div class="notif-icon <?= e($n['type']) ?>">
            <i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?>"></i>
          </div>
          <div class="notif-body">
            <a class="notif-title" href="<?= e(notificationUrl($n, $user['role'])) ?>"><?= e($n['title']) ?> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <div class="notif-msg"><?= e($n['message']) ?></div>
            <span class="notif-time"><?= timeAgo($n['created_at']) ?> · <?= formatDate($n['created_at'],'M j, Y h:i A') ?></span>
            <?php if (!$n['is_read']): ?>
            <form method="POST" class="mt-2" data-pending-label="Marking as read…"><?= csrfField() ?><input type="hidden" name="action" value="read"><input type="hidden" name="notification_id" value="<?= (int)$n['id'] ?>"><input type="hidden" name="page" value="<?= $page ?>"><button type="submit" class="btn btn-outline-secondary btn-sm" aria-label="Mark <?= e($n['title']) ?> as read">Mark as read</button></form>
            <?php endif; ?>
          </div>
          <?php if (!$n['is_read']): ?><span class="unread-dot" role="img" aria-label="Unread"></span><?php endif; ?>
        </article>
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
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
