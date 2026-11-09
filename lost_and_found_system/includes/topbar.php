<?php /** Shared dashboard / browse page toolbar. */ ?>
<div class="topbar">
  <button type="button" class="btn-icon" id="sidebarToggle" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i></button>
  <div class="topbar-title"><span><?= e(roleLabel()) ?></span><i class="bi bi-chevron-right" aria-hidden="true"></i><strong><?= e($pageTitle ?? 'Overview') ?></strong></div>
  <div class="topbar-actions">
    <div class="position-relative">
      <a href="<?= BASE_URL ?>/student/notifications.php" class="btn-icon" id="notifBtnTop" aria-label="Notifications<?= $notifCount ? ', ' . (int)$notifCount . ' unread' : '' ?>"><i class="bi bi-bell" aria-hidden="true"></i></a>
      <span id="notifBadge" class="notification-count" aria-hidden="true" <?= $notifCount ? '' : 'style="display:none"' ?>><?= (int)$notifCount ?></span>
    </div>
    <span class="topbar-avatar" aria-label="<?= e($user['name']) ?>"><?= userInitials($user['name']) ?></span>
  </div>
</div>
