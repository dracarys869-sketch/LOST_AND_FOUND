<?php
/**
 * Landing Page — redirects to login or role dashboard
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/claim_ml.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    header('Location: ' . BASE_URL . '/' . $role . '/dashboard.php');
    exit;
}

// Publish general item information; ownership details remain private.
$privacyReady = claimMl()->schemaReady();
$lostItems = db()->fetchAll(
    'SELECT li.id,li.item_name,li.brand,li.color,li.date_lost,c.name AS cat,l.name AS loc
     FROM lost_items li 
     LEFT JOIN categories c ON li.category_id=c.id 
     LEFT JOIN locations l ON li.location_id=l.id 
     WHERE li.status = "active" 
     ORDER BY li.created_at DESC LIMIT 6'
);

$foundItems = db()->fetchAll(
    'SELECT fi.id,fi.item_name,fi.brand,fi.color,fi.date_found,c.name AS cat,l.name AS loc,
     ' . ($privacyReady ? 'v.public_description' : 'NULL') . ' AS description
     FROM found_items fi 
     LEFT JOIN categories c ON fi.category_id=c.id 
     LEFT JOIN locations l ON fi.location_id=l.id
     ' . ($privacyReady ? 'LEFT JOIN found_item_verification v ON v.found_item_id=fi.id ' : '') . '
     WHERE fi.status = "active"
     ORDER BY fi.created_at DESC LIMIT 6'
);
$hiddenLostCount = (int)(db()->fetchOne(
    'SELECT COUNT(*) AS c FROM lost_items WHERE status <> "active"'
)['c'] ?? 0);
$hiddenFoundCount = (int)(db()->fetchOne(
    'SELECT COUNT(*) AS c FROM found_items WHERE status <> "active"'
)['c'] ?? 0);

$schoolName = getSetting('school_name', APP_NAME);
$isDark     = isset($_COOKIE['dark_mode']) && $_COOKIE['dark_mode'] === '1';
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($schoolName) ?> Lost and Found Management System — Report, track, and recover lost items on campus.">
  <title>Lost &amp; Found — <?= e($schoolName) ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body>

<!-- Hero Section -->
<section class="landing-hero">
  <div class="hero-content animate-fade-in">
    <div class="hero-badge">
      <i class="bi bi-shield-check"></i> Campus Lost &amp; Found Portal
    </div>
    <h1 class="hero-title">
      Lost Something?<br>We Can Help You Find It.
    </h1>
    <p class="hero-desc">
      A smart, organized system to report, track, and reunite students with their lost belongings — powered by an intelligent matching engine.
    </p>
    <div class="hero-cta">
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-light btn-lg px-4 fw-700" id="btnLogin">
        <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
      </a>
      <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-light btn-lg px-4" id="btnRegister">
        <i class="bi bi-person-plus me-2"></i> Student Registration
      </a>
    </div>
  </div>
</section>

<!-- Public Items Feed -->
<section class="container landing-feed py-5" style="margin-top: -3.5rem; position: relative; z-index: 10;">
  <div class="card public-feed-card">
    <div class="card-header border-0 pt-4 px-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 w-100">
        <div>
          <h2 class="fw-800 mb-1" style="font-size: 1.5rem; color: var(--text);">Public Feed</h2>
          <p class="mb-0" style="font-size: 0.85rem; color: var(--text-muted);">Browse recently reported items on campus</p>
        </div>
        <ul class="nav nav-pills" id="feedTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="lost-tab" data-bs-toggle="pill" data-bs-target="#lost-pane" type="button" role="tab" aria-selected="true">
              <i class="bi bi-search me-1"></i> Lost Items <span class="badge rounded-pill bg-light text-primary"><?= count($lostItems) ?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="found-tab" data-bs-toggle="pill" data-bs-target="#found-pane" type="button" role="tab" aria-selected="false">
              <i class="bi bi-box-seam me-1"></i> Found Items <span class="badge rounded-pill bg-light text-primary"><?= count($foundItems) ?></span>
            </button>
          </li>
        </ul>
      </div>
    </div>
    <div class="card-body p-4">
      <div class="tab-content" id="feedTabsContent">
        <!-- Lost Pane -->
        <div class="tab-pane fade show active" id="lost-pane" role="tabpanel" tabindex="0">
          <?php if (empty($lostItems)): ?>
            <div class="empty-state py-5">
              <i class="bi bi-inbox"></i>
              <h5>No active lost reports</h5>
              <p>No active lost item reports are currently listed. Items that are matched, claimed, or closed are not shown here.</p>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($lostItems as $item):
                $photo = itemPrimaryPhoto('lost', $item['id']);
                $sub   = implode(' · ', array_filter([$item['brand'], $item['color']]));
                $sub   = $sub !== '' ? e($sub) : 'No additional details';
              ?>
                <div class="col-md-6 col-lg-4">
                  <article class="card public-item-card h-100">
                    <div class="public-item-media">
                      <?php if ($photo): ?>
                        <img src="<?= e($photo) ?>" alt="<?= e($item['item_name']) ?>" loading="lazy">
                      <?php else: ?>
                        <div class="media-placeholder"><i class="bi bi-image"></i></div>
                      <?php endif; ?>
                      <span class="public-item-badge lost">Lost</span>
                    </div>
                    <div class="public-item-body">
                      <div>
                        <h3 class="public-item-title text-truncate" title="<?= e($item['item_name']) ?>"><?= e($item['item_name']) ?></h3>
                        <p class="public-item-sub"><?= $sub ?></p>
                      </div>
                      <div class="public-item-meta">
                        <div class="meta-row"><i class="bi bi-tag"></i><span><strong>Category:</strong> <?= e($item['cat'] ?: 'Uncategorized') ?></span></div>
                        <div class="meta-row"><i class="bi bi-geo-alt"></i><span><strong>Last seen:</strong> <?= e($item['loc'] ?: 'Unknown') ?></span></div>
                        <div class="meta-row"><i class="bi bi-calendar-event"></i><span><strong>Date lost:</strong> <?= formatDate($item['date_lost']) ?></span></div>
                      </div>
                    </div>
                  </article>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($hiddenLostCount > 0): ?>
              <p class="small text-muted mt-4 mb-0"><i class="bi bi-info-circle me-1"></i><?= $hiddenLostCount ?> older lost report<?= $hiddenLostCount === 1 ? '' : 's' ?> hidden because the status is matched, claimed, or closed.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Found Pane -->
        <div class="tab-pane fade" id="found-pane" role="tabpanel" tabindex="0">
          <?php if (empty($foundItems)): ?>
            <div class="empty-state py-5">
              <i class="bi bi-inbox"></i>
              <h5>No active found reports</h5>
              <p>No active found item reports are currently listed. Items that are matched, claimed, or released are not shown here.</p>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($foundItems as $item):
                $photo = itemPrimaryPhoto('found', $item['id']);
                $sub   = implode(' · ', array_filter([$item['brand'], $item['color']]));
                $sub   = $sub !== '' ? e($sub) : (!empty($item['description']) ? snippet($item['description'], 60) : 'No additional details');
              ?>
                <div class="col-md-6 col-lg-4">
                  <article class="card public-item-card h-100">
                    <div class="public-item-media">
                      <?php if ($photo): ?>
                        <img src="<?= e($photo) ?>" alt="<?= e($item['item_name']) ?>" loading="lazy">
                      <?php else: ?>
                        <div class="media-placeholder"><i class="bi bi-image"></i></div>
                      <?php endif; ?>
                      <span class="public-item-badge found">Found</span>
                    </div>
                    <div class="public-item-body">
                      <div>
                        <h3 class="public-item-title text-truncate" title="<?= e($item['item_name']) ?>"><?= e($item['item_name']) ?></h3>
                        <p class="public-item-sub"><?= $sub ?></p>
                      </div>
                      <div class="public-item-meta">
                        <div class="meta-row"><i class="bi bi-tag"></i><span><strong>Category:</strong> <?= e($item['cat'] ?: 'Uncategorized') ?></span></div>
                        <div class="meta-row"><i class="bi bi-geo-alt"></i><span><strong>Location:</strong> <?= e($item['loc'] ?: 'Unknown') ?></span></div>
                        <div class="meta-row"><i class="bi bi-calendar-event"></i><span><strong>Date found:</strong> <?= formatDate($item['date_found']) ?></span></div>
                      </div>
                    </div>
                  </article>
                </div>
              <?php endforeach; ?>
            </div>
            <?php if ($hiddenFoundCount > 0): ?>
              <p class="small text-muted mt-4 mb-0"><i class="bi bi-info-circle me-1"></i><?= $hiddenFoundCount ?> older found report<?= $hiddenFoundCount === 1 ? '' : 's' ?> hidden because the status is matched, claimed, or released.</p>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<footer class="landing-footer">
  <div class="landing-footer-inner">
    <span>&copy; <?= date('Y') ?> <?= e($schoolName) ?>. All rights reserved.</span>
    <span>
      <a href="<?= BASE_URL ?>/login.php">Sign In</a>
      <span class="mx-2">·</span>
      <a href="<?= BASE_URL ?>/register.php">Create Account</a>
    </span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
