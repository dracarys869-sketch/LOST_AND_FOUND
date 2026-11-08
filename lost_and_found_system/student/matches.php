<?php
/**
 * Student — My Matches (existing weighted item-matching results)
 */
$pageTitle  = 'My Matches';
$activePage = 'matches';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/matching_engine.php';
require_once __DIR__ . '/../includes/claim_ml.php';

requireRole('student', 'admin');

$uid = $user['id'];
$privacyReady = claimMl()->schemaReady();

// Fetch matches for this student's lost items
$matches = db()->fetchAll(
    'SELECT m.*,
     li.item_name AS lost_name, li.report_number AS lost_rn, li.date_lost,
     li.brand AS lost_brand, li.color AS lost_color, li.description AS lost_desc,
     li.location_id AS lost_loc_id, li.category_id AS lost_cat_id,
     fi.item_name AS found_name, fi.report_number AS found_rn, fi.date_found,
     fi.brand AS found_brand, fi.color AS found_color, ' . ($privacyReady ? 'fv.public_description' : 'NULL') . ' AS found_desc,
     fi.storage_location, fi.id AS found_id,
     lc.name AS lost_cat, ll.name AS lost_loc,
     fc.name AS found_cat, fl.name AS found_loc
     FROM matches m
     LEFT JOIN lost_items  li ON m.lost_item_id  = li.id
     LEFT JOIN found_items fi ON m.found_item_id = fi.id
     LEFT JOIN categories lc ON li.category_id   = lc.id
     LEFT JOIN locations  ll ON li.location_id   = ll.id
     LEFT JOIN categories fc ON fi.category_id   = fc.id
     LEFT JOIN locations  fl ON fi.location_id   = fl.id
     ' . ($privacyReady ? 'LEFT JOIN found_item_verification fv ON fv.found_item_id=fi.id ' : '') . '
     WHERE li.user_id = ?
     ORDER BY m.similarity_score DESC, m.created_at DESC',
    [$uid]
);

// Selected match detail
$selectedId = (int)($_GET['id'] ?? 0);
$selected   = null;
foreach ($matches as $m) {
    if ((int)$m['id'] === $selectedId) { $selected = $m; break; }
}

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_student.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Student /</span> My Matches</div>
    <div class="topbar-actions">
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Item Match Suggestions</h1>
        <p class="page-subtitle">Items that may match your lost reports, ranked by similarity score.</p>
      </div>
    </div>

    <?php if (empty($matches)): ?>
    <div class="empty-state">
      <i class="bi bi-link-45deg"></i>
      <h5>No matches yet</h5>
      <p>When a found item closely matches one of your lost item reports, it will appear here.</p>
      <a href="<?= BASE_URL ?>/student/lost_items.php" class="btn btn-primary btn-sm">Report a Lost Item</a>
    </div>
    <?php else: ?>
    <div class="row g-3">
      <!-- Match List -->
      <div class="col-lg-4">
        <?php foreach ($matches as $m): ?>
        <?php
        $scoreColor = $m['similarity_score'] >= 90 ? 'green' : ($m['similarity_score'] >= 75 ? 'blue' : 'orange');
        $isSelected = (int)$m['id'] === $selectedId;
        ?>
        <a href="?id=<?= $m['id'] ?>" class="text-decoration-none">
          <div class="card mb-2 <?= $isSelected ? 'border-primary' : '' ?>"
               style="<?= $isSelected ? 'border-width:2px;' : '' ?>">
            <div class="card-body py-2 px-3">
              <div class="d-flex align-items-center justify-content-between mb-1">
                <span style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">
                  <?= $isSelected ? '▶ ' : '' ?>Match #<?= $m['id'] ?>
                </span>
                <?= statusBadge($m['status']) ?>
              </div>
              <div class="fw-700" style="font-size:.88rem;"><?= e($m['lost_name']) ?></div>
              <div style="font-size:.78rem;color:var(--text-muted);">→ <?= e($m['found_name']) ?></div>
              <div class="d-flex align-items-center gap-2 mt-2">
                <div style="flex:1;height:4px;background:var(--border);border-radius:2px;overflow:hidden;">
                  <div style="height:100%;background:var(--primary);width:<?= $m['similarity_score'] ?>%;"></div>
                </div>
                <strong style="font-size:.85rem;color:var(--primary);"><?= number_format($m['similarity_score'],1) ?>%</strong>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Match Detail -->
      <div class="col-lg-8">
        <?php if ($selected): ?>
        <?php
        $reasons = json_decode($selected['match_reasons'] ?? '{}', true) ?: [];
        $reasonLabels = ['category'=>'Category','item_name'=>'Item Name','color'=>'Color','brand'=>'Brand','description'=>'Description','location'=>'Location','date'=>'Date Proximity'];
        ?>
        <div class="card mb-3">
          <div class="card-header">
            <span class="card-title">
              <i class="bi bi-link-45deg"></i> Match Analysis —
              <strong style="color:var(--primary);"><?= number_format($selected['similarity_score'],1) ?>% similarity</strong>
            </span>
            <?= statusBadge($selected['status']) ?>
          </div>
          <div class="card-body">
            <!-- Score ring + reason bars -->
            <div class="row g-3 mb-4">
              <div class="col-auto d-flex align-items-center justify-content-center">
                <div class="match-score-ring" id="scoreRing"></div>
              </div>
              <div class="col">
                <div class="match-reasons">
                  <?php foreach ($reasonLabels as $key => $label): ?>
                  <?php $pts = $reasons[$key] ?? 0; $max = ['category'=>25,'item_name'=>20,'color'=>15,'brand'=>15,'description'=>15,'location'=>5,'date'=>5][$key] ?? 10; ?>
                  <div class="match-reason-row">
                    <span style="min-width:100px;font-size:.78rem;"><?= $label ?></span>
                    <div class="match-reason-bar">
                      <div class="match-reason-fill" style="width:<?= $max > 0 ? ($pts/$max*100) : 0 ?>%"></div>
                    </div>
                    <span style="font-size:.75rem;font-weight:700;min-width:30px;text-align:right;"><?= $pts ?>/<?= $max ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>

            <!-- Side-by-side comparison -->
            <div class="row g-3">
              <div class="col-md-6">
                <div class="compare-card">
                  <div class="compare-card-header lost">🔍 Your Lost Item</div>
                  <div class="compare-card-body">
                    <?php
                    $fields = [
                      ['Report #', $selected['lost_rn']],
                      ['Item Name', $selected['lost_name']],
                      ['Category', $selected['lost_cat']],
                      ['Location', $selected['lost_loc']],
                      ['Brand', $selected['lost_brand'] ?? '—'],
                      ['Color', $selected['lost_color'] ?? '—'],
                      ['Date Lost', $selected['date_lost'] ? formatDate($selected['date_lost']) : '—'],
                    ];
                    foreach ($fields as [$label, $val]):
                    ?>
                    <div class="compare-row">
                      <span class="compare-label"><?= $label ?></span>
                      <span class="compare-val"><?= e((string)$val) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($selected['lost_desc']): ?>
                    <div style="font-size:.78rem;margin-top:.5rem;color:var(--text-muted);"><?= snippet($selected['lost_desc'], 100) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="compare-card">
                  <div class="compare-card-header found">📦 Found Item</div>
                  <div class="compare-card-body">
                    <?php
                    $ffields = [
                      ['Report #',   $selected['found_rn']],
                      ['Item Name',  $selected['found_name']],
                      ['Category',   $selected['found_cat']],
                      ['Location',   $selected['found_loc']],
                      ['Brand',      $selected['found_brand'] ?? '—'],
                      ['Color',      $selected['found_color'] ?? '—'],
                      ['Date Found', $selected['date_found'] ? formatDate($selected['date_found']) : '—'],
                    ];
                    foreach ($ffields as [$label, $val]):
                    $isMatch = false;
                    if ($label === 'Category')  $isMatch = $selected['lost_cat']   === $selected['found_cat'];
                    if ($label === 'Color')     $isMatch = strcasecmp($selected['lost_color']??'', $selected['found_color']??'') === 0;
                    if ($label === 'Brand')     $isMatch = strcasecmp($selected['lost_brand']??'', $selected['found_brand']??'') === 0;
                    if ($label === 'Location')  $isMatch = $selected['lost_loc']   === $selected['found_loc'];
                    ?>
                    <div class="compare-row">
                      <span class="compare-label"><?= $label ?></span>
                      <span class="compare-val <?= $isMatch?'match':'' ?>"><?= e((string)$val) ?><?= $isMatch ? ' ✓' : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($selected['found_desc']): ?>
                    <div style="font-size:.78rem;margin-top:.5rem;color:var(--text-muted);"><?= snippet($selected['found_desc'], 100) ?></div>
                    <?php endif; ?>
                    <?php if ($selected['storage_location']): ?>
                    <div class="mt-2 p-2" style="background:var(--primary-ultra);border-radius:6px;font-size:.78rem;">
                      <i class="bi bi-geo-alt me-1 text-primary"></i>
                      <strong>Storage:</strong> <?= e($selected['storage_location']) ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php if ($selected['status'] === 'pending' || $selected['status'] === 'confirmed'): ?>
          <div class="card-footer d-flex justify-content-end gap-2">
            <a href="<?= BASE_URL ?>/student/claims.php?action=new&match_id=<?= $selected['id'] ?>&found_id=<?= $selected['found_id'] ?>"
               class="btn btn-primary">
              <i class="bi bi-file-earmark-check me-2"></i> File a Claim for This Item
            </a>
          </div>
          <?php endif; ?>
        </div>

        <?php else: ?>
        <div class="card">
          <div class="card-body">
            <div class="empty-state py-4">
              <i class="bi bi-link-45deg"></i>
              <h5>Select a match</h5>
              <p>Click on a match in the list to view the detailed comparison.</p>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($selected): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    renderScoreRing('scoreRing', <?= $selected['similarity_score'] ?>);
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
