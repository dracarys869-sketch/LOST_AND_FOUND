<?php
/**
 * Admin — Claims Management
 */
$pageTitle  = 'Claims';
$activePage = 'claims';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/claim_ml.php';

requireRole('admin');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action']   ?? '';
    $claimId  = (int)($_POST['claim_id'] ?? 0);
    try {
        if (!in_array($action, ['approve', 'reject'], true)) throw new InvalidArgumentException('Invalid claim action.');
        $notes = ClaimFeatures::text($_POST['review_notes'] ?? '', 'Review notes', 2000);
        claimMl()->decide((int)$user['id'], $claimId, $action === 'approve' ? 'approved' : 'rejected', $notes);
        setFlash('success', 'Final decision saved. Claimant notified.');
    } catch (InvalidArgumentException | DomainException $e) {
        setFlash('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Claim decision failed: ' . get_class($e));
        setFlash('danger', 'The final decision could not be saved. Please try again.');
    }
    redirect(BASE_URL . '/admin/claims.php');
}

$statusFilter = sanitize($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$mlReady = claimMl()->schemaReady();

$sql    = 'SELECT c.*, u.name AS claimant_name, u.email AS claimant_email, u.student_id,
           fi.item_name, fi.report_number AS found_rn, fi.category_id,
           rv.name AS reviewer_name, ' . ($mlReady ? 'a.classification,a.evidence_json,a.prediction_reason' : 'NULL AS classification,NULL AS evidence_json,NULL AS prediction_reason') . '
           FROM claims c
           LEFT JOIN users u ON c.claimant_id=u.id
           LEFT JOIN found_items fi ON c.found_item_id=fi.id
           LEFT JOIN users rv ON c.reviewed_by=rv.id ' . ($mlReady ? 'LEFT JOIN claim_assessments a ON a.claim_id=c.id ' : '') . 'WHERE 1=1';
$params = [];
if ($statusFilter) { $sql .= ' AND c.status=?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY c.created_at DESC';
$pager = paginate($sql, $params, $page);
$nextClaim = null;
foreach ($pager['rows'] as $listedClaim) {
    if ($listedClaim['status'] === 'pending') { $nextClaim = $listedClaim; break; }
}

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Claims</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Claims Management</h1><p class="page-subtitle">Review ownership evidence and Decision Tree recommendations before making the final decision.</p></div>
      <div class="action-row">
        <?php if ($nextClaim): ?><a href="<?= BASE_URL ?>/admin/claim_review.php?id=<?= (int)$nextClaim['id'] ?>" class="btn btn-primary">Review next claim <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/decision_tree.php" class="text-link small">Decision Tree</a>
      </div>
    </div>

    <!-- Status filter pills -->
    <nav class="filter-tabs" aria-label="Filter claims by status">
      <?php foreach ([''=> 'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
      <a href="?status=<?= $v ?>" class="btn btn-sm btn-outline-secondary <?= $statusFilter===$v?'active':'' ?>" <?= $statusFilter===$v?'aria-current="page"':'' ?>><?= $l ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-file-earmark-check"></i> Claims <span class="badge bg-secondary ms-1"><?= $pager['total'] ?></span></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Decision Tree / Evidence</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $claim): ?>
              <tr>
                <td data-label="#">#<?= $claim['id'] ?></td>
                <td data-label="Item">
                  <div class="fw-600"><?= e($claim['item_name']) ?></div>
                  <code><?= e($claim['found_rn']) ?></code>
                </td>
                <td data-label="Claimant">
                  <div class="fw-600"><?= e($claim['claimant_name']) ?></div>
                  <div class="text-muted-sm"><?= e($claim['claimant_email']) ?></div>
                  <?php if ($claim['student_id']): ?>
                  <div class="text-muted-sm">ID: <?= e($claim['student_id']) ?></div>
                  <?php endif; ?>
                </td>
                <td data-label="Decision Tree" class="table-note">
                  <?= claimClassificationBadge($claim['classification']) ?>
                  <div class="text-muted mt-1"><?= e(claimEvidenceSummary($claim['evidence_json'])) ?></div>
                  <?php if ($claim['prediction_reason']): ?><div class="mt-1"><?= e($claim['prediction_reason']) ?></div><?php endif; ?>
                </td>
                <td data-label="Status"><?= statusBadge($claim['status']) ?></td>
                <td data-label="Submitted" class="text-nowrap"><?= timeAgo($claim['created_at']) ?></td>
                <td data-label="Actions">
                  <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>/admin/claim_review.php?id=<?= $claim['id'] ?>">Review</a>
                  <?php if ($claim['status'] !== 'pending'): ?>
                  <span class="text-muted-sm">by <?= e($claim['reviewer_name'] ?? '—') ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr><td colspan="7"><div class="empty-state py-3"><i class="bi bi-file-earmark-check"></i><h5>No claims found</h5></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php if ($pager['pages'] > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $pager['from'] ?>–<?= $pager['to'] ?> of <?= $pager['total'] ?></small>
        <?= renderPagination($pager) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
