<?php
/**
 * Student — Claims
 */
$pageTitle  = 'My Claims';
$activePage = 'claims';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('student', 'admin');

$uid    = $user['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $foundId   = (int)($_POST['found_item_id'] ?? 0);
    $matchId   = (int)($_POST['match_id']      ?? 0) ?: null;
    $lostId    = (int)($_POST['lost_item_id']  ?? 0) ?: null;
    $proof     = sanitize($_POST['proof_description'] ?? '');

    if (!$foundId) $errors[] = 'Invalid item.';

    // Check no duplicate
    $existing = db()->fetchOne('SELECT id FROM claims WHERE claimant_id=? AND found_item_id=? AND status!="rejected"', [$uid,$foundId]);
    if ($existing) $errors[] = 'You already have an active claim for this item.';

    if (empty($errors)) {
        $proofPhoto = null;
        if (!empty($_FILES['proof_photo']['name'])) {
            $proofPhoto = uploadFile($_FILES['proof_photo'], 'claim_proofs');
        }

        $claimId = (int)db()->insert(
            'INSERT INTO claims (lost_item_id, found_item_id, match_id, claimant_id, proof_description, proof_photo)
             VALUES (?,?,?,?,?,?)',
            [$lostId, $foundId, $matchId, $uid, $proof ?: null, $proofPhoto]
        );

        // Notify admins and security
        $fi = db()->fetchOne('SELECT item_name FROM found_items WHERE id=?',[$foundId]);
        $staffList = db()->fetchAll('SELECT id FROM users WHERE role IN ("admin","security") AND status="active"');
        foreach ($staffList as $s) {
            createNotification($s['id'], 'New Ownership Claim Filed',
                "{$user['name']} filed a claim for \"{$fi['item_name']}\". Please review.", 'claim', $claimId, 'claim');
        }

        auditLog($uid, 'FILE_CLAIM', 'claims', $claimId);
        setFlash('success', 'Your claim has been submitted. Staff will review it shortly.');
        redirect(BASE_URL . '/student/claims.php');
    }
}

// Pre-fill from URL if coming from matches page
$preFilledFoundId = (int)($_GET['found_id']   ?? 0);
$preFilledMatchId = (int)($_GET['match_id']   ?? 0);

// Fetch found item info if pre-filling
$preFilledFound = null;
if ($preFilledFoundId) {
    $preFilledFound = db()->fetchOne('SELECT * FROM found_items WHERE id=?', [$preFilledFoundId]);
}

// Fetch this student's claims
$claims = db()->fetchAll(
    'SELECT c.*, fi.item_name, fi.report_number AS found_rn, fi.storage_location,
     rv.name AS reviewer_name
     FROM claims c
     LEFT JOIN found_items fi ON c.found_item_id=fi.id
     LEFT JOIN users rv ON c.reviewed_by=rv.id
     WHERE c.claimant_id=?
     ORDER BY c.created_at DESC',
    [$uid]
);

$showForm = isset($_GET['action']) && $_GET['action'] === 'new';

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Student /</span> My Claims</div>
    <div class="topbar-actions">
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">My Claims</h1><p class="page-subtitle">Ownership claims you have submitted.</p></div>
    </div>

    <!-- New claim form -->
    <?php if ($showForm): ?>
    <div class="card mb-4">
      <div class="card-header"><span class="card-title"><i class="bi bi-file-earmark-plus"></i> File a New Claim</span></div>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="card-body">
          <?php if ($errors): ?>
          <div class="alert alert-danger"><?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?></div>
          <?php endif; ?>

          <?php if ($preFilledFound): ?>
          <div class="alert alert-info d-flex gap-3 mb-4">
            <i class="bi bi-box-seam" style="font-size:1.4rem;"></i>
            <div>
              <strong>Filing claim for: <?= e($preFilledFound['item_name']) ?></strong>
              <div style="font-size:.82rem;">Report #<?= e($preFilledFound['report_number']) ?></div>
              <?php if ($preFilledFound['storage_location']): ?>
              <div style="font-size:.82rem;"><i class="bi bi-geo-alt me-1"></i><?= e($preFilledFound['storage_location']) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <input type="hidden" name="found_item_id" value="<?= $preFilledFoundId ?>">
          <input type="hidden" name="match_id"      value="<?= $preFilledMatchId ?>">
          <?php else: ?>
          <div class="mb-3">
            <label class="form-label">Found Item Report # (or ID)</label>
            <input type="number" name="found_item_id" class="form-control" required placeholder="Found item ID">
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Proof of Ownership <span class="text-danger">*</span></label>
            <textarea name="proof_description" class="form-control" rows="5" required
                      placeholder="Describe why this item belongs to you:&#10;• Unique identifying features (scratches, stickers, contents)&#10;• When and where you lost it&#10;• What was inside (if applicable)&#10;• Serial numbers or registration details"><?= e($_POST['proof_description'] ?? '') ?></textarea>
            <div class="form-text">The more detail you provide, the faster your claim will be reviewed.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Supporting Photo (optional)</label>
            <input type="file" name="proof_photo" class="form-control" accept="image/*">
            <div class="form-text">Upload a photo that proves ownership (e.g., a previous photo with the item, receipt, etc.)</div>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-end gap-2">
          <a href="<?= BASE_URL ?>/student/claims.php" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" class="btn btn-primary"><i class="bi bi-send me-2"></i>Submit Claim</button>
        </div>
      </form>
    </div>
    <?php endif; ?>

    <!-- Claims History -->
    <div class="card">
      <div class="card-header">
        <span class="card-title"><i class="bi bi-file-earmark-check"></i> Claim History</span>
        <?php if (!$showForm): ?>
        <a href="?action=new" class="btn btn-primary btn-sm"><i class="bi bi-plus me-1"></i>New Claim</a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-mobile-cards">
            <thead><tr><th>#</th><th>Item</th><th>Storage Location</th><th>Status</th><th>Reviewer Notes</th><th>Submitted</th></tr></thead>
            <tbody>
              <?php foreach ($claims as $claim): ?>
              <tr>
                <td data-label="#">#<?= $claim['id'] ?></td>
                <td data-label="Item">
                  <div class="fw-600" style="font-size:.85rem;"><?= e($claim['item_name']) ?></div>
                  <code style="font-size:.7rem;"><?= e($claim['found_rn']) ?></code>
                </td>
                <td data-label="Storage" style="font-size:.8rem;"><?= e($claim['storage_location'] ?? '—') ?></td>
                <td data-label="Status"><?= statusBadge($claim['status']) ?></td>
                <td data-label="Notes" style="font-size:.8rem;max-width:200px;">
                  <?= $claim['review_notes'] ? snippet($claim['review_notes'],70) : '<span class="text-muted">—</span>' ?>
                  <?php if ($claim['reviewer_name']): ?>
                  <div style="font-size:.72rem;color:var(--text-muted);">by <?= e($claim['reviewer_name']) ?></div>
                  <?php endif; ?>
                </td>
                <td data-label="Submitted" style="font-size:.75rem;white-space:nowrap;"><?= timeAgo($claim['created_at']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($claims)): ?>
              <tr><td colspan="6"><div class="empty-state py-4"><i class="bi bi-file-earmark-check"></i><h5>No claims yet</h5><p>When you find a match, you can file a claim to recover your item.</p></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
