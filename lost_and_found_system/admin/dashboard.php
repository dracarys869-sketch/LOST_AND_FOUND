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

requireRole('admin');

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
    $month   = date('Y-m', strtotime("-{$i} months"));
    $label   = date('M Y', strtotime("-{$i} months"));
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

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <div class="topbar-title">
      <span class="text-muted-sm">Admin /</span> Dashboard
    </div>
    <div class="topbar-actions">
      <div style="position:relative;">
        <a href="<?= BASE_URL ?>/student/notifications.php" class="btn-icon" id="notifBtn">
          <i class="bi bi-bell"></i>
        </a>
        <?php if ($notifCount > 0): ?>
        <span class="badge-dot position-absolute top-0 end-0 translate-middle badge rounded-pill bg-danger"
              style="font-size:.6rem;padding:.25em .4em;">
          <?= $notifCount ?>
        </span>
        <?php endif; ?>
      </div>
      
      
    </div>
  </div>

  <div class="content-area">
    <!-- Page Header -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Overview of the Lost &amp; Found system — <?= date('l, F j, Y') ?></p>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
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
      <div class="col-6 col-lg-4 col-xl-2 d-flex">
        <a href="<?= $k['href'] ?>" class="stat-card <?= $k['color'] ?> text-decoration-none w-100">
          <div class="stat-icon <?= $k['color'] ?>"><i class="bi <?= $k['icon'] ?>"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format((int)$k['value']) ?></div>
            <div class="stat-label"><?= $k['label'] ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
      <!-- Monthly Trend -->
      <div class="col-lg-8">
        <div class="card h-100">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-bar-chart-line"></i> Monthly Trend (Last 6 Months)</span>
          </div>
          <div class="card-body">
            <div class="chart-container" style="height:240px;">
              <canvas id="monthlyChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Pie -->
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-pie-chart"></i> Lost by Category</span>
          </div>
          <div class="card-body d-flex align-items-center justify-content-center">
            <div class="chart-container" style="height:220px;width:100%;">
              <canvas id="categoryChart"></canvas>
            </div>
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
                    <td><code style="font-size:.75rem;"><?= e($item['report_number']) ?></code></td>
                    <td>
                      <div class="fw-600" style="font-size:.85rem;"><?= e($item['item_name']) ?></div>
                      <div style="font-size:.72rem;color:var(--text-muted);"><?= e($item['category_name']) ?></div>
                    </td>
                    <td style="font-size:.82rem;"><?= e($item['reporter_name']) ?></td>
                    <td style="font-size:.78rem;white-space:nowrap;"><?= formatDate($item['date_lost']) ?></td>
                    <td><?= statusBadge($item['status']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentLost)): ?>
                  <tr><td colspan="5" class="text-center py-4 text-muted">No reports yet.</td></tr>
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
              <div class="stat-icon green" style="width:30px;height:30px;font-size:.75rem;flex-shrink:0;">
                <i class="bi bi-activity"></i>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="font-size:.78rem;font-weight:600;"><?= e(str_replace('_',' ',$log['action'])) ?></div>
                <div style="font-size:.72rem;color:var(--text-muted);"><?= e($log['user_name'] ?? 'System') ?> · <?= timeAgo($log['created_at']) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recentLogs)): ?>
            <div class="p-3 text-center text-muted" style="font-size:.85rem;">No activity logged yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.content-area -->
</div><!-- /.main-content -->

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
          { label:'Lost',  data:{$monthLost},  backgroundColor:'rgba(253,126,20,.7)', borderRadius:6 },
          { label:'Found', data:{$monthFound}, backgroundColor:'rgba(45,106,79,.7)',  borderRadius:6 },
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'top' } },
        scales:{ x:{ grid:{ display:false } }, y:{ beginAtZero:true, ticks:{ stepSize:1 } } }
    }
});

// Category pie chart
new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
        labels: {$catLabels},
        datasets:[{ data:{$catValues},
            backgroundColor:['#065F46','#059669','#10B981','#34D399','#A7F3D0','#D1FAE5','#FCE7F3','#044030'],
            borderWidth:2, borderColor:'var(--surface)'
        }]
    },
    options: {
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom', labels:{ font:{size:11}, boxWidth:12 } } }
    }
});
</script>
JS;
include __DIR__ . '/../includes/footer.php';
