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
$pendingClaimRows = db()->fetchAll(
    'SELECT c.*, fi.item_name, u.name AS claimant_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id LEFT JOIN users u ON c.claimant_id=u.id WHERE c.status="pending" ORDER BY c.created_at ASC LIMIT 5'
);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>

  <div class="content-area">
    <div class="workspace-intro">
      <div>
        <h1>Found items &amp; handovers</h1>
        <p>Log belongings in custody, verify ownership evidence, and record approved returns.</p>
      </div>
      <time class="workspace-date" datetime="<?= date('Y-m-d') ?>"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('D, M j, Y') ?></time>
    </div>

    <section class="dashboard-banner" aria-labelledby="custody-title">
      <div>
        <span class="eyebrow">EVERY ITEM HAS A WAY HOME</span>
        <h2 id="custody-title">A careful handover starts here.</h2>
        <p>Log a found item, record where it's stored, and help return it to the right person.</p>
        <div class="action-row">
          <a href="<?= BASE_URL ?>/security/found_items.php?action=new" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Log found item</a>
          <a href="<?= BASE_URL ?>/security/qr_scanner.php" class="btn btn-outline-secondary"><i class="bi bi-qr-code-scan" aria-hidden="true"></i> Scan QR code</a>
          <a href="<?= BASE_URL ?>/student/lost_items.php" class="btn btn-outline-secondary">Report lost item</a>
        </div>
      </div>
      <ol class="recovery-steps" aria-label="Custody workflow">
        <li><i class="bi bi-box-seam" aria-hidden="true"></i><span><strong>Log &amp; store</strong><small>Keep a clear custody record</small></span></li>
        <li><i class="bi bi-shield-check" aria-hidden="true"></i><span><strong>Check the details</strong><small>Support ownership reviews</small></span></li>
        <li><i class="bi bi-bag-check" aria-hidden="true"></i><span><strong>Record a return</strong><small>Release approved claims</small></span></li>
      </ol>
    </section>

    <div class="row stats-grid mb-4">
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
          <i class="bi bi-arrow-up-right stat-link-arrow" aria-hidden="true"></i>
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
    <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
      <i class="bi bi-hourglass-split" aria-hidden="true"></i>
      <div>
        <strong><?= $stats['pending_claims'] ?> claim<?= $stats['pending_claims']>1?'s':'' ?> awaiting review</strong>
        <div class="small">Students are waiting for their claims to be processed.</div>
      </div>
      <a href="<?= BASE_URL ?>/security/claims.php?status=pending" class="text-link ms-auto">Review claims</a>
    </div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-lg-8">
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
                    <td><code><?= e($fi['report_number']) ?></code></td>
                    <td class="fw-600"><?= e($fi['item_name']) ?></td>
                    <td><?= e($fi['cat']) ?></td>
                    <td><?= statusBadge($fi['status']) ?></td>
                    <td>
                      <?php if ($fi['qr_code_path']): ?>
                      <i class="bi bi-qr-code text-primary"></i>
                      <?php else: ?>
                      <span class="text-muted">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($recentFound)): ?><tr><td colspan="5"><div class="empty-state py-3"><i class="bi bi-inbox"></i><h5>No found items logged yet</h5><p>Items you log into custody will appear here.</p><a href="<?= BASE_URL ?>/security/found_items.php?action=new" class="btn btn-primary btn-sm">Log found item</a></div></td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card">
          <div class="card-header">
            <span class="card-title"><i class="bi bi-hourglass-split"></i> Pending Claims</span>
            <a href="<?= BASE_URL ?>/security/claims.php" class="btn btn-sm btn-outline-primary">All Claims</a>
          </div>
          <?php if (empty($pendingClaimRows)): ?>
          <div class="card-body"><div class="empty-state py-3"><i class="bi bi-check-circle" aria-hidden="true"></i><h3>No pending claims</h3><p>New ownership claims will appear here for review.</p></div></div>
          <?php else: ?>
          <?php foreach ($pendingClaimRows as $claim): ?>
          <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
            <div class="stat-icon icon-sm">
              <i class="bi bi-file-earmark"></i>
            </div>
            <div class="flex-1">
              <div class="fw-600 small"><?= e($claim['item_name']) ?></div>
              <div class="text-muted-sm">by <?= e($claim['claimant_name']) ?> · <?= timeAgo($claim['created_at']) ?></div>
            </div>
            <a href="<?= BASE_URL ?>/security/claims.php?focus=<?= $claim['id'] ?>" class="btn btn-sm btn-outline-secondary">Review</a>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
