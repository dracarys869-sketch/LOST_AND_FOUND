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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="School Lost and Found Management System — Report, track, and recover lost items on campus.">
  <title>Lost &amp; Found — Gian Saad Academy</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
</head>
<body style="background: var(--bg); color: var(--text);">

<!-- Hero Section -->
<section class="landing-hero" style="min-height: 85vh; padding-bottom: 5rem;">
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
<section class="container py-5" style="margin-top: -3rem; position: relative; z-index: 10;">
  <div class="card shadow-lg border-0" style="border-radius: var(--radius-md);">
    <div class="card-header bg-white border-0 pt-4 px-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
          <h2 class="fw-800 text-primary mb-1" style="font-size: 1.5rem;">Public Feed</h2>
          <p class="text-muted mb-0" style="font-size: 0.85rem;">Browse recently reported items on campus</p>
        </div>
        <ul class="nav nav-pills" id="feedTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active btn-sm px-3" id="lost-tab" data-bs-toggle="pill" data-bs-target="#lost-pane" type="button" role="tab" aria-selected="true">
              <i class="bi bi-search me-1"></i> Lost Items
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link btn-sm px-3" id="found-tab" data-bs-toggle="pill" data-bs-target="#found-pane" type="button" role="tab" aria-selected="false">
              <i class="bi bi-box-seam me-1"></i> Found Items
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
            <div class="text-center py-5 text-muted">
              <i class="bi bi-info-circle display-4 mb-2"></i>
              <p>No active lost item reports currently listed.</p>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($lostItems as $item): 
                $photo = itemPrimaryPhoto('lost', $item['id']);
              ?>
                <div class="col-md-6 col-lg-4">
                  <div class="card h-100 border" style="border-radius: var(--radius);">
                    <?php if ($photo): ?>
                      <img src="<?= e($photo) ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= e($item['item_name']) ?>">
                    <?php else: ?>
                      <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 180px;">
                        <i class="bi bi-image" style="font-size: 2.5rem;"></i>
                      </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-direction-column justify-content-between">
                      <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <h5 class="fw-700 text-truncate mb-0" style="font-size: 1rem; max-width: 75%;"><?= e($item['item_name']) ?></h5>
                          <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Lost</span>
                        </div>
                        <p class="text-muted-sm mb-3" style="font-size: 0.8rem;"><?= e(implode(' · ', array_filter([$item['brand'], $item['color']]))) ?></p>
                      </div>
                      <div class="border-top pt-2" style="font-size: 0.75rem;">
                        <div class="mb-1"><i class="bi bi-tag me-1 text-primary"></i><strong>Category:</strong> <?= e($item['cat']) ?></div>
                        <div class="mb-1"><i class="bi bi-geo-alt me-1 text-primary"></i><strong>Last Seen:</strong> <?= e($item['loc']) ?></div>
                        <div><i class="bi bi-calendar-event me-1 text-primary"></i><strong>Date Lost:</strong> <?= formatDate($item['date_lost']) ?></div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Found Pane -->
        <div class="tab-pane fade" id="found-pane" role="tabpanel" tabindex="0">
          <?php if (empty($foundItems)): ?>
            <div class="text-center py-5 text-muted">
              <i class="bi bi-info-circle display-4 mb-2"></i>
              <p>No active found item reports currently listed.</p>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($foundItems as $item): 
                $photo = itemPrimaryPhoto('found', $item['id']);
              ?>
                <div class="col-md-6 col-lg-4">
                  <div class="card h-100 border" style="border-radius: var(--radius);">
                    <?php if ($photo): ?>
                      <img src="<?= e($photo) ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="<?= e($item['item_name']) ?>">
                    <?php else: ?>
                      <div class="d-flex align-items-center justify-content-center bg-light text-muted" style="height: 180px;">
                        <i class="bi bi-image" style="font-size: 2.5rem;"></i>
                      </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-direction-column justify-content-between">
                      <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                          <h5 class="fw-700 text-truncate mb-0" style="font-size: 1rem; max-width: 75%;"><?= e($item['item_name']) ?></h5>
                          <span class="badge bg-success" style="font-size: 0.65rem;">Found</span>
                        </div>
                        <p class="text-muted-sm mb-3" style="font-size: 0.8rem;"><?= snippet($item['description'] ?? '', 80) ?></p>
                      </div>
                      <div class="border-top pt-2" style="font-size: 0.75rem;">
                        <div class="mb-1"><i class="bi bi-tag me-1 text-primary"></i><strong>Category:</strong> <?= e($item['cat']) ?></div>
                        <div class="mb-1"><i class="bi bi-geo-alt me-1 text-primary"></i><strong>Location:</strong> <?= e($item['loc']) ?></div>
                        <div><i class="bi bi-calendar-event me-1 text-primary"></i><strong>Date Found:</strong> <?= formatDate($item['date_found']) ?></div>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
