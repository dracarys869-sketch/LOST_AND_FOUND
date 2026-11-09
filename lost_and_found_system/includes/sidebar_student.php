<?php
/**
 * Student Sidebar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

$u = currentUser();
$nc = unreadCount($u['id']);
$activePage = $activePage ?? '';
$schoolName = getSetting('school_name', APP_NAME);

function studentNavLink(string $href, string $icon, string $label, string $page, string $active, int $badge = 0): string {
    $cls = $page === $active ? ' active' : '';
    $b   = $badge > 0 ? "<span class='nav-badge'>{$badge}</span>" : '';
    if ($page === 'notifications') $b = "<span class='nav-badge' data-notification-count" . ($badge ? '' : ' hidden') . ">{$badge}</span>";
    return "<li class='nav-item'>
              <a href='{$href}' class='nav-link-sidebar{$cls}'>
                <i class='bi {$icon}'></i> {$label} {$b}
              </a>
            </li>";
}
?>

<nav class="sidebar" id="sidebar" aria-label="Student navigation">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon"><img src="<?= ASSETS_URL ?>/images/lost-found-logo.png" alt="Lost and Found logo"></div>
    <div class="sidebar-brand-text">
      <div class="sidebar-brand-name">Campus Lost &amp; Found</div>
      <span class="sidebar-brand-sub"><?= e($schoolName) ?></span>
    </div>
    <button type="button" class="sidebar-close d-lg-none" id="sidebarClose" aria-label="Close navigation">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <ul class="sidebar-nav list-unstyled">
    <?= studentNavLink(BASE_URL.'/student/dashboard.php',      'bi-grid-1x2',            'Overview',      'dashboard',     $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/browse.php',         'bi-grid',                'Browse Items',  'browse',        $activePage) ?>

    <li class="nav-section-label">Reports</li>
    <?= studentNavLink(BASE_URL.'/student/lost_items.php',     'bi-search',              'Report Lost',   'lost',          $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/found_items.php',    'bi-box-seam',            'Report Found',  'found',         $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/my_reports.php',     'bi-journal',             'My Reports',    'my_reports',    $activePage) ?>

    <li class="nav-section-label">Recovery</li>
    <?= studentNavLink(BASE_URL.'/student/matches.php',        'bi-link-45deg',          'My Matches',    'matches',       $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/claims.php',         'bi-file-earmark-check',  'My Claims',     'claims',        $activePage) ?>

    <li class="nav-section-label">Account</li>
    <?= studentNavLink(BASE_URL.'/student/notifications.php',  'bi-bell',                'Notifications', 'notifications', $activePage, $nc) ?>
    <?= studentNavLink(BASE_URL.'/student/profile.php',        'bi-person-circle',       'My Profile',    'profile',       $activePage) ?>
  </ul>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center justify-content-between px-2 mb-3">
      <span class="text-muted-sm">
        <i class="bi bi-moon me-2" aria-hidden="true"></i>Dark mode
      </span>
      <button type="button" class="dark-toggle <?= $isDark ? 'active' : '' ?>" id="darkToggle" role="switch" aria-checked="<?= $isDark ? 'true' : 'false' ?>" aria-label="Dark mode"></button>
    </div>
    <div class="sidebar-account">
      <div class="sidebar-avatar"><?= userInitials($u['name']) ?></div>
      <div class="sidebar-account-info">
        <div class="sidebar-user-name"><?= e($u['name']) ?></div>
        <div class="sidebar-user-role">Student</div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout" aria-label="Log out" title="Log out">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ── Mobile Bottom Navigation (hidden on desktop via CSS) ── -->
<nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
  <a href="<?= BASE_URL ?>/student/dashboard.php"
     class="mbn-item <?= $activePage==='dashboard' ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i>
    <span>Home</span>
  </a>
  <a href="<?= BASE_URL ?>/student/lost_items.php"
     class="mbn-item <?= $activePage==='lost' ? 'active' : '' ?>">
    <i class="bi bi-search"></i>
    <span>Lost</span>
  </a>
  <a href="<?= BASE_URL ?>/student/browse.php"
     class="mbn-item <?= $activePage==='browse' ? 'active' : '' ?>">
    <i class="bi bi-box-seam"></i>
    <span>Browse</span>
  </a>
  <a href="<?= BASE_URL ?>/student/matches.php"
     class="mbn-item <?= $activePage==='matches' ? 'active' : '' ?>">
    <i class="bi bi-link-45deg"></i>
    <span>Matches</span>
  </a>
  <a href="<?= BASE_URL ?>/student/notifications.php"
     class="mbn-item <?= $activePage==='notifications' ? 'active' : '' ?>"
     style="position:relative;">
    <i class="bi bi-bell"></i>
    <span>Alerts</span>
    <span class="mbn-badge" data-notification-count <?= $nc ? '' : 'hidden' ?>><?= $nc > 9 ? '9+' : $nc ?></span>
  </a>
</nav>
