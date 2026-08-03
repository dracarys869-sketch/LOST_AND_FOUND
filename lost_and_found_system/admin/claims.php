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

requireRole('admin');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action   = $_POST['action']   ?? '';
    $claimId  = (int)($_POST['claim_id'] ?? 0);
    $notes    = sanitize($_POST['review_notes'] ?? '');

    if ($action === 'approve' && $claimId) {
        db()->execute(
            'UPDATE claims SET status="approved", reviewed_by=?, review_notes=?, updated_at=NOW() WHERE id=?',
            [$user['id'], $notes, $claimId]
        );
        // Get claim details to notify claimant
        $claim = db()->fetchOne('SELECT c.*,u.name AS claimant_name, fi.item_name FROM claims c LEFT JOIN users u ON c.claimant_id=u.id LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE c.id=?',[$claimId]);
        if ($claim) {
            createNotification($claim['claimant_id'], 'Claim Approved! 🎉',
                "Your claim for \"{$claim['item_name']}\" has been approved. Please visit the Security Office to collect your item.", 'success', $claimId, 'claim');
            if ($claim['match_id']) {
                db()->execute("UPDATE matches SET status='confirmed' WHERE id=?", [$claim['match_id']]);
            }
        }
        auditLog($user['id'], 'APPROVE_CLAIM', 'claims', $claimId);
        setFlash('success', 'Claim approved. Student notified.');
    }
    if ($action === 'reject' && $claimId) {
        db()->execute(
            'UPDATE claims SET status="rejected", reviewed_by=?, review_notes=?, updated_at=NOW() WHERE id=?',
            [$user['id'], $notes, $claimId]
        );
        $claim = db()->fetchOne('SELECT c.*,fi.item_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE c.id=?',[$claimId]);
        if ($claim) {
            createNotification($claim['claimant_id'], 'Claim Rejected',
                "Your claim for \"{$claim['item_name']}\" was rejected. Reason: " . ($notes ?: 'No reason given.'), 'warning', $claimId, 'claim');
        }
        auditLog($user['id'], 'REJECT_CLAIM', 'claims', $claimId);
        setFlash('warning', 'Claim rejected.');
    }
    redirect(BASE_URL . '/admin/claims.php');
}

$statusFilter = sanitize($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));

$sql    = 'SELECT c.*, u.name AS claimant_name, u.email AS claimant_email, u.student_id,
           fi.item_name, fi.report_number AS found_rn, fi.category_id,
           rv.name AS reviewer_name
           FROM claims c
           LEFT JOIN users u ON c.claimant_id=u.id
           LEFT JOIN found_items fi ON c.found_item_id=fi.id
           LEFT JOIN users rv ON c.reviewed_by=rv.id
           WHERE 1=1';
$params = [];
if ($statusFilter) { $sql .= ' AND c.status=?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY c.created_at DESC';
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Claims</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Claims Management</h1><p class="page-subtitle">Review and approve/reject ownership claims.</p></div>
    </div>

    <!-- Status filter pills -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <?php foreach ([''=> 'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $v=>$l): ?>
      <a href="?status=<?= $v ?>" class="btn btn-sm <?= $statusFilter===$v?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-file-earmark-check"></i> Claims <span class="badge bg-secondary ms-1"><?= $pager['total'] ?></span></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead><tr><th>#</th><th>Item</th><th>Claimant</th><th>Proof</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $claim): ?>
              <tr>
                <td data-label="#">#<?= $claim['id'] ?></td>
                <td data-label="Item">
                  <div class="fw-600" style="font-size:.85rem;"><?= e($claim['item_name']) ?></div>
                  <code style="font-size:.7rem;"><?= e($claim['found_rn']) ?></code>
                </td>
                <td data-label="Claimant">
                  <div style="font-size:.83rem;font-weight:600;"><?= e($claim['claimant_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--text-muted);"><?= e($claim['claimant_email']) ?></div>
                  <?php if ($claim['student_id']): ?>
                  <div style="font-size:.72rem;color:var(--text-light);">ID: <?= e($claim['student_id']) ?></div>
                  <?php endif; ?>
                </td>
                <td data-label="Proof" style="font-size:.8rem;max-width:200px;"><?= snippet($claim['proof_description'] ?? '', 70) ?></td>
                <td data-label="Status"><?= statusBadge($claim['status']) ?></td>
                <td data-label="Submitted" style="font-size:.78rem;white-space:nowrap;"><?= timeAgo($claim['created_at']) ?></td>
                <td data-label="Actions">
                  <?php if ($claim['status'] === 'pending'): ?>
                  <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-success py-0 px-2"
                            data-bs-toggle="modal" data-bs-target="#actionModal"
                            data-id="<?= $claim['id'] ?>" data-action="approve"
                            data-name="<?= e($claim['item_name']) ?>">
                      <i class="bi bi-check-lg"></i> Approve
                    </button>
                    <button class="btn btn-sm btn-outline-danger py-0 px-2"
                            data-bs-toggle="modal" data-bs-target="#actionModal"
                            data-id="<?= $claim['id'] ?>" data-action="reject"
                            data-name="<?= e($claim['item_name']) ?>">
                      <i class="bi bi-x"></i> Reject
                    </button>
                  </div>
                  <?php else: ?>
                  <span style="font-size:.75rem;color:var(--text-muted);">by <?= e($claim['reviewer_name'] ?? '—') ?></span>
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
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="actionModalTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action"   id="modalClaimAction">
        <input type="hidden" name="claim_id" id="modalClaimId">
        <div class="modal-body">
          <p id="modalClaimDesc"></p>
          <div class="mb-3">
            <label class="form-label">Review Notes</label>
            <textarea name="review_notes" class="form-control" rows="3" placeholder="Add a note for the student (optional)…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn" id="modalConfirmBtn">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('actionModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    const isApprove = b.dataset.action === 'approve';
    document.getElementById('modalClaimAction').value = b.dataset.action;
    document.getElementById('modalClaimId').value     = b.dataset.id;
    document.getElementById('actionModalTitle').innerHTML = isApprove
        ? '<i class="bi bi-check-circle text-success me-2"></i>Approve Claim'
        : '<i class="bi bi-x-circle text-danger me-2"></i>Reject Claim';
    document.getElementById('modalClaimDesc').textContent = `Item: ${b.dataset.name}`;
    const btn = document.getElementById('modalConfirmBtn');
    btn.className = 'btn ' + (isApprove ? 'btn-success' : 'btn-danger');
    btn.textContent = isApprove ? 'Approve Claim' : 'Reject Claim';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
