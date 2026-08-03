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
    return "<li class='nav-item'>
              <a href='{$href}' class='nav-link-sidebar{$cls}'>
                <i class='bi {$icon}'></i> {$label} {$b}
              </a>
            </li>";
}
?>

<nav class="sidebar" id="sidebar">
  <div class="sidebar-user">
    <div class="sidebar-avatar"><?= userInitials($u['name']) ?></div>
    <div>
      <div class="sidebar-user-name"><?= e($u['name']) ?></div>
      <div class="sidebar-user-role"><?= e($u['student_id'] ?? 'Student') ?></div>
    </div>
  </div>

  <ul class="sidebar-nav list-unstyled">
    <span class="nav-section-label">Overview</span>
    <?= studentNavLink(BASE_URL.'/student/dashboard.php',      'bi-grid-1x2',            'Dashboard',     'dashboard',     $activePage) ?>

    <span class="nav-section-label">My Reports</span>
    <?= studentNavLink(BASE_URL.'/student/lost_items.php',     'bi-search',              'Report Lost',   'lost',          $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/found_items.php',    'bi-box-seam',            'Report Found',  'found',         $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/my_reports.php',     'bi-journal',             'My Reports',    'my_reports',    $activePage) ?>

    <span class="nav-section-label">Matches & Claims</span>
    <?= studentNavLink(BASE_URL.'/student/matches.php',        'bi-link-45deg',          'My Matches',    'matches',       $activePage) ?>
    <?= studentNavLink(BASE_URL.'/student/claims.php',         'bi-file-earmark-check',  'My Claims',     'claims',        $activePage) ?>

    <span class="nav-section-label">Account</span>
    <?= studentNavLink(BASE_URL.'/student/notifications.php',  'bi-bell',                'Notifications', 'notifications', $activePage, $nc) ?>
    <?= studentNavLink(BASE_URL.'/student/profile.php',        'bi-person-circle',       'My Profile',    'profile',       $activePage) ?>
  </ul>

  <div class="sidebar-footer">
    <div class="d-flex align-items-center justify-content-between px-2 mb-3">
      <span style="font-size: 0.85rem; color: var(--sidebar-text); font-weight: 500;">
        <i class="bi bi-moon-stars me-2"></i>Dark Mode
      </span>
      <button class="dark-toggle <?= $isDark ? 'active' : '' ?>" id="darkToggle" aria-label="Toggle dark mode"></button>
    </div>
    <a href="<?= BASE_URL ?>/logout.php" class="nav-link-sidebar px-2">
      <i class="bi bi-box-arrow-left"></i> Logout
    </a>
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
  <a href="<?= BASE_URL ?>/student/found_items.php"
     class="mbn-item <?= $activePage==='found' ? 'active' : '' ?>">
    <i class="bi bi-box-seam"></i>
    <span>Found</span>
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
    <?php if ($nc > 0): ?>
    <span class="mbn-badge"><?= $nc > 9 ? '9+' : $nc ?></span>
    <?php endif; ?>
  </a>
</nav>
