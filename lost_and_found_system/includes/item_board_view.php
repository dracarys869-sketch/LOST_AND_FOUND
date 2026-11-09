<?php
/** Requires $board; $boardInPortal enables direct ownership-claim links. */
$boardInPortal = $boardInPortal ?? false;
$boardPager = $board['pager'];
?>
<section class="item-board" id="item-board" aria-labelledby="board-title">
  <div class="board-heading">
    <div>
      <span class="eyebrow">THE CAMPUS NOTICEBOARD</span>
      <h2 id="board-title">A familiar item could be right here.</h2>
      <p>Search reports by item, brand, color, or campus location.</p>
    </div>
    <nav class="board-tabs" aria-label="Item type">
      <?php foreach (['found' => ['bi-box-seam', 'Found items'], 'lost' => ['bi-search', 'Lost reports']] as $boardType => [$boardIcon, $boardLabel]): ?>
      <a href="<?= e(itemBoardUrl($board, ['type' => $boardType])) ?>" class="board-tab <?= $board['type'] === $boardType ? 'active' : '' ?>" <?= $board['type'] === $boardType ? 'aria-current="page"' : '' ?>>
        <i class="bi <?= $boardIcon ?>" aria-hidden="true"></i> <?= $boardLabel ?>
        <span class="board-count"><?= number_format($board['counts'][$boardType]) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <form method="GET" action="<?= e($board['base_url']) ?>#item-board" class="board-filters" role="search" aria-label="Search campus reports" data-pending-label="Finding items…">
    <input type="hidden" name="type" value="<?= $board['type'] ?>">
    <div class="board-search">
      <label class="visually-hidden" for="board-search">Item, brand, color, or location</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input type="search" id="board-search" class="form-control" name="search" maxlength="100" value="<?= e($board['search']) ?>" placeholder="What are you looking for?">
    </div>
    <div>
      <label class="visually-hidden" for="board-category">Category</label>
      <select name="category" id="board-category" class="form-select">
        <option value="">All categories</option>
        <?php foreach ($board['categories'] as $boardCategory): ?>
        <option value="<?= (int)$boardCategory['id'] ?>" <?= (int)$boardCategory['id'] === $board['category'] ? 'selected' : '' ?>><?= e($boardCategory['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="visually-hidden" for="board-sort">Sort reports</label>
      <select name="sort" id="board-sort" class="form-select">
        <option value="newest" <?= $board['sort'] === 'newest' ? 'selected' : '' ?>>Newest first</option>
        <option value="oldest" <?= $board['sort'] === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Search <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
  </form>

  <div class="board-results-summary">
    <p><strong><?= number_format($boardPager['total']) ?></strong> <?= $board['type'] === 'found' ? 'found item' : 'lost report' ?><?= $boardPager['total'] === 1 ? '' : 's' ?><?= $board['search'] !== '' ? ' for “' . e($board['search']) . '”' : ' on the board' ?></p>
    <?php if ($board['search'] !== '' || $board['category']): ?>
    <a class="text-link" href="<?= e(itemBoardUrl($board, ['search' => '', 'category' => 0])) ?>">Clear filters</a>
    <?php else: ?>
    <span class="small text-muted"><?= $board['sort'] === 'oldest' ? 'Oldest' : 'Most recent' ?> reports first</span>
    <?php endif; ?>
  </div>

  <?php if (!$boardPager['rows']): ?>
  <div class="card empty-state board-empty">
    <i class="bi <?= $board['search'] !== '' || $board['category'] ? 'bi-search' : 'bi-inbox' ?>" aria-hidden="true"></i>
    <h3><?= $board['search'] !== '' || $board['category'] ? 'No matching reports' : 'No ' . ($board['type'] === 'found' ? 'found items' : 'lost reports') . ' right now' ?></h3>
    <p><?= $board['search'] !== '' || $board['category'] ? 'Try a different word, a broader description, or another category.' : 'New reports will appear here. You can submit a report to help get an item home.' ?></p>
    <div class="action-row justify-content-center">
      <?php if ($board['search'] !== '' || $board['category']): ?>
      <a href="<?= e(itemBoardUrl($board, ['search' => '', 'category' => 0])) ?>" class="btn btn-outline-secondary">Clear filters</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/<?= $boardInPortal ? 'student/lost_items.php' : 'login.php?redirect=' . rawurlencode(BASE_URL . '/student/lost_items.php') ?>" class="btn btn-primary">Report a lost item</a>
    </div>
  </div>
  <?php else: ?>
  <div class="item-board-grid">
    <?php foreach ($boardPager['rows'] as $boardItem):
      $boardPhoto = itemPrimaryPhoto($board['type'], (int)$boardItem['id']);
      $categoryIcon = preg_match('/^bi-[a-z0-9-]+$/D', $boardItem['category_icon'] ?? '') ? $boardItem['category_icon'] : 'bi-box-seam';
      $claimUrl = BASE_URL . '/student/claims.php?action=new&found_id=' . (int)$boardItem['id'];
      $itemActionUrl = $board['type'] === 'found' ? $claimUrl : BASE_URL . '/student/found_items.php';
      if (!$boardInPortal) $itemActionUrl = BASE_URL . '/login.php?redirect=' . rawurlencode($itemActionUrl);
    ?>
    <article class="board-item">
      <div class="board-item-media">
        <div class="board-item-placeholder" <?= $boardPhoto ? 'hidden' : '' ?>>
          <i class="bi <?= e($categoryIcon) ?>" aria-hidden="true"></i>
          <span>No photo provided</span>
        </div>
        <?php if ($boardPhoto): ?>
        <img src="<?= e($boardPhoto) ?>" alt="<?= e($boardItem['item_name']) ?>" loading="lazy" decoding="async" data-item-image>
        <?php endif; ?>
        <span class="item-type-tag <?= $board['type'] ?>"><span aria-hidden="true"></span><?= $board['type'] === 'found' ? 'Found' : 'Lost' ?></span>
      </div>
      <div class="board-item-body">
        <span class="item-category"><?= e($boardItem['category_name'] ?: 'Uncategorized') ?></span>
        <h3><?= e($boardItem['item_name']) ?></h3>
        <?php $boardDetails = implode(' · ', array_filter([$boardItem['brand'], $boardItem['color']])); ?>
        <?php if ($boardDetails !== ''): ?><p class="board-item-details"><?= e($boardDetails) ?></p><?php endif; ?>
        <div class="board-item-meta">
          <span><i class="bi bi-geo-alt" aria-hidden="true"></i><span><span class="visually-hidden"><?= $board['type'] === 'lost' ? 'Last seen: ' : 'Found at: ' ?></span><?= e($boardItem['location_name'] ?: 'Location not specified') ?></span></span>
          <span><i class="bi bi-calendar3" aria-hidden="true"></i><time datetime="<?= e($boardItem['item_date']) ?>"><?= formatDate($boardItem['item_date']) ?></time></span>
        </div>
        <a href="<?= e($itemActionUrl) ?>" class="board-item-action" aria-label="<?= $board['type'] === 'found' ? 'Claim ' : 'Report finding ' ?><?= e($boardItem['item_name']) ?>">
          <?= $board['type'] === 'found' ? 'Is this yours?' : 'Found this item?' ?> <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
        </a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="board-pagination">
    <span class="small text-muted">Showing <?= $boardPager['from'] ?>–<?= $boardPager['to'] ?> of <?= number_format($boardPager['total']) ?></span>
    <?= renderPagination($boardPager, '', 'item-board') ?>
  </div>
  <?php endif; ?>
</section>
