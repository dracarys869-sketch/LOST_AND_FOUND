<?php
/**
 * Student Dashboard
 */
$pageTitle  = 'Student Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('student');

$uid = $user['id'];

// KPIs
$myLost    = db()->fetchOne('SELECT COUNT(*) AS c FROM lost_items  WHERE user_id=?',[$uid])['c'] ?? 0;
$myFound   = db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE user_id=?',[$uid])['c'] ?? 0;
$myMatches = db()->fetchOne('SELECT COUNT(*) AS c FROM matches m LEFT JOIN lost_items li ON m.lost_item_id=li.id WHERE li.user_id=? AND m.status="pending"',[$uid])['c'] ?? 0;
$myClaims  = db()->fetchOne('SELECT COUNT(*) AS c FROM claims WHERE claimant_id=?',[$uid])['c'] ?? 0;

// Recent activity
$recentLost = db()->fetchAll(
    'SELECT li.*, c.name AS cat FROM lost_items li LEFT JOIN categories c ON li.category_id=c.id
     WHERE li.user_id=? ORDER BY li.created_at DESC LIMIT 5', [$uid]);
$recentMatches = db()->fetchAll(
    'SELECT m.*, li.item_name AS lost_name, fi.item_name AS found_name, fi.report_number AS found_rn
     FROM matches m LEFT JOIN lost_items li ON m.lost_item_id=li.id LEFT JOIN found_items fi ON m.found_item_id=fi.id
     WHERE li.user_id=? ORDER BY m.created_at DESC LIMIT 5', [$uid]);
$recentNotifs = getNotifications($uid, 5);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<div class="main-content">
  <div class="topbar topbar-clean">
    <button class="btn-icon btn-icon-surface me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title d-flex flex-column ms-1">
      <span class="fw-800" style="font-size: 0.95rem; font-family: 'Outfit'; color: var(--text); line-height: 1.2;">Student Dashboard</span>
      <span class="text-muted-sm fw-500">Stay organized, stay worry-free.</span>
    </div>
    <div class="topbar-actions">
      <div style="position:relative;">
        <a href="<?= BASE_URL ?>/student/notifications.php" class="btn-icon btn-icon-surface" id="notifBtnTop">
          <i class="bi bi-bell"></i>
        </a>
        <?php if ($notifCount > 0): ?>
        <span id="notifBadge" class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger"
              style="font-size:.6rem;padding:.25em .4em;"><?= $notifCount ?></span>
        <?php else: ?>
        <span id="notifBadge" class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger"
              style="font-size:.6rem;padding:.25em .4em;display:none;"></span>
        <?php endif; ?>
      </div>
      
      
    </div>
  </div>

  <div class="content-area dashboard-content-area animate-fade-in-up">
    <!-- Welcome Banner Card -->
    <div class="welcome-banner p-4 mb-4 position-relative overflow-hidden">
      <div class="welcome-banner-content">
        <h2 class="fw-800 mb-1 welcome-banner-title">Welcome back,<br><?= e(explode(' ', $user['name'])[0]) ?>! 👋</h2>
        <p class="mb-0 opacity-80 welcome-banner-subtitle"><?= date('l, F j, Y') ?></p>
      </div>
      <div class="welcome-banner-img-wrapper">
        <img src="<?= ASSETS_URL ?>/uploads/dashboard_illustration.png" alt="Illustration" class="welcome-banner-img">
      </div>
    </div>

    <!-- Quick Report Actions -->
    <div class="row g-3 mb-4">
      <div class="col-6">
        <a href="<?= BASE_URL ?>/student/lost_items.php?action=new" class="d-flex align-items-center gap-3 p-3 text-decoration-none quick-action-card">
          <div class="icon-circle-mint">
            <i class="bi bi-plus-lg text-primary" style="font-size: 1.1rem; -webkit-text-stroke: 0.5px;"></i>
          </div>
          <div>
            <div class="fw-700 text-primary" style="font-size: 0.95rem; font-family: 'Outfit';">Report Lost</div>
            <div class="text-muted d-none d-sm-block" style="font-size: 0.72rem;">Report an item you lost</div>
          </div>
        </a>
      </div>
      <div class="col-6">
        <a href="<?= BASE_URL ?>/student/found_items.php?action=new" class="d-flex align-items-center gap-3 p-3 text-decoration-none quick-action-card-primary">
          <div class="icon-circle-white">
            <i class="bi bi-plus-lg text-primary" style="font-size: 1.1rem; -webkit-text-stroke: 0.5px;"></i>
          </div>
          <div>
            <div class="fw-700 text-white" style="font-size: 0.95rem; font-family: 'Outfit';">Report Found</div>
            <div style="font-size: 0.72rem; color: rgba(255,255,255,0.85);" class="d-none d-sm-block">Report an item you found</div>
          </div>
        </a>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
      <?php
      $kpis = [
        ['bi-search','My Lost Reports',$myLost,'orange',BASE_URL.'/student/my_reports.php?type=lost', "Items you've reported lost"],
        ['bi-box-seam','My Found Reports',$myFound,'blue',BASE_URL.'/student/my_reports.php?type=found', "Items you've reported found"],
        ['bi-link-45deg','Pending Matches',$myMatches,'green',BASE_URL.'/student/matches.php', "Possible matches for you"],
        ['bi-file-earmark-check','My Claims',$myClaims,'purple',BASE_URL.'/student/claims.php', "Items you've claimed"],
      ];
      foreach ($kpis as [$ic,$lb,$vl,$cl,$hr,$sl]):
      ?>
      <div class="col-6 col-md-3 d-flex">
        <a href="<?= $hr ?>" class="stat-card stat-card-surface text-decoration-none d-flex align-items-center gap-3 p-3 w-100">
          <div class="stat-icon <?= $cl ?>" style="width: 44px; height: 44px; font-size: 1.25rem;"><i class="bi <?= $ic ?>"></i></div>
          <div class="stat-info">
            <div class="stat-value" style="font-size: 1.5rem; font-family: 'Outfit'; line-height: 1;"><?= number_format((int)$vl) ?></div>
            <div class="stat-label" style="font-size: 0.8rem; font-weight: 700; color: var(--text); line-height: 1.1; margin-top: 0.15rem;"><?= $lb ?></div>
            <div class="text-muted-sm d-none d-sm-block" style="margin-top: 0.1rem; line-height: 1.1;"><?= $sl ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Alerts: Pending Matches -->
    <?php if ($myMatches > 0): ?>
    <div class="alert alert-primary d-flex align-items-center gap-3 mb-4 animate-slide-down" style="border-radius:var(--radius-md);">
      <i class="bi bi-link-45deg" style="font-size:1.5rem;flex-shrink:0;"></i>
      <div>
        <strong><?= $myMatches ?> possible match<?= $myMatches>1?'es':'' ?> found!</strong>
        <div style="font-size:.85rem;">Check your match results — your lost item may have been found.</div>
      </div>
      <a href="<?= BASE_URL ?>/student/matches.php" class="btn btn-primary btn-sm ms-auto">Review</a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <!-- My Recent Reports -->
      <div class="col-lg-7">
        <div class="card stat-card-surface p-0 overflow-hidden">
          <div class="card-header card-header-clean d-flex justify-content-between align-items-center p-3">
            <span class="card-title fw-800 mb-0" style="font-size: 1.05rem; font-family: 'Outfit'; color: var(--text);"><i class="bi bi-journal-text me-2 text-primary"></i>My Recent Reports</span>
            <a href="<?= BASE_URL ?>/student/my_reports.php" class="btn btn-outline-primary btn-sm" style="border-radius: 20px; font-size: 0.78rem; padding: 0.25rem 0.85rem; border-color: var(--primary); color: var(--primary);">View All</a>
          </div>
          <div class="card-body p-4">
            <?php if (empty($recentLost)): ?>
            <div class="empty-state text-center py-4 d-flex flex-column align-items-center justify-content-center">
              <div class="mb-3 position-relative d-flex align-items-center justify-content-center" style="width: 76px; height: 76px; border-radius: 50%; background: rgba(167, 243, 208, 0.4);">
                <i class="bi bi-clipboard2-check text-primary" style="font-size: 2.2rem;"></i>
                <span style="position: absolute; bottom: 8px; right: -5px; font-size: 1.1rem;">🍃</span>
                <span style="position: absolute; top: 12px; left: -8px; font-size: 0.9rem;">🍃</span>
              </div>
              <h5 class="fw-800" style="font-size: 1.1rem; color: var(--text); font-family: 'Outfit'; margin-bottom: 0.35rem;">No reports yet</h5>
              <p class="text-muted mb-4" style="font-size: 0.85rem; max-width: 250px; margin: 0 auto 1rem;">Start by reporting a lost item.</p>
              <a href="<?= BASE_URL ?>/student/lost_items.php?action=new" class="btn btn-primary fw-700" style="background: var(--primary); border: none; border-radius: 30px; padding: 0.5rem 1.35rem; font-size: 0.82rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; color: #ffffff;">
                <span style="font-size: 1.1rem; font-weight: 800; line-height: 1; display: inline-flex; align-items: center; margin-right: 0.15rem;">+</span> Report Lost Item
              </a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0 table-mobile-cards">
                <thead><tr><th>Report #</th><th>Item</th><th>Category</th><th>Date Lost</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentLost as $item): ?>
                  <tr>
                    <td data-label="Report #"><code style="font-size:.72rem;"><?= e($item['report_number']) ?></code></td>
                    <td data-label="Item" class="fw-600" style="font-size:.85rem;"><?= e($item['item_name']) ?></td>
                    <td data-label="Category" style="font-size:.78rem;color:var(--text-muted);"><?= e($item['cat']) ?></td>
                    <td data-label="Date Lost" style="font-size:.78rem;"><?= formatDate($item['date_lost']) ?></td>
                    <td data-label="Status"><?= statusBadge($item['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Notifications -->
      <div class="col-lg-5">
        <div class="card stat-card-surface p-0 overflow-hidden">
          <div class="card-header card-header-clean d-flex justify-content-between align-items-center p-3">
            <span class="card-title fw-800 mb-0" style="font-size: 1.05rem; font-family: 'Outfit'; color: var(--text);">
              <i class="bi bi-bell-fill me-2 text-primary"></i>Notifications
              <?php if ($notifCount > 0): ?><span class="nav-badge" style="position:static;display:inline-block;margin-left: 0.35rem;"><?= $notifCount ?></span><?php endif; ?>
            </span>
            <a href="<?= BASE_URL ?>/student/notifications.php" class="btn btn-outline-primary btn-sm" style="border-radius: 20px; font-size: 0.78rem; padding: 0.25rem 0.85rem; border-color: var(--primary); color: var(--primary);">View All</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentNotifs)): ?>
            <div class="text-center py-5 d-flex flex-column align-items-center">
              <div class="mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%; background: var(--surface-2);">
                <i class="bi bi-bell text-muted" style="font-size: 1.5rem;"></i>
              </div>
              <h5 class="fw-800" style="font-size: 0.95rem; color: var(--text-muted); font-family: 'Outfit';">No notifications</h5>
            </div>
            <?php else: ?>
            <div class="notif-list">
              <?php foreach ($recentNotifs as $n): ?>
              <div class="notif-item <?= $n['is_read']?'':'unread' ?> d-flex align-items-start gap-3 p-3" style="border-bottom: 1px solid var(--border); transition: background 0.2s;">
                <div class="notif-icon <?= e($n['type']) ?>" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: <?= $n['type']==='match'?'#e0f2fe':($n['type']==='success'?'#d1fae5':'#fef3c7') ?>; color: <?= $n['type']==='match'?'#0369a1':($n['type']==='success'?'#047857':'#b45309') ?>;">
                  <i class="bi <?= $n['type']==='match'?'bi-link-45deg':($n['type']==='success'?'bi-check-circle':'bi-bell') ?>" style="font-size: 1.1rem;"></i>
                </div>
                <div class="notif-body flex-grow-1">
                  <div class="notif-title fw-700" style="font-size: 0.88rem; color: var(--text);"><?= e($n['title']) ?></div>
                  <div class="notif-msg text-muted" style="font-size: 0.78rem; line-height: 1.4; margin-top: 0.1rem;"><?= snippet($n['message'], 80) ?></div>
                  <div class="notif-time text-muted d-flex align-items-center gap-1" style="font-size: 0.72rem; margin-top: 0.35rem;">
                    <i class="bi bi-clock"></i> <?= timeAgo($n['created_at']) ?>
                  </div>
                </div>
                <?php if (!$n['is_read']): ?>
                <div class="unread-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; margin-top: 0.5rem; flex-shrink: 0;"></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Matches -->
    <?php if (!empty($recentMatches)): ?>
    <div class="card stat-card-surface mt-4 p-0 overflow-hidden">
      <div class="card-header card-header-clean d-flex justify-content-between align-items-center p-3">
        <span class="card-title fw-800 mb-0" style="font-size: 1.05rem; font-family: 'Outfit'; color: var(--text);"><i class="bi bi-link-45deg me-2 text-primary"></i>My Matches</span>
        <a href="<?= BASE_URL ?>/student/matches.php" class="btn btn-outline-primary btn-sm" style="border-radius: 20px; font-size: 0.78rem; padding: 0.25rem 0.85rem; border-color: var(--primary); color: var(--primary);">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead><tr><th>Your Lost Item</th><th>Matched Found Item</th><th>Score</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recentMatches as $m): ?>
              <tr>
                <td data-label="Lost Item" class="fw-600" style="font-size:.85rem;"><?= e($m['lost_name']) ?></td>
                <td data-label="Found Item" style="font-size:.85rem;"><?= e($m['found_name']) ?> <code style="font-size:.7rem;"><?= e($m['found_rn']) ?></code></td>
                <td data-label="Score">
                  <strong style="color:var(--primary)"><?= number_format($m['similarity_score'],1) ?>%</strong>
                </td>
                <td data-label="Status"><?= statusBadge($m['status']) ?></td>
                <td data-label="Date" style="font-size:.78rem;"><?= timeAgo($m['created_at']) ?></td>
                <td data-label="">
                  <a href="<?= BASE_URL ?>/student/matches.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                    <i class="bi bi-eye"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
