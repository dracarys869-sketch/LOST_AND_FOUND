<?php
/**
 * Security — Dashboard
 */
$pageTitle  = 'Security Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('security', 'admin');

$stats = [
    'active_found'    => db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE status="active"')['c']   ?? 0,
    'pending_claims'  => db()->fetchOne('SELECT COUNT(*) AS c FROM claims WHERE status="pending"')['c']       ?? 0,
    'released_today'  => db()->fetchOne('SELECT COUNT(*) AS c FROM releases WHERE DATE(release_date)=CURDATE()')['c'] ?? 0,
    'total_in_storage'=> db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE status IN ("active","matched","claimed")')['c'] ?? 0,
];

$recentFound = db()->fetchAll(
    'SELECT fi.*, c.name AS cat FROM found_items fi LEFT JOIN categories c ON fi.category_id=c.id WHERE fi.status != "released" ORDER BY fi.created_at DESC LIMIT 8'
);
$pendingClaims = db()->fetchAll(
    'SELECT c.*, fi.item_name, u.name AS claimant_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id LEFT JOIN users u ON c.claimant_id=u.id WHERE c.status="pending" ORDER BY c.created_at ASC LIMIT 5'
);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> Dashboard</div>
    <div class="topbar-actions">
      <a href="<?= BASE_URL ?>/security/qr_scanner.php" class="btn btn-primary btn-sm">
        <i class="bi bi-qr-code-scan me-1"></i> QR Scanner
      </a>
      
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Security Dashboard</h1>
        <p class="page-subtitle">Manage found items, claims, and releases — <?= date('M j, Y') ?></p>
      </div>
      <a href="<?= BASE_URL ?>/security/found_items.php?action=new" class="btn btn-primary btn-sm">
        <i class="bi bi-plus me-1"></i> Log Found Item
      </a>
    </div>

    <div class="row g-3 mb-4">
      <?php
      $kpis = [
        ['bi-box-seam','Active Found Items',$stats['active_found'],'blue',BASE_URL.'/security/found_items.php'],
        ['bi-hourglass-split','Pending Claims',$stats['pending_claims'],'orange',BASE_URL.'/security/claims.php?status=pending'],
        ['bi-archive','In Storage',$stats['total_in_storage'],'green',BASE_URL.'/security/storage.php'],
        ['bi-check2-circle','Released Today',$stats['released_today'],'teal',BASE_URL.'/security/release.php'],
      ];
      foreach ($kpis as [$ic,$lb,$vl,$cl,$hr]):
      ?>
      <div class="col-6 col-md-3 d-flex">
        <a href="<?= $hr ?>" class="stat-card <?= $cl ?> text-decoration-none w-100">
          <div class="stat-icon <?= $cl ?>"><i class="bi <?= $ic ?>"></i></div>
          <div class="stat-info">
            <div class="stat-value"><?= number_format((int)$vl) ?></div>
            <div class="stat-label"><?= $lb ?></div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($stats['pending_claims'] > 0): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
      <i class="bi bi-hourglass-split" style="font-size:1.5rem;flex-shrink:0;"></i>
      <div>
        <strong><?= $stats['pending_claims'] ?> claim<?= $stats['pending_claims']>1?'s':'' ?> awaiting review</strong>
        <div style="font-size:.85rem;">Students are waiting for their claims to be processed.</div>
      </div>
      <a href="<?= BASE_URL ?>/security/claims.php?status=pending" class="btn btn-warning btn-sm ms-auto">Review Claims</a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-box-seam"></i> Recent Found Items</span>
            <a href="<?= BASE_URL ?>/security/found_items.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr><th>Report #</th><th>Item</th><th>Category</th><th>Status</th><th>QR</th></tr></thead>
                <tbody>
                  <?php foreach ($recentFound as $fi): ?>
                  <tr>
                    <td><code style="font-size:.72rem;"><?= e($fi['report_number']) ?></code></td>
                    <td class="fw-600" style="font-size:.85rem;"><?= e($fi['item_name']) ?></td>
                    <td style="font-size:.8rem;"><?= e($fi['cat']) ?></td>
                    <td><?= statusBadge($fi['status']) ?></td>
                    <td>
                      <?php if ($fi['qr_code_path']): ?>
                      <i class="bi bi-qr-code text-primary"></i>
                      <?php else: ?>
                      <span class="text-muted" style="font-size:.75rem;">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentFound)): ?><tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-inbox"></i><h5>No found items logged yet</h5></div></td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-hourglass-split"></i> Pending Claims</span>
            <a href="<?= BASE_URL ?>/security/claims.php" class="btn btn-sm btn-outline-primary">All Claims</a>
          </div>
          <?php if (empty($pendingClaims)): ?>
          <div class="card-body"><div class="empty-state py-3"><i class="bi bi-check-circle text-success" style="font-size:2rem;"></i><h5>No pending claims</h5></div></div>
          <?php else: ?>
          <?php foreach ($pendingClaims as $claim): ?>
          <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <div class="stat-icon orange" style="width:32px;height:32px;font-size:.8rem;flex-shrink:0;">
              <i class="bi bi-file-earmark"></i>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="fw-600 truncate" style="font-size:.83rem;"><?= e($claim['item_name']) ?></div>
              <div style="font-size:.73rem;color:var(--text-muted);">by <?= e($claim['claimant_name']) ?> · <?= timeAgo($claim['created_at']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/security/claims.php?focus=<?= $claim['id'] ?>" class="btn btn-sm btn-primary py-0 px-2">Review</a>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
