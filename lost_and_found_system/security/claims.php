<?php
/**
 * Security — Claims Review & Release
 */
$pageTitle  = 'Claims Review';
$activePage = 'claims';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/claim_ml.php';

requireRole('security', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action  = $_POST['action']  ?? '';
    $claimId = (int)($_POST['claim_id'] ?? 0);

    try {
        if ($action !== 'review') {
            http_response_code(403);
            exit('Only administrators can make final ownership decisions. Use the administrator claims page.');
        }
        $notes = ClaimFeatures::text($_POST['notes'] ?? '', 'Private review notes', 4000, 10);
        claimMl()->saveSecurityReview((int)$user['id'], $claimId, $notes);
        setFlash('success', 'Private evidence review saved for the administrator.');
    } catch (InvalidArgumentException | DomainException $e) {
        setFlash('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Security claim review failed: ' . get_class($e));
        setFlash('danger', 'The review could not be saved. Please try again.');
    }
    redirect(BASE_URL.'/security/claims.php');
}

$statusFilter = sanitize($_GET['status'] ?? '');
$focus        = (int)($_GET['focus'] ?? 0);
$page         = max(1,(int)($_GET['page'] ?? 1));
$mlReady = claimMl()->schemaReady();

$sql    = 'SELECT c.*, u.name AS claimant_name, u.email AS claimant_email, u.student_id,
           fi.item_name, fi.report_number AS found_rn, fi.storage_location, ' . ($mlReady ? 'v.security_notes,v.final_decision' : 'NULL AS security_notes,NULL AS final_decision') . '
           FROM claims c LEFT JOIN users u ON c.claimant_id=u.id
           LEFT JOIN found_items fi ON c.found_item_id=fi.id ' . ($mlReady ? 'LEFT JOIN claim_verifications v ON v.claim_id=c.id ' : '') . 'WHERE 1=1';
$params = [];
if ($statusFilter) { $sql .= ' AND c.status=?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY c.status="pending" DESC, c.created_at ASC';
$pager = paginate($sql, $params, $page);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> Claims</div>
    <div class="topbar-actions"></div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Claims Review</h1><p class="page-subtitle">Record private evidence for administrator review and release administrator-approved items.</p></div>
    </div>

    <div class="d-flex gap-2 mb-3">
      <?php foreach ([''=> 'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
      <a href="?status=<?= $v ?>" class="btn btn-sm <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($pager['rows'] as $claim): ?>
    <?php $isFocus = $claim['id'] === $focus; ?>
    <div class="card mb-3 <?= $isFocus ? 'border-primary' : '' ?>" id="claim-<?= $claim['id'] ?>"
         style="<?= $isFocus ? 'border-width:2px;' : '' ?>">
      <div class="card-header">
        <div>
          <span class="card-title"><i class="bi bi-file-earmark-check"></i> Claim #<?= $claim['id'] ?></span>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:.15rem;">
            Submitted <?= timeAgo($claim['created_at']) ?> for <strong><?= e($claim['item_name']) ?></strong>
            <code style="font-size:.7rem;"><?= e($claim['found_rn']) ?></code>
          </div>
        </div>
        <?= statusBadge($claim['status']) ?>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <h6 class="fw-700 mb-2" style="font-size:.8rem;">Claimant</h6>
            <div style="font-size:.85rem;font-weight:600;"><?= e($claim['claimant_name']) ?></div>
            <div style="font-size:.78rem;color:var(--text-muted);"><?= e($claim['claimant_email']) ?></div>
            <?php if ($claim['student_id']): ?><div style="font-size:.75rem;">ID: <?= e($claim['student_id']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <h6 class="fw-700 mb-2" style="font-size:.8rem;">Item in Custody</h6>
            <div style="font-size:.85rem;font-weight:600;"><?= e($claim['item_name']) ?></div>
            <?php if ($claim['storage_location']): ?>
            <div style="font-size:.78rem;"><i class="bi bi-geo-alt me-1 text-primary"></i><?= e($claim['storage_location']) ?></div>
            <?php endif; ?>
          </div>
          <div class="col-md-4">
            <h6 class="fw-700 mb-2" style="font-size:.8rem;">Proof of Ownership</h6>
            <div style="font-size:.82rem;color:var(--text-muted);">
              <?= $claim['proof_description'] ? e($claim['proof_description']) : '<em>No description provided.</em>' ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card-body border-top">
        <a href="<?= BASE_URL ?>/security/item_verification.php?item_id=<?= $claim['found_item_id'] ?>" class="btn btn-sm btn-outline-secondary mb-3">Private Item Reference</a>
        <?php if ($user['role'] === 'admin'): ?><a href="<?= BASE_URL ?>/admin/claim_review.php?id=<?= $claim['id'] ?>" class="btn btn-sm btn-outline-primary mb-3">Administrator Review / Decision</a><?php endif; ?>
        <form method="POST">
          <?= csrfField() ?><input type="hidden" name="action" value="review"><input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
          <label class="form-label" for="notes-<?= $claim['id'] ?>">Private evidence review (visible to staff)</label>
          <textarea name="notes" id="notes-<?= $claim['id'] ?>" class="form-control mb-2" rows="2" required minlength="10" maxlength="4000"><?= e($claim['security_notes'] ?? '') ?></textarea>
          <button class="btn btn-sm btn-primary">Save Review for Administrator</button>
        </form>
      </div>
      <?php if ($claim['status'] === 'approved' && $claim['final_decision'] === 'approved'): ?>
      <div class="card-footer d-flex justify-content-end">
        <a href="<?= BASE_URL ?>/security/release.php?claim_id=<?= $claim['id'] ?>" class="btn btn-sm btn-primary">
          <i class="bi bi-check2-circle me-1"></i>Record Release
        </a>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (empty($pager['rows'])): ?>
    <div class="empty-state"><i class="bi bi-file-earmark-check"></i><h5>No claims found</h5></div>
    <?php endif; ?>
    <?php if ($pager['pages'] > 1): echo '<div class="d-flex justify-content-center mt-3">'.renderPagination($pager).'</div>'; endif; ?>
  </div>
</div>

<script>
<?php if ($focus): ?>
document.getElementById('claim-<?= $focus ?>')?.scrollIntoView({behavior:'smooth',block:'center'});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
