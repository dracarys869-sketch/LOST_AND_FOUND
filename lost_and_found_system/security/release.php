<?php
/**
 * Security — Release Items
 */
$pageTitle  = 'Release Items';
$activePage = 'release';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('security', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $claimId  = (int)($_POST['claim_id']  ?? 0);
    $notes    = sanitize($_POST['notes']  ?? '');

    if ($claimId) {
        $claim = db()->fetchOne(
            'SELECT c.*, fi.id AS found_id, fi.item_name FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id WHERE c.id=? AND c.status="approved"',
            [$claimId]
        );
        if ($claim) {
            db()->beginTransaction();
            try {
                // Create release record
                $relId = (int)db()->insert(
                    'INSERT INTO releases (claim_id, found_item_id, released_by, released_to, notes) VALUES (?,?,?,?,?)',
                    [$claimId, $claim['found_id'], $user['id'], $claim['claimant_id'], $notes ?: null]
                );
                // Update found item status
                db()->execute("UPDATE found_items SET status='released' WHERE id=?", [$claim['found_id']]);
                // Update lost item status if linked
                if ($claim['lost_item_id']) {
                    db()->execute("UPDATE lost_items SET status='claimed' WHERE id=?", [$claim['lost_item_id']]);
                }
                // Update match status if linked
                if ($claim['match_id']) {
                    db()->execute("UPDATE matches SET status='confirmed' WHERE id=?", [$claim['match_id']]);
                }
                // Notify student
                createNotification($claim['claimant_id'], 'Item Released!',
                    "Your item \"{$claim['item_name']}\" has been released to you. Thank you!", 'success', $relId, 'release');

                auditLog($user['id'], 'RELEASE_ITEM', 'releases', $relId);
                db()->commit();
                setFlash('success', "Item \"{$claim['item_name']}\" released successfully. Release record created.");
            } catch (Exception $e) {
                db()->rollBack();
                setFlash('danger', 'Error recording release: ' . $e->getMessage());
            }
        } else {
            setFlash('danger', 'Claim not found or not yet approved.');
        }
    }
    redirect(BASE_URL . '/security/release.php');
}

// Pre-fill claim_id from URL
$preFillClaimId = (int)($_GET['claim_id'] ?? 0);

// Fetch approved, unreleased claims
$approved = db()->fetchAll(
    'SELECT c.*, fi.item_name, fi.report_number AS found_rn, fi.storage_location, u.name AS claimant_name, u.student_id
     FROM claims c LEFT JOIN found_items fi ON c.found_item_id=fi.id LEFT JOIN users u ON c.claimant_id=u.id
     WHERE c.status="approved"
     AND NOT EXISTS (SELECT 1 FROM releases r WHERE r.claim_id=c.id)
     ORDER BY c.updated_at ASC'
);

// Release history
$history = db()->fetchAll(
    'SELECT r.*, fi.item_name, u.name AS released_to_name, rb.name AS released_by_name
     FROM releases r LEFT JOIN found_items fi ON r.found_item_id=fi.id
     LEFT JOIN users u ON r.released_to=u.id LEFT JOIN users rb ON r.released_by=rb.id
     ORDER BY r.release_date DESC LIMIT 10'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_security.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Security /</span> Release Items</div>
    <div class="topbar-actions"></div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Release Items</h1><p class="page-subtitle">Record item handover to approved claimants.</p></div>
    </div>

    <?php if (empty($approved)): ?>
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2"></i> No approved claims are pending release. <a href="<?= BASE_URL ?>/security/claims.php?status=approved">View approved claims.</a>
    </div>
    <?php else: ?>
    <?php foreach ($approved as $claim): ?>
    <div class="card mb-3 <?= $claim['id']===$preFillClaimId?'border-primary':'' ?>">
      <div class="card-header">
        <div>
          <span class="card-title"><i class="bi bi-check-circle text-success me-2"></i>Approved Claim #<?= $claim['id'] ?></span>
          <div style="font-size:.75rem;color:var(--text-muted);">Approved <?= timeAgo($claim['updated_at']) ?></div>
        </div>
        <span class="badge bg-success">Approved</span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-4">
            <small class="text-muted-sm">Claimant</small>
            <div class="fw-700"><?= e($claim['claimant_name']) ?></div>
            <?php if ($claim['student_id']): ?><div style="font-size:.8rem;">ID: <?= e($claim['student_id']) ?></div><?php endif; ?>
          </div>
          <div class="col-md-4">
            <small class="text-muted-sm">Item</small>
            <div class="fw-700"><?= e($claim['item_name']) ?></div>
            <code style="font-size:.72rem;"><?= e($claim['found_rn']) ?></code>
          </div>
          <div class="col-md-4">
            <small class="text-muted-sm">Storage Location</small>
            <div><?= e($claim['storage_location'] ?? '—') ?></div>
          </div>
        </div>
      </div>
      <div class="card-footer">
        <form method="POST" class="d-flex align-items-center gap-2 flex-wrap">
          <?= csrfField() ?>
          <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
          <input type="text" name="notes" class="form-control flex-grow-1" placeholder="Release notes (optional)…">
          <button type="submit" class="btn btn-success" onclick="return confirm('Confirm item release to <?= e($claim['claimant_name']) ?>?')">
            <i class="bi bi-check2-circle me-2"></i>Record Release
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Release History -->
    <?php if (!empty($history)): ?>
    <div class="card mt-4">
      <div class="card-header"><span class="card-title"><i class="bi bi-clock-history"></i> Recent Releases</span></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.83rem;">
            <thead><tr><th>Item</th><th>Released To</th><th>Released By</th><th>Date</th><th>Notes</th></tr></thead>
            <tbody>
              <?php foreach ($history as $r): ?>
              <tr>
                <td class="fw-600"><?= e($r['item_name']) ?></td>
                <td><?= e($r['released_to_name']) ?></td>
                <td><?= e($r['released_by_name']) ?></td>
                <td style="white-space:nowrap;"><?= formatDate($r['release_date'],'M j, Y h:i A') ?></td>
                <td style="color:var(--text-muted);"><?= e($r['notes'] ?? '—') ?></td>
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
