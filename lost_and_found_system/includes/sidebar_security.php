<?php
/**
 * Security Staff Sidebar
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

$u = currentUser();
$nc = unreadCount($u['id']);
$activePage = $activePage ?? '';
$schoolName = getSetting('school_name', APP_NAME);

function secNavLink(string $href, string $icon, string $label, string $page, string $active, int $badge = 0): string {
    $cls = $page === $active ? ' active' : '';
    $b   = $badge > 0 ? "<span class='nav-badge'>{$badge}</span>" : '';
    if ($page === 'notifications') $b = "<span class='nav-badge' data-notification-count" . ($badge ? '' : ' hidden') . ">{$badge}</span>";
    return "<li class='nav-item'>
              <a href='{$href}' class='nav-link-sidebar{$cls}'>
                <i class='bi {$icon}'></i> {$label} {$b}
              </a>
            </li>";
}

$pendingClaims = db()->fetchOne('SELECT COUNT(*) AS c FROM claims WHERE status="pending"')['c'] ?? 0;
?>

<nav class="sidebar" id="sidebar" aria-label="Security navigation">
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
    <?= secNavLink(BASE_URL.'/security/dashboard.php',    'bi-grid-1x2',           'Dashboard',    'dashboard', $activePage) ?>

    <li class="nav-section-label">Items</li>
    <?= secNavLink(BASE_URL.'/student/lost_items.php?action=new', 'bi-search', 'Report Lost Item', 'lost', $activePage) ?>
    <?= secNavLink(BASE_URL.'/security/found_items.php',  'bi-box-seam',           'Found Items',  'found',     $activePage) ?>
    <?= secNavLink(BASE_URL.'/security/storage.php',      'bi-archive',            'Storage',      'storage',   $activePage) ?>

    <li class="nav-section-label">Claims &amp; release</li>
    <?= secNavLink(BASE_URL.'/security/claims.php',       'bi-file-earmark-check', 'Claims',       'claims',    $activePage, (int)$pendingClaims) ?>
    <?= secNavLink(BASE_URL.'/security/release.php',      'bi-check2-circle',      'Release Items','release',   $activePage) ?>

    <?= secNavLink(BASE_URL.'/security/qr_scanner.php',   'bi-qr-code-scan',       'QR Scanner',   'qr',        $activePage) ?>

    <li class="nav-section-label">Account</li>
    <?= secNavLink(BASE_URL.'/student/notifications.php',  'bi-bell',              'Notifications','notifications',$activePage,$nc) ?>
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
        <div class="sidebar-user-role">Security Staff</div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout" aria-label="Log out" title="Log out">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<!-- ── Mobile Bottom Navigation (hidden on desktop via CSS) ── -->
<nav class="mobile-bottom-nav d-lg-none" aria-label="Mobile navigation">
  <a href="<?= BASE_URL ?>/security/dashboard.php"
     class="mbn-item <?= $activePage==='dashboard' ? 'active' : '' ?>">
    <i class="bi bi-grid-1x2"></i>
    <span>Home</span>
  </a>
  <a href="<?= BASE_URL ?>/security/found_items.php"
     class="mbn-item <?= $activePage==='found' ? 'active' : '' ?>">
    <i class="bi bi-box-seam"></i>
    <span>Found</span>
  </a>
  <a href="<?= BASE_URL ?>/security/claims.php"
     class="mbn-item <?= $activePage==='claims' ? 'active' : '' ?>"
     style="position:relative;">
    <i class="bi bi-file-earmark-check"></i>
    <span>Claims</span>
    <?php if ($pendingClaims > 0): ?>
    <span class="mbn-badge"><?= $pendingClaims > 9 ? '9+' : $pendingClaims ?></span>
    <?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/security/qr_scanner.php"
     class="mbn-item <?= $activePage==='qr' ? 'active' : '' ?>">
    <i class="bi bi-qr-code-scan"></i>
    <span>QR Scan</span>
  </a>
  <a href="<?= BASE_URL ?>/student/notifications.php"
     class="mbn-item <?= $activePage==='notifications' ? 'active' : '' ?>"
     style="position:relative;">
    <i class="bi bi-bell"></i>
    <span>Alerts</span>
    <span class="mbn-badge" data-notification-count <?= $nc ? '' : 'hidden' ?>><?= $nc > 9 ? '9+' : $nc ?></span>
  </a>
</nav>
