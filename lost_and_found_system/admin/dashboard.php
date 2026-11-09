<?php
/**
 * Admin Dashboard
 */
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/claim_ml.php';

requireRole('admin');

$mlReady = claimMl()->schemaReady();
$claimAssessments = $mlReady ? db()->fetchAll(
    'SELECT c.id,c.status,c.created_at,fi.item_name,u.name AS claimant_name,a.classification,a.evidence_json,a.prediction_reason
     FROM claims c JOIN found_items fi ON fi.id=c.found_item_id JOIN users u ON u.id=c.claimant_id
     LEFT JOIN claim_assessments a ON a.claim_id=c.id ORDER BY c.created_at DESC,c.id DESC LIMIT 8'
) : [];
$activeTree = $mlReady ? db()->fetchOne('SELECT version FROM decision_tree_models WHERE is_active=1 ORDER BY id DESC LIMIT 1') : false;

// ─── KPI Stats ───────────────────────────────────────────────
$stats = [
    'total_lost'    => db()->fetchOne('SELECT COUNT(*) AS c FROM lost_items')['c']  ?? 0,
    'total_found'   => db()->fetchOne('SELECT COUNT(*) AS c FROM found_items')['c'] ?? 0,
    'total_matched' => db()->fetchOne('SELECT COUNT(*) AS c FROM matches WHERE status = "confirmed"')['c'] ?? 0,
    'total_claimed' => db()->fetchOne('SELECT COUNT(*) AS c FROM claims WHERE status = "approved"')['c']  ?? 0,
    'pending_claims'=> db()->fetchOne('SELECT COUNT(*) AS c FROM claims WHERE status = "pending"')['c']   ?? 0,
    'released'      => db()->fetchOne('SELECT COUNT(*) AS c FROM releases')['c'] ?? 0,
];

// ─── Monthly trend (last 6 months) ───────────────────────────
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = strtotime("first day of -{$i} months");
    $month   = date('Y-m', $monthStart);
    $label   = date('M Y', $monthStart);
    $lost    = db()->fetchOne('SELECT COUNT(*) AS c FROM lost_items  WHERE DATE_FORMAT(created_at,"%Y-%m") = ?', [$month])['c'] ?? 0;
    $found   = db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE DATE_FORMAT(created_at,"%Y-%m") = ?', [$month])['c'] ?? 0;
    $monthlyData[] = ['label' => $label, 'lost' => $lost, 'found' => $found];
}

// ─── Category breakdown ───────────────────────────────────────
$catStats = db()->fetchAll(
    'SELECT c.name, COUNT(li.id) AS cnt FROM categories c
     LEFT JOIN lost_items li ON li.category_id = c.id
     GROUP BY c.id, c.name ORDER BY cnt DESC LIMIT 8'
);

// ─── Recent lost items ────────────────────────────────────────
$recentLost = db()->fetchAll(
    'SELECT li.*, u.name AS reporter_name, c.name AS category_name, l.name AS location_name
     FROM lost_items li
     LEFT JOIN users      u ON li.user_id      = u.id
     LEFT JOIN categories c ON li.category_id  = c.id
     LEFT JOIN locations  l ON li.location_id  = l.id
     ORDER BY li.created_at DESC LIMIT 8'
);

// ─── Recent found items ───────────────────────────────────────
$recentFound = db()->fetchAll(
    'SELECT fi.*, u.name AS reporter_name, c.name AS category_name
     FROM found_items fi
     LEFT JOIN users      u ON fi.user_id     = u.id
     LEFT JOIN categories c ON fi.category_id = c.id
     WHERE fi.status != "released"
     ORDER BY fi.created_at DESC LIMIT 5'
);

// ─── Recent audit logs ────────────────────────────────────────
$recentLogs = db()->fetchAll(
    'SELECT al.*, u.name AS user_name FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC LIMIT 6'
);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>

  <div class="content-area">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Campus lost &amp; found</h1>
        <p class="page-subtitle">Reports, ownership reviews, and returns · <?= date('l, F j, Y') ?></p>
      </div>
      <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i> View reports</a>
    </div>

    <section class="dashboard-banner" aria-labelledby="review-queue-title">
      <div>
        <span class="eyebrow">THE NEXT STEP IS YOURS</span>
        <h2 id="review-queue-title"><?= $stats['pending_claims'] ? number_format((int)$stats['pending_claims']) . ' claim' . ((int)$stats['pending_claims'] === 1 ? '' : 's') . ' awaiting your review.' : 'Your review queue is up to date.' ?></h2>
        <p><?= $stats['pending_claims'] ? 'Help an item find its owner. Review the evidence and record an ownership decision.' : 'New ownership claims will appear here. Keep track of reports and returns across campus.' ?></p>
        <a href="<?= BASE_URL ?>/admin/claims.php?status=pending" class="btn btn-primary">Review claims <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      </div>
      <ol class="recovery-steps" aria-label="Review workflow">
        <li><i class="bi bi-file-earmark-text" aria-hidden="true"></i><span><strong>Check the evidence</strong><small>Review identifying details</small></span></li>
        <li><i class="bi bi-shield-check" aria-hidden="true"></i><span><strong>Verify ownership</strong><small>Record the final decision</small></span></li>
        <li><i class="bi bi-bag-check" aria-hidden="true"></i><span><strong>Arrange the return</strong><small>Security handles collection</small></span></li>
      </ol>
    </section>

    <!-- Live summary counts -->
    <div class="row stats-grid mb-4">
      <?php
      $kpis = [
        ['icon'=>'bi-search',            'label'=>'Total Lost Reports',  'value'=>$stats['total_lost'],    'color'=>'orange', 'href'=>BASE_URL.'/admin/reports.php?type=lost'],
        ['icon'=>'bi-box-seam',          'label'=>'Total Found Reports', 'value'=>$stats['total_found'],   'color'=>'blue',   'href'=>BASE_URL.'/admin/reports.php?type=found'],
        ['icon'=>'bi-link-45deg',        'label'=>'Confirmed Matches',   'value'=>$stats['total_matched'], 'color'=>'green',  'href'=>BASE_URL.'/admin/reports.php?type=match'],
        ['icon'=>'bi-file-earmark-check','label'=>'Approved Claims',     'value'=>$stats['total_claimed'], 'color'=>'purple', 'href'=>BASE_URL.'/admin/claims.php?status=approved'],
        ['icon'=>'bi-hourglass-split',   'label'=>'Pending Claims',      'value'=>$stats['pending_claims'],'color'=>'red',    'href'=>BASE_URL.'/admin/claims.php?status=pending'],
        ['icon'=>'bi-check2-circle',     'label'=>'Items Released',      'value'=>$stats['released'],      'color'=>'teal',   'href'=>BASE_URL.'/admin/reports.php?type=released'],
      ];
      foreach ($kpis as $k): ?>
      <div class="col-6 col-md-4 col-xl-2 d-flex">
        <a href="<?= $k['href'] ?>" class="stat-card <?= $k['color'] ?> text-decoration-none w-100">
          <i class="bi bi-arrow-up-right stat-link-arrow" aria-hidden="true"></i>
          <div class="stat-icon <?= $k['color'] ?>"><i class="bi <?= $k['icon'] ?>"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format((int)$k['value']) ?></div>
            <div class="stat-label"><?= $k['label'] ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="card mb-4">
      <div class="card-header">
        <h2 class="card-title">Recent ownership claims</h2>
        <a href="<?= BASE_URL ?>/admin/decision_tree.php" class="text-link small">Decision Tree training &amp; evaluation</a>
      </div>
      <div class="card-body">
        <p class="small mb-2">Review each claimant's evidence before recording an ownership decision.</p>
        <?php if (!$mlReady): ?>
        <div class="alert alert-warning mb-0">Setup required: run <code>php scripts/migrate_decision_tree.php</code>.</div>
        <?php elseif (!$activeTree): ?>
        <div class="alert alert-info mb-0">No trained model is active. Claims are collected for manual verification; no model accuracy is available yet.</div>
        <?php else: ?>
        <small class="text-muted">Active model: <?= e($activeTree['version']) ?></small>
        <?php endif; ?>
      </div>
      <?php if ($mlReady): ?>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th scope="col">Item / claim</th><th scope="col">Claimant</th><th scope="col">Recommendation</th><th scope="col">Status</th><th scope="col">Submitted</th><th scope="col"><span class="visually-hidden">Action</span></th></tr></thead>
          <tbody>
          <?php foreach ($claimAssessments as $assessment): ?>
            <tr>
              <td><strong class="fw-600"><?= e($assessment['item_name']) ?></strong><div class="text-muted-sm">Claim #<?= (int)$assessment['id'] ?></div></td><td><?= e($assessment['claimant_name']) ?></td>
              <td><?= claimClassificationBadge($assessment['classification']) ?></td>
              <td><?= statusBadge($assessment['status']) ?></td><td class="text-nowrap"><?= formatDate($assessment['created_at'], 'M j, Y') ?></td>
              <td><a href="<?= BASE_URL ?>/admin/claim_review.php?id=<?= $assessment['id'] ?>" class="btn btn-sm btn-outline-primary" aria-label="Review claim #<?= (int)$assessment['id'] ?>">Review <i class="bi bi-arrow-right" aria-hidden="true"></i></a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$claimAssessments): ?><tr><td colspan="6" class="text-center text-muted py-3">No claims submitted.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
      <!-- Monthly Trend -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header">
            <h2 class="card-title">Monthly reports · last 6 months</h2>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="monthlyChart" role="img" aria-label="Lost and found reports by month. Exact values are in the table below."></canvas>
            </div>
            <details class="chart-data"><summary>View monthly report counts</summary>
              <div class="table-responsive"><table class="table table-sm"><thead><tr><th scope="col">Month</th><th scope="col">Lost</th><th scope="col">Found</th></tr></thead><tbody>
                <?php foreach ($monthlyData as $month): ?><tr><th scope="row"><?= e($month['label']) ?></th><td><?= (int)$month['lost'] ?></td><td><?= (int)$month['found'] ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </details>
          </div>
        </div>
      </div>

      <!-- Category Pie -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <h2 class="card-title">Lost reports by category</h2>
          </div>
          <div class="card-body">
            <div class="chart-container">
              <canvas id="categoryChart" role="img" aria-label="Lost reports by category. Exact values are in the table below."></canvas>
            </div>
            <details class="chart-data"><summary>View category counts</summary>
              <div class="table-responsive"><table class="table table-sm"><thead><tr><th scope="col">Category</th><th scope="col">Reports</th></tr></thead><tbody>
                <?php foreach ($catStats as $category): ?><tr><th scope="row"><?= e($category['name']) ?></th><td><?= (int)$category['cnt'] ?></td></tr><?php endforeach; ?>
              </tbody></table></div>
            </details>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Tables -->
    <div class="row g-3 mb-4">
      <!-- Recent Lost Items -->
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-search"></i> Recent Lost Item Reports</span>
            <a href="<?= BASE_URL ?>/admin/reports.php?type=lost" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>Report #</th>
                    <th>Item</th>
                    <th>Reporter</th>
                    <th>Date</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentLost as $item): ?>
                  <tr>
                    <td><code><?= e($item['report_number']) ?></code></td>
                    <td>
                      <div class="fw-600"><?= e($item['item_name']) ?></div>
                      <div class="text-muted-sm"><?= e($item['category_name']) ?></div>
                    </td>
                    <td><?= e($item['reporter_name']) ?></td>
                    <td class="text-nowrap"><?= formatDate($item['date_lost']) ?></td>
                    <td><?= statusBadge($item['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentLost)): ?>
                  <tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-search"></i><h5>No lost reports yet</h5><p>Student reports will appear here.</p><a href="<?= BASE_URL ?>/admin/reports.php?type=lost" class="btn btn-outline-primary btn-sm">View lost items</a></div></td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Audit + Found summary -->
      <div class="col-lg-5">
        <div class="card mb-3">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-journal-text"></i> Recent Activity</span>
            <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="btn btn-sm btn-outline-primary">View Log</a>
          </div>
          <div class="card-body p-0">
            <?php foreach ($recentLogs as $log): ?>
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
              <div class="stat-icon icon-sm">
                <i class="bi bi-activity"></i>
              </div>
              <div class="flex-1">
                <div class="small fw-600"><?= e(str_replace('_',' ',$log['action'])) ?></div>
                <div class="text-muted-sm"><?= e($log['user_name'] ?? 'System') ?> · <?= timeAgo($log['created_at']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLogs)): ?>
            <div class="p-3 text-center text-muted-sm">No activity logged yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.content-area -->
</main>

<?php
$monthLabels  = json_encode(array_column($monthlyData, 'label'));
$monthLost    = json_encode(array_column($monthlyData, 'lost'));
$monthFound   = json_encode(array_column($monthlyData, 'found'));
$catLabels    = json_encode(array_column($catStats, 'name'));
$catValues    = json_encode(array_column($catStats, 'cnt'));
$extraScripts = <<<JS
<script>
// Monthly trend chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: {$monthLabels},
        datasets: [
          { label:'Lost', data:{$monthLost}, backgroundColor:chartPalette().neutral, lafTone:'neutral', borderRadius:4 },
          { label:'Found', data:{$monthFound}, backgroundColor:chartPalette().accent, lafTone:'accent', borderRadius:4 },
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top' } },
        scales:{ x:{ grid:{ display:false } }, y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
    }
});

// Horizontal bars keep category names readable without a color-coded legend.
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: {$catLabels},
        datasets:[{ label:'Lost reports', data:{$catValues}, backgroundColor:chartPalette().neutral, lafTone:'neutral', borderRadius:4 }]
    },
    options: {
        indexAxis:'y', responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false } },
        scales:{ x:{ beginAtZero:true, ticks:{ precision:0 } }, y:{ grid:{ display:false } } }
    }
});
</script>
JS;
include __DIR__ . '/../includes/footer.php';
