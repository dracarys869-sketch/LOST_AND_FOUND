<?php
$pageTitle = 'Private Item Verification';
$activePage = 'found';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/claim_ml.php';
requireRole('security', 'admin');
header('Cache-Control: private, no-store');
$itemId = (int)filter_var($_GET['item_id'] ?? 0, FILTER_VALIDATE_INT);
$item = db()->fetchOne('SELECT fi.*,c.name AS category,l.name AS location FROM found_items fi
    JOIN categories c ON c.id=fi.category_id JOIN locations l ON l.id=fi.location_id WHERE fi.id=?', [$itemId]);
if (!$item) { http_response_code(404); exit('Item not found.'); }
$errors = [];
$ready = claimMl()->schemaReady();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        claimMl()->saveReference((int)$user['id'], $itemId, $_POST);
        setFlash('success', 'Private item reference saved for future claims. Existing claim snapshots were preserved.');
        redirect(BASE_URL . '/security/item_verification.php?item_id=' . $itemId);
    } catch (InvalidArgumentException | DomainException $e) { $errors[] = $e->getMessage(); }
    catch (Throwable $e) {
        error_log('Private item reference failed: ' . get_class($e));
        $errors[] = 'The item reference could not be saved. Please try again.';
    }
}
$reference = $ready ? db()->fetchOne('SELECT * FROM found_item_verification WHERE found_item_id=?', [$itemId]) : false;
$answers = $reference ? ClaimMlService::decode($reference['verification_answers']) : [];
$formValue = static fn(string $key, string $default = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
$photos = itemPhotos('found', $itemId);
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_' . ($user['role'] === 'admin' ? 'admin' : 'security') . '.php';
?>
<div class="main-content">
  <div class="topbar"><button class="btn-icon me-2" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button><div class="topbar-title">Private Item Verification</div></div>
  <div class="content-area">
    <div class="page-header"><div><h1 class="page-title"><?= e($item['item_name']) ?></h1><p class="page-subtitle"><?= e($item['report_number']) ?> · <?= e($item['category']) ?> · <?= e($item['location']) ?></p></div><a href="<?= BASE_URL ?>/security/found_items.php" class="btn btn-sm btn-outline-secondary">Found Items</a></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <?php if (!$ready): ?>
      <div class="alert alert-warning">An administrator must run <code>php scripts/migrate_decision_tree.php</code> first.</div>
    <?php else: ?>
    <div class="alert alert-info">Inspect the physical item and record the reference before claims arrive. Correct answers and identifying phrases are visible only to authorized staff. All claim questions are generic; claimants never receive these reference answers.</div>
    <div class="row g-3">
      <div class="col-lg-8"><div class="card">
        <div class="card-header"><span class="card-title"><i class="bi bi-shield-lock"></i> Verification Reference</span><small class="text-muted">Revision <?= (int)($reference['reference_version'] ?? 0) ?></small></div>
        <form method="POST">
          <?= csrfField() ?>
          <div class="card-body">
            <label class="form-label" for="privateMarks">Private identifying phrases — one per line</label>
            <textarea name="unique_characteristics" id="privateMarks" class="form-control mb-2" rows="4" maxlength="2000" placeholder="Record concise, independently observed marks or codes. Up to 10 phrases."><?= e($formValue('unique_characteristics', $reference['unique_characteristics'] ?? '')) ?></textarea>
            <p class="form-text">Use concise identifiers rather than full sentences. Matching normalizes case and punctuation; it does not understand paraphrases. Leave unknown references blank.</p>
            <?php foreach (ClaimFeatures::QUESTIONS as $key => $question): ?>
              <label class="form-label mt-2" for="reference-<?= $key ?>"><?= e($question) ?> — correct private answer</label>
              <textarea name="verification_answers[<?= $key ?>]" id="reference-<?= $key ?>" class="form-control mb-3" rows="2" maxlength="1000"><?= e(is_string($_POST['verification_answers'][$key] ?? null) ? $_POST['verification_answers'][$key] : ($answers[$key] ?? '')) ?></textarea>
            <?php endforeach; ?>
            <label class="form-label" for="publicDescription">Public-safe description (optional)</label>
            <textarea name="public_description" id="publicDescription" class="form-control mb-2" rows="2" maxlength="500" placeholder="A general description without unique marks, personal names, contents, or correct answers."><?= e($formValue('public_description', $reference['public_description'] ?? '')) ?></textarea>
            <p class="form-text">Only this summary is used in public found-item descriptions. The original detailed report stays private. Never include passwords or PINs in any reference.</p>
            <div class="form-check mt-3">
              <input type="checkbox" name="reference_checked" id="referenceChecked" class="form-check-input" value="1" required>
              <label class="form-check-label" for="referenceChecked">I inspected the item independently and checked that the public summary reveals no identifying secrets.</label>
            </div>
          </div>
          <div class="card-footer"><button class="btn btn-primary">Save Item Reference</button></div>
        </form>
      </div></div>
      <div class="col-lg-4"><div class="card mb-3">
        <div class="card-header"><span class="card-title">Original Private Report</span></div>
        <div class="card-body"><p style="white-space:pre-wrap;"><?= e($item['description'] ?? 'No description.') ?></p><p class="small text-muted">Found <?= e($item['date_found']) ?><br>Storage: <?= e($item['storage_location']) ?></p></div>
      </div>
      <div class="card"><div class="card-header"><span class="card-title">Item Photos</span></div><div class="card-body">
        <?php foreach ($photos as $photo): ?><a href="<?= e($photo['url']) ?>" target="_blank" rel="noopener"><img src="<?= e($photo['url']) ?>" class="img-fluid rounded mb-2" alt="Private found item photo"></a><?php endforeach; ?>
        <?php if (!$photos): ?><p class="text-muted">No photos uploaded.</p><?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?><a href="<?= BASE_URL ?>/admin/photo_review.php?type=found&item_id=<?= $itemId ?>" class="btn btn-sm btn-outline-primary">Review Photo Privacy</a><?php endif; ?>
      </div></div></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
