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

requireRole('security', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action  = $_POST['action']  ?? '';
    $claimId = (int)($_POST['claim_id'] ?? 0);

    if ($action === 'approve' && $claimId) {
        db()->execute('UPDATE claims SET status="approved",reviewed_by=?,review_notes=?,updated_at=NOW() WHERE id=?',
            [$user['id'], sanitize($_POST['notes']??''), $claimId]);
        $claim = db()->fetchOne('SELECT c.*,fi.item_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE c.id=?',[$claimId]);
        if ($claim) {
            createNotification($claim['claimant_id'],'Claim Approved! 🎉',"Your claim for \"{$claim['item_name']}\" was approved. Visit Security Office.",'success',$claimId,'claim');
            if ($claim['match_id']) {
                db()->execute("UPDATE matches SET status='confirmed' WHERE id=?", [$claim['match_id']]);
            }
        }
        auditLog($user['id'],'APPROVE_CLAIM','claims',$claimId);
        setFlash('success','Claim approved. Student has been notified.');
    }
    if ($action === 'reject' && $claimId) {
        db()->execute('UPDATE claims SET status="rejected",reviewed_by=?,review_notes=?,updated_at=NOW() WHERE id=?',
            [$user['id'], sanitize($_POST['notes']??''), $claimId]);
        $claim = db()->fetchOne('SELECT c.*,fi.item_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE c.id=?',[$claimId]);
        if ($claim) createNotification($claim['claimant_id'],'Claim Rejected',"Your claim for \"{$claim['item_name']}\" was not approved. Reason: ".sanitize($_POST['notes']??'No reason given.'),'warning',$claimId,'claim');
        auditLog($user['id'],'REJECT_CLAIM','claims',$claimId);
        setFlash('warning','Claim rejected.');
    }
    redirect(BASE_URL.'/security/claims.php');
}

$statusFilter = sanitize($_GET['status'] ?? '');
$focus        = (int)($_GET['focus'] ?? 0);
$page         = max(1,(int)($_GET['page'] ?? 1));

$sql    = 'SELECT c.*, u.name AS claimant_name, u.email AS claimant_email, u.student_id,
           fi.item_name, fi.report_number AS found_rn, fi.storage_location
           FROM claims c LEFT JOIN users u ON c.claimant_id=u.id
           LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE 1=1';
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
      <div><h1 class="page-title">Claims Review</h1><p class="page-subtitle">Verify and process ownership claims.</p></div>
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

      <?php if ($claim['status'] === 'pending'): ?>
      <div class="card-footer d-flex justify-content-end gap-2">
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#actionModal"
                data-id="<?= $claim['id'] ?>" data-action="reject" data-item="<?= e($claim['item_name']) ?>">
          <i class="bi bi-x-circle me-1"></i>Reject
        </button>
        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#actionModal"
                data-id="<?= $claim['id'] ?>" data-action="approve" data-item="<?= e($claim['item_name']) ?>">
          <i class="bi bi-check-circle me-1"></i>Approve
        </button>
      </div>
      <?php elseif ($claim['status'] === 'approved'): ?>
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

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="aModalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" id="aAction"><input type="hidden" name="claim_id" id="aClaimId">
      <div class="modal-body">
        <p id="aDesc"></p>
        <div class="mb-3"><label class="form-label">Notes (shown to student)</label><textarea name="notes" class="form-control" rows="3" placeholder="Optional note…"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn" id="aBtn">Confirm</button>
      </div>
    </form>
  </div></div>
</div>

<script>
document.getElementById('actionModal')?.addEventListener('show.bs.modal',function(e){
    const b=e.relatedTarget, isApprove=b.dataset.action==='approve';
    document.getElementById('aAction').value=b.dataset.action;
    document.getElementById('aClaimId').value=b.dataset.id;
    document.getElementById('aModalTitle').innerHTML=isApprove?'<i class="bi bi-check-circle text-success me-2"></i>Approve Claim':'<i class="bi bi-x-circle text-danger me-2"></i>Reject Claim';
    document.getElementById('aDesc').textContent='Item: '+b.dataset.item;
    const btn=document.getElementById('aBtn');
    btn.className='btn '+(isApprove?'btn-success':'btn-danger');
    btn.textContent=isApprove?'Approve':'Reject';
});
<?php if ($focus): ?>
document.getElementById('claim-<?= $focus ?>')?.scrollIntoView({behavior:'smooth',block:'center'});
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
