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
require_once __DIR__ . '/../includes/claim_ml.php';

requireRole('student', 'admin');

$uid    = $user['id'];
$errors = [];
$categories = getCategories();
$locations = getLocations();
$formValue = static fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $proofPhoto = null;
    try {
        ClaimFeatures::submission($_POST);
        if (isset($_FILES['proof_photo']) && ($_FILES['proof_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $proofPhoto = uploadFile($_FILES['proof_photo'], 'claim_proofs');
            if (!$proofPhoto) throw new InvalidArgumentException('Supporting photo could not be uploaded. Use an allowed image under 5 MB.');
        }
        claimMl()->submitClaim((int)$uid, $_POST, $proofPhoto ?: null);
        setFlash('success', 'Your claim has been submitted. An administrator will review your ownership evidence.');
        redirect(BASE_URL . '/student/claims.php');
    } catch (InvalidArgumentException | DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Claim submission failed: ' . get_class($e));
        $errors[] = 'The claim could not be saved. Please try again or contact an administrator.';
    }
    if ($proofPhoto) @unlink(UPLOADS_PATH . '/' . $proofPhoto);
}

// Pre-fill from URL if coming from matches page
$preFilledFoundId = (int)filter_var($_POST['found_item_id'] ?? $_GET['found_id'] ?? 0, FILTER_VALIDATE_INT);
$preFilledMatchId = (int)filter_var($_POST['match_id'] ?? $_GET['match_id'] ?? 0, FILTER_VALIDATE_INT);

// Fetch found item info if pre-filling
$preFilledFound = null;
if ($preFilledFoundId) {
    $preFilledFound = db()->fetchOne('SELECT id,item_name,report_number,storage_location FROM found_items WHERE id=? AND status IN ("active","matched")', [$preFilledFoundId]);
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

$showForm = !empty($errors) || (isset($_GET['action']) && $_GET['action'] === 'new');

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
            <label class="form-label">Found Item ID</label>
            <input type="number" name="found_item_id" class="form-control" required placeholder="Found item ID">
          </div>
          <?php endif; ?>

          <div class="alert alert-info">Describe the item from your own knowledge. Your evidence is private. An administrator makes the final ownership decision.</div>
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label" for="claimedCategory">Your item's category</label>
              <select name="claimed_category_id" id="claimedCategory" class="form-select">
                <option value="">Not sure</option>
                <?php foreach ($categories as $category): ?>
                <option value="<?= $category['id'] ?>" <?= $formValue('claimed_category_id') === (string)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="claimedLocation">Where you lost it</label>
              <select name="claimed_location_id" id="claimedLocation" class="form-select">
                <option value="">Not sure</option>
                <?php foreach ($locations as $location): ?>
                <option value="<?= $location['id'] ?>" <?= $formValue('claimed_location_id') === (string)$location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="claimedDate">Date you lost it</label>
              <input type="date" name="claimed_date_lost" id="claimedDate" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= e($formValue('claimed_date_lost')) ?>">
              <div class="form-text">Leave blank if unknown.</div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Proof of Ownership <span class="text-danger">*</span></label>
            <textarea name="proof_description" class="form-control" rows="5" required minlength="10" maxlength="4000"
                      placeholder="Describe your item and explain your ownership evidence."><?= e($formValue('proof_description')) ?></textarea>
            <div class="form-text">The more detail you provide, the faster your claim will be reviewed.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="claimMarks">Unique identifying characteristics</label>
            <textarea name="unique_characteristics" id="claimMarks" class="form-control" rows="3" maxlength="2000" placeholder="Describe private marks, stickers, scratches, or an identifying code."><?= e($formValue('unique_characteristics')) ?></textarea>
          </div>
          <?php foreach (ClaimFeatures::QUESTIONS as $key => $question): ?>
          <div class="mb-3">
            <label class="form-label" for="answer-<?= $key ?>"><?= e($question) ?></label>
            <textarea name="verification_answers[<?= $key ?>]" id="answer-<?= $key ?>" class="form-control" rows="2" maxlength="1000"><?= e(is_string($_POST['verification_answers'][$key] ?? null) ? $_POST['verification_answers'][$key] : '') ?></textarea>
          </div>
          <?php endforeach; ?>
          <p class="form-text">Leave optional answers blank if you do not know. Do not provide passwords, PINs, or account credentials.</p>

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
