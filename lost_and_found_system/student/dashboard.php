<?php
/**
 * Student Dashboard
 */
$pageTitle  = 'Overview';
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
     WHERE li.user_id=? ORDER BY li.created_at DESC,li.id DESC LIMIT 5', [$uid]);
$recentMatches = db()->fetchAll(
    'SELECT m.*, li.item_name AS lost_name, fi.item_name AS found_name, fi.report_number AS found_rn
     FROM matches m LEFT JOIN lost_items li ON m.lost_item_id=li.id LEFT JOIN found_items fi ON m.found_item_id=fi.id
      WHERE li.user_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT 5', [$uid]);
$recentNotifs = getNotifications($uid, 5);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>

  <div class="content-area">
    <div class="workspace-intro">
      <div>
        <h1>Welcome back, <?= e(explode(' ', trim($user['name']))[0]) ?>.</h1>
        <p>Your reports, updates, and a little help finding what's missing.</p>
      </div>
      <time class="workspace-date" datetime="<?= date('Y-m-d') ?>"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('D, M j, Y') ?></time>
    </div>

    <section class="dashboard-banner" aria-labelledby="next-step-title">
      <div>
        <span class="eyebrow">LET'S GET IT BACK TO YOU</span>
        <?php if ($myMatches > 0): ?>
        <h2 id="next-step-title"><?= (int)$myMatches ?> possible match<?= $myMatches > 1 ? 'es' : '' ?>. One step closer.</h2>
        <p>A found item may match your report. Compare the details before filing an ownership claim.</p>
        <?php else: ?>
        <h2 id="next-step-title">Missing something? You're in the right place.</h2>
        <p>Tell us what went missing and where. We'll let you know when a found item may be a match.</p>
        <?php endif; ?>
        <div class="action-row">
          <?php if ($myMatches > 0): ?>
          <a href="<?= BASE_URL ?>/student/matches.php" class="btn btn-primary">Review matches <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/student/lost_items.php?action=new" class="btn <?= $myMatches > 0 ? 'btn-outline-secondary' : 'btn-primary' ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report lost item</a>
          <a href="<?= BASE_URL ?>/student/browse.php" class="btn btn-outline-secondary">Browse found items</a>
        </div>
      </div>
      <ol class="recovery-steps" aria-label="The recovery process">
        <li><i class="bi bi-pencil-square" aria-hidden="true"></i><span><strong>Report it</strong><small>A few details make a difference</small></span></li>
        <li><i class="bi bi-intersect" aria-hidden="true"></i><span><strong>Find a match</strong><small>Check your possible matches</small></span></li>
        <li><i class="bi bi-bag-check" aria-hidden="true"></i><span><strong>Bring it home</strong><small>Claim and collect your item</small></span></li>
      </ol>
    </section>

    <div class="dashboard-section-label"><h2>Your activity at a glance</h2><a href="<?= BASE_URL ?>/student/found_items.php" class="text-link">Found something? Report it <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>

    <!-- KPI Cards -->
    <div class="row stats-grid mb-4">
      <?php
      $kpis = [
        ['bi-search','Lost reports',$myLost,BASE_URL.'/student/my_reports.php?type=lost'],
        ['bi-box-seam','Found reports',$myFound,BASE_URL.'/student/my_reports.php?type=found'],
        ['bi-link-45deg','Pending matches',$myMatches,BASE_URL.'/student/matches.php'],
        ['bi-file-earmark-check','My claims',$myClaims,BASE_URL.'/student/claims.php'],
      ];
      foreach ($kpis as [$ic,$lb,$vl,$hr]):
      ?>
      <div class="col-6 col-md-3 d-flex">
        <a href="<?= $hr ?>" class="stat-card text-decoration-none w-100">
          <i class="bi bi-arrow-up-right stat-link-arrow" aria-hidden="true"></i>
          <div class="stat-icon"><i class="bi <?= $ic ?>" aria-hidden="true"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format((int)$vl) ?></div>
            <div class="stat-label"><?= $lb ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-4">
      <!-- My Recent Reports -->
      <div class="col-lg-8">
        <section class="card h-100" aria-labelledby="recent-reports-title">
          <div class="card-header">
            <h2 class="card-title" id="recent-reports-title">Recent lost reports</h2>
            <a href="<?= BASE_URL ?>/student/my_reports.php" class="text-link small">All reports</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentLost)): ?>
            <div class="empty-state">
              <i class="bi bi-journal-text" aria-hidden="true"></i>
              <h3>No lost reports yet</h3>
              <p>Your submitted reports and their status will appear here.</p>
              <a href="<?= BASE_URL ?>/student/lost_items.php?action=new" class="text-link">Report a lost item</a>
            </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0 table-mobile-cards">
                <thead><tr><th scope="col">Item / report</th><th scope="col">Category</th><th scope="col">Date lost</th><th scope="col">Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentLost as $item): ?>
                  <tr>
                    <td data-label="Item"><div class="report-cell"><span class="report-cell-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><span><strong><?= e($item['item_name']) ?></strong><code><?= e($item['report_number']) ?></code></span></div></td>
                    <td data-label="Category" class="text-muted"><?= e($item['cat']) ?></td>
                    <td data-label="Date Lost" class="text-nowrap"><?= formatDate($item['date_lost']) ?></td>
                    <td data-label="Status"><?= statusBadge($item['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </div>
        </section>
      </div>

      <!-- Notifications -->
      <div class="col-lg-4">
        <section class="card h-100" aria-labelledby="notifications-title">
          <div class="card-header">
            <h2 class="card-title" id="notifications-title">Notifications</h2>
            <a href="<?= BASE_URL ?>/student/notifications.php" class="text-link small">View all</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($recentNotifs)): ?>
            <div class="empty-state">
              <i class="bi bi-bell" aria-hidden="true"></i>
              <h3>No notifications</h3>
            </div>
            <?php else: ?>
            <div class="notif-list">
              <?php foreach ($recentNotifs as $n): ?>
              <a href="<?= e(notificationUrl($n, 'student')) ?>" class="notif-item <?= $n['is_read']?'':'unread' ?>">
                <div class="notif-icon <?= e($n['type']) ?>">
                  <i class="bi <?= $n['type']==='match'?'bi-link-45deg':($n['type']==='success'?'bi-check-circle':'bi-bell') ?>" aria-hidden="true"></i>
                </div>
                <div class="notif-body flex-grow-1">
                  <div class="notif-title"><?= e($n['title']) ?></div>
                  <div class="notif-msg"><?= snippet($n['message'], 80) ?></div>
                  <div class="notif-time">
                    <i class="bi bi-clock"></i> <?= timeAgo($n['created_at']) ?>
                  </div>
                </div>
                <?php if (!$n['is_read']): ?>
                <span class="unread-dot" role="img" aria-label="Unread"></span>
                <?php endif; ?>
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>

    <!-- Recent Matches -->
    <?php if (!empty($recentMatches)): ?>
    <section class="card mt-4" aria-labelledby="matches-title">
      <div class="card-header">
        <h2 class="card-title" id="matches-title">Recent matches</h2>
        <a href="<?= BASE_URL ?>/student/matches.php" class="text-link small">All matches</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead><tr><th>Your Lost Item</th><th>Matched Found Item</th><th>Score</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recentMatches as $m): ?>
              <tr>
                <td data-label="Lost Item" class="fw-600"><?= e($m['lost_name']) ?></td>
                <td data-label="Found Item"><?= e($m['found_name']) ?> <code><?= e($m['found_rn']) ?></code></td>
                <td data-label="Score">
                  <strong class="text-primary"><?= number_format($m['similarity_score'],1) ?>%</strong>
                </td>
                <td data-label="Status"><?= statusBadge($m['status']) ?></td>
                <td data-label="Date"><?= timeAgo($m['created_at']) ?></td>
                <td data-label="">
                  <a href="<?= BASE_URL ?>/student/matches.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" aria-label="View match for <?= e($m['lost_name']) ?>">
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
