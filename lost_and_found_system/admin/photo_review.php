<?php
$pageTitle = 'Photo Privacy';
$activePage = 'reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/claim_ml.php';
requireRole('admin');
header('Cache-Control: private, no-store');
$type = $_GET['type'] ?? '';
$itemId = (int)filter_var($_GET['item_id'] ?? 0, FILTER_VALIDATE_INT);
if (!in_array($type, ['lost', 'found'], true)) { http_response_code(404); exit('Item not found.'); }
$item = db()->fetchOne('SELECT id,item_name FROM ' . ($type === 'found' ? 'found_items' : 'lost_items') . ' WHERE id=?', [$itemId]);
if (!$item) { http_response_code(404); exit('Item not found.'); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        $photoId = ClaimFeatures::id($_POST['photo_id'] ?? null, 'photo');
        if (!db()->fetchOne('SELECT id FROM item_photos WHERE id=? AND item_type=? AND item_id=?', [$photoId, $type, $itemId])) throw new DomainException('Photo not found for this item.');
        claimMl()->setPhotoVisibility((int)$user['id'], $photoId, ($_POST['is_public'] ?? '') === '1');
        setFlash('success', 'Photo privacy saved.');
    } catch (InvalidArgumentException | DomainException $e) { setFlash('danger', $e->getMessage()); }
    catch (Throwable $e) { setFlash('danger', 'Photo privacy could not be saved.'); }
    redirect(BASE_URL . '/admin/photo_review.php?type=' . $type . '&item_id=' . $itemId);
}
$photos = claimMl()->schemaReady() ? db()->fetchAll('SELECT p.id,p.photo_path,COALESCE(v.is_public,0) AS is_public
    FROM item_photos p LEFT JOIN item_photo_visibility v ON v.photo_id=p.id WHERE p.item_type=? AND p.item_id=?', [$type, $itemId]) : [];
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar"><button class="btn-icon me-2" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button><div class="topbar-title">Admin / Photo Privacy</div></div>
  <div class="content-area">
    <div class="page-header"><div><h1 class="page-title">Report Photos — <?= e($item['item_name']) ?></h1><p class="page-subtitle">Report photos are shown on the public landing page after upload. Check them here to confirm they do not contain sensitive information.</p></div></div>
    <div class="row g-3">
      <?php foreach ($photos as $photo): ?><div class="col-md-6"><div class="card"><div class="card-body">
        <a href="<?= e(photoUrl($photo['photo_path'])) ?>" target="_blank" rel="noopener" aria-label="Open full-size photo of <?= e($item['item_name']) ?>"><img src="<?= e(photoUrl($photo['photo_path'])) ?>" class="photo-review-image mb-3" alt="<?= e($item['item_name']) ?> — photo under privacy review"></a>
        <form method="POST">
          <?= csrfField() ?><input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
          <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="public-<?= $photo['id'] ?>" name="is_public" value="1" <?= $photo['is_public'] ? 'checked' : '' ?>><label class="form-check-label" for="public-<?= $photo['id'] ?>">Reviewed: safe to show publicly (no private marks, serials, labels, contents, or personal details)</label></div>
          <button class="btn btn-primary btn-sm">Save Privacy</button>
        </form>
      </div></div></div><?php endforeach; ?>
    </div>
    <?php if (!$photos): ?><div class="alert alert-info">No photos available. If this is a new installation, apply the Decision Tree migration first.</div><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/reports.php?type=<?= $type ?>" class="btn btn-outline-secondary mt-3">Back to Reports</a>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
