<?php
/** Public campus item board. */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/item_board.php';
if (isLoggedIn()) redirect(dashboardUrl());

$schoolName = getSetting('school_name', APP_NAME);
$allowRegistration = getSetting('allow_registration', true);
$isDark = ($_COOKIE['dark_mode'] ?? '') === '1';
$board = loadItemBoard($_GET, BASE_URL . '/index.php');
$returnedCount = (int)(db()->fetchOne('SELECT COUNT(*) AS n FROM releases')['n'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en" <?= $isDark ? 'data-theme="dark"' : '' ?>>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="description" content="Find your way back to the things that matter. Browse lost and found reports at <?= e($schoolName) ?>, report an item, and follow its return.">
  <title>Campus Lost &amp; Found — <?= e($schoolName) ?></title>
  <link rel="icon" type="image/png" href="<?= ASSETS_URL ?>/images/lost-found-logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css?v=<?= filemtime(BASE_PATH . '/assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/landing.css?v=<?= filemtime(BASE_PATH . '/assets/css/landing.css') ?>">
</head>
<body class="public-page">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="public-header">
  <nav class="public-nav" aria-label="Main navigation">
    <a href="<?= BASE_URL ?>/" class="public-brand"><img src="<?= ASSETS_URL ?>/images/lost-found-logo.png" alt="" width="44" height="44"><span><strong>Campus Lost &amp; Found</strong><small><?= e($schoolName) ?></small></span></a>
    <div class="public-nav-links"><a href="#item-board">Browse items</a><a href="#how-it-works">How it works</a></div>
    <div class="public-nav-actions">
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-secondary" id="btnLogin">Sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
      <?php if ($allowRegistration): ?><a href="<?= BASE_URL ?>/register.php" class="btn btn-primary" id="btnRegister">Create account</a><?php endif; ?>
    </div>
  </nav>
</header>

<main id="main-content" tabindex="-1">
  <section class="public-hero" aria-labelledby="hero-title">
    <div class="public-hero-copy">
      <span class="eyebrow"><span class="eyebrow-line" aria-hidden="true"></span> A LITTLE HELP FROM YOUR CAMPUS</span>
      <h1 id="hero-title">A little lost.<br><span>Closer to found.</span></h1>
      <p>That favorite water bottle. Your everyday backpack. Let's help you find your way back to the things that matter.</p>
      <div class="action-row">
        <a href="<?= BASE_URL ?>/login.php?redirect=<?= rawurlencode(BASE_URL . '/student/lost_items.php') ?>" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg" aria-hidden="true"></i> Report a lost item</a>
        <a href="<?= BASE_URL ?>/login.php?redirect=<?= rawurlencode(BASE_URL . '/student/found_items.php') ?>" class="btn btn-outline-secondary btn-lg">I found something <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      </div>
      <div class="hero-community"><i class="bi bi-people" aria-hidden="true"></i><span>A shared space for students and campus staff.</span></div>
    </div>
    <div class="public-hero-visual">
      <img src="<?= ASSETS_URL ?>/images/campus-belongings.svg" alt="An illustration of a backpack, water bottle, and keys beside a campus lost and found box." width="540" height="390" fetchpriority="high">
      <div class="hero-visual-caption"><span class="hero-caption-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span><span><strong>Good things find their way back.</strong><small>Report it. Match it. Reclaim it.</small></span></div>
    </div>
  </section>

  <section class="public-stats" aria-label="Campus report totals">
    <div><span class="public-stat-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span><span><strong><?= number_format($board['counts']['found']) ?></strong><span>Found items on the board</span></span></div>
    <div><span class="public-stat-icon"><i class="bi bi-search" aria-hidden="true"></i></span><span><strong><?= number_format($board['counts']['lost']) ?></strong><span>Open lost reports</span></span></div>
    <div><span class="public-stat-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span><span><strong><?= number_format($returnedCount) ?></strong><span>Items returned to their owners</span></span></div>
  </section>

  <div class="public-board-wrap"><?php include __DIR__ . '/includes/item_board_view.php'; ?></div>

  <section class="public-how" id="how-it-works" aria-labelledby="how-title">
    <div class="how-heading"><span class="eyebrow">FROM MISSING TO REUNITED</span><h2 id="how-title">A simple path back.</h2><p>A few details from you. A little help from your campus.</p></div>
    <ol class="how-steps">
      <li><span class="step-number">01</span><i class="bi bi-pencil-square" aria-hidden="true"></i><h3>Share the details</h3><p>Report what you lost or found, where it was, and when. A photo can help identify it.</p></li>
      <li><span class="step-number">02</span><i class="bi bi-intersect" aria-hidden="true"></i><h3>Look for a match</h3><p>Browse the board and review possible matches. Recognize your item? Submit an ownership claim.</p></li>
      <li><span class="step-number">03</span><i class="bi bi-bag-check" aria-hidden="true"></i><h3>Make it a happy return</h3><p>Staff review your evidence. Once your claim is approved, visit security to collect your item.</p></li>
    </ol>
  </section>
</main>

<footer class="public-footer"><div><a href="<?= BASE_URL ?>/" class="footer-brand"><i class="bi bi-box-seam" aria-hidden="true"></i> Campus Lost &amp; Found</a><span>&copy; <?= date('Y') ?> <?= e($schoolName) ?></span></div><a href="<?= BASE_URL ?>/login.php">Student &amp; staff sign in <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></footer>
<script src="<?= ASSETS_URL ?>/js/main.js?v=<?= filemtime(BASE_PATH . '/assets/js/main.js') ?>"></script>
</body>
</html>
