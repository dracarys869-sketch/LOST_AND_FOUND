<?php
/**
 * Admin — Item Reports (Lost / Found / Matches / Released)
 */
$activePage = 'reports';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('admin');

$type   = sanitize($_GET['type']   ?? 'lost');
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['item_id'] ?? 0);

    if ($act === 'update_status_lost' && $id) {
        $ns = sanitize($_POST['new_status'] ?? '');
        if (in_array($ns, ['pending','active','matched','claimed','closed'])) {
            db()->execute('UPDATE lost_items SET status = ?, admin_notes = ? WHERE id = ?',
                [$ns, sanitize($_POST['notes'] ?? ''), $id]);
            auditLog($user['id'], 'UPDATE_LOST_STATUS', 'lost_items', $id);
            setFlash('success', 'Lost item status updated.');
        }
    }
    if ($act === 'update_status_found' && $id) {
        $ns = sanitize($_POST['new_status'] ?? '');
        if (in_array($ns, ['pending','active','matched','claimed','released'])) {
            db()->execute('UPDATE found_items SET status = ?, security_notes = ? WHERE id = ?',
                [$ns, sanitize($_POST['notes'] ?? ''), $id]);
            auditLog($user['id'], 'UPDATE_FOUND_STATUS', 'found_items', $id);
            setFlash('success', 'Found item status updated.');
        }
    }
    if ($act === 'approve_match' && $id) {
        db()->execute('UPDATE matches SET status = "confirmed", reviewed_by = ? WHERE id = ?', [$user['id'], $id]);
        auditLog($user['id'], 'APPROVE_MATCH', 'matches', $id);
        setFlash('success', 'Match confirmed.');
    }
    if ($act === 'reject_match' && $id) {
        // Revert item statuses
        $match = db()->fetchOne('SELECT * FROM matches WHERE id = ?', [$id]);
        if ($match) {
            db()->execute('UPDATE lost_items  SET status = "active" WHERE id = ? AND status = "matched"', [$match['lost_item_id']]);
            db()->execute('UPDATE found_items SET status = "active" WHERE id = ? AND status = "matched"', [$match['found_item_id']]);
        }
        db()->execute('UPDATE matches SET status = "rejected", reviewed_by = ? WHERE id = ?', [$user['id'], $id]);
        auditLog($user['id'], 'REJECT_MATCH', 'matches', $id);
        setFlash('warning', 'Match rejected. Items returned to active.');
    }
    redirect(BASE_URL . '/admin/reports.php?type=' . $type);
}

// Build query
switch ($type) {
    case 'found':
        $pageTitle = 'Found Item Reports';
        $sql    = 'SELECT fi.*, u.name AS reporter_name, c.name AS cat, l.name AS loc
                   FROM found_items fi
                   LEFT JOIN users u ON fi.user_id=u.id LEFT JOIN categories c ON fi.category_id=c.id
                   LEFT JOIN locations l ON fi.location_id=l.id WHERE fi.status != "released"';
        $params = [];
        if ($search) { $sql .= ' AND (fi.item_name LIKE ? OR fi.brand LIKE ? OR u.name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
        if ($status) { $sql .= ' AND fi.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY fi.created_at DESC';
        break;

    case 'match':
        $pageTitle = 'Item Matches';
        $sql    = 'SELECT m.*, li.item_name AS lost_name, li.report_number AS lost_rn,
                   fi.item_name AS found_name, fi.report_number AS found_rn,
                   u.name AS lost_user
                   FROM matches m
                   LEFT JOIN lost_items li ON m.lost_item_id=li.id
                   LEFT JOIN found_items fi ON m.found_item_id=fi.id
                   LEFT JOIN users u ON li.user_id=u.id WHERE 1=1';
        $params = [];
        if ($status) { $sql .= ' AND m.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY m.created_at DESC';
        break;

    case 'released':
        $pageTitle = 'Released Items';
        $sql    = 'SELECT r.*, fi.item_name, fi.report_number,
                   rb.name AS released_by_name, rt.name AS released_to_name
                   FROM releases r
                   LEFT JOIN found_items fi ON r.found_item_id=fi.id
                   LEFT JOIN users rb ON r.released_by=rb.id
                   LEFT JOIN users rt ON r.released_to=rt.id
                   WHERE 1=1';
        $params = [];
        $sql .= ' ORDER BY r.release_date DESC';
        break;

    default: // lost
        $pageTitle = 'Lost Item Reports';
        $sql    = 'SELECT li.*, u.name AS reporter_name, c.name AS cat, l.name AS loc
                   FROM lost_items li
                   LEFT JOIN users u ON li.user_id=u.id LEFT JOIN categories c ON li.category_id=c.id
                   LEFT JOIN locations l ON li.location_id=l.id WHERE 1=1';
        $params = [];
        if ($search) { $sql .= ' AND (li.item_name LIKE ? OR li.brand LIKE ? OR u.name LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
        if ($status) { $sql .= ' AND li.status = ?'; $params[] = $status; }
        $sql .= ' ORDER BY li.created_at DESC';
        break;
}
$pager = paginate($sql, $params, $page);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> <?= e($pageTitle) ?></div>
    <div class="topbar-actions">
      <a href="<?= BASE_URL ?>/reports/export_csv.php?type=<?= $type ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>" class="btn-export-csv">
        <i class="bi bi-download"></i> Export CSV
      </a>
      <a href="<?= BASE_URL ?>/reports/pdf_report.php?type=<?= $type ?>" class="btn-pdf-report">
        <i class="bi bi-file-earmark-pdf"></i> PDF Report
      </a>
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title"><?= e($pageTitle) ?></h1>
      </div>
    </div>

    <!-- Type Tabs -->
    <ul class="nav nav-pills mb-3" style="gap:.5rem;">
      <?php foreach ([
        ['lost','Lost Items','bi-search'],['found','Found Items','bi-box-seam'],
        ['match','Matches','bi-link-45deg'],['released','Released','bi-check2-circle']
      ] as [$t,$l,$ic]): ?>
      <li class="nav-item">
        <a class="nav-link <?= $type===$t?'active':'' ?>" href="?type=<?= $t ?>">
          <i class="bi <?= $ic ?> me-1"></i><?= $l ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>

    <!-- Filters (lost/found only) -->
    <?php if (in_array($type, ['lost','found'])): ?>
    <div class="filters-row">
      <form method="GET" class="d-flex gap-2 flex-wrap w-100">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <div class="search-bar flex-grow-1">
          <i class="bi bi-search"></i>
          <input type="text" name="search" class="form-control" placeholder="Search item name, brand, reporter…"
                 value="<?= e($search) ?>">
        </div>
        <select name="status" class="form-select" style="max-width:160px;">
          <option value="">All Status</option>
          <?php
          $statuses = $type==='lost'
            ? ['pending','active','matched','claimed','closed']
            : ['pending','active','matched','claimed','released'];
          foreach ($statuses as $s):
          ?>
          <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-outline-primary btn-sm">Filter</button>
        <a href="?type=<?= $type ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </form>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header">
        <span class="card-title">
          <i class="bi bi-table"></i> <?= e($pageTitle) ?>
          <span class="badge bg-secondary ms-1"><?= number_format($pager['total']) ?></span>
        </span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">

          <?php if ($type === 'match'): ?>
          <!-- Matches table -->
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>ID</th><th>Lost Item</th><th>Found Item</th><th>Score</th><th>Status</th><th>Date</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $m): ?>
              <tr>
                <td style="font-size:.75rem;">#<?= $m['id'] ?></td>
                <td>
                  <div class="fw-600" style="font-size:.83rem;"><?= e($m['lost_name']) ?></div>
                  <code style="font-size:.7rem;"><?= e($m['lost_rn']) ?></code>
                </td>
                <td>
                  <div class="fw-600" style="font-size:.83rem;"><?= e($m['found_name']) ?></div>
                  <code style="font-size:.7rem;"><?= e($m['found_rn']) ?></code>
                </td>
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <div style="background:var(--primary);height:6px;border-radius:3px;width:<?= min(100,$m['similarity_score']) ?>px;max-width:80px;"></div>
                    <strong style="font-size:.82rem;"><?= number_format($m['similarity_score'],1) ?>%</strong>
                  </div>
                </td>
                <td><?= statusBadge($m['status']) ?></td>
                <td style="font-size:.75rem;"><?= timeAgo($m['created_at']) ?></td>
                <td>
                  <?php if ($m['status'] === 'pending'): ?>
                  <div class="d-flex gap-1">
                    <form method="POST"><input type="hidden" name="action" value="approve_match"><?= csrfField() ?><input type="hidden" name="item_id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-success py-0 px-2"><i class="bi bi-check-lg"></i></button></form>
                    <form method="POST"><input type="hidden" name="action" value="reject_match"><?= csrfField() ?><input type="hidden" name="item_id" value="<?= $m['id'] ?>"><button class="btn btn-sm btn-outline-danger py-0 px-2"><i class="bi bi-x"></i></button></form>
                  </div>
                  <?php else: ?>
                  <span class="text-muted" style="font-size:.75rem;"><?= ucfirst($m['matched_by']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr><td colspan="7"><div class="empty-state py-3"><i class="bi bi-link-45deg"></i><h5>No matches found</h5></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>

          <?php elseif ($type === 'released'): ?>
          <!-- Released table -->
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>ID</th><th>Item</th><th>Released To</th><th>Released By</th><th>Date</th><th>Notes</th>
            </tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $r): ?>
              <tr>
                <td>#<?= $r['id'] ?></td>
                <td><div class="fw-600" style="font-size:.85rem;"><?= e($r['item_name']) ?></div><code><?= e($r['report_number']) ?></code></td>
                <td><?= e($r['released_to_name']) ?></td>
                <td><?= e($r['released_by_name']) ?></td>
                <td><?= formatDate($r['release_date'], 'M j, Y h:i A') ?></td>
                <td style="font-size:.8rem;"><?= snippet($r['notes'] ?? '', 60) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?><tr><td colspan="6"><div class="empty-state py-3"><i class="bi bi-check2-circle"></i><h5>No releases yet</h5></div></td></tr><?php endif; ?>
            </tbody>
          </table>

          <?php else: ?>
          <!-- Lost / Found items table -->
          <table class="table table-hover mb-0">
            <thead><tr>
              <th>Report #</th><th>Item</th><th>Reporter</th><th>Location</th>
              <th>Date <?= $type==='lost'?'Lost':'Found' ?></th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
              <?php foreach ($pager['rows'] as $item): ?>
              <?php
              $detailUrl = BASE_URL . '/admin/' . ($type==='lost'?'reports.php?type=lost':'reports.php?type=found');
              ?>
              <tr>
                <td><code style="font-size:.75rem;"><?= e($item['report_number']) ?></code></td>
                <td>
                  <div class="fw-600" style="font-size:.85rem;"><?= e($item['item_name']) ?></div>
                  <div style="font-size:.72rem;color:var(--text-muted);">
                    <?= e($item['cat']) ?>
                    <?php if ($item['brand']): ?> · <?= e($item['brand']) ?><?php endif; ?>
                    <?php if ($item['color']): ?> · <?= e($item['color']) ?><?php endif; ?>
                  </div>
                </td>
                <td style="font-size:.82rem;"><?= e($item['reporter_name']) ?></td>
                <td style="font-size:.8rem;"><?= e($item['loc']) ?></td>
                <td style="font-size:.8rem;white-space:nowrap;">
                  <?= formatDate($type==='lost' ? $item['date_lost'] : $item['date_found']) ?>
                </td>
                <td><?= statusBadge($item['status']) ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary py-0 px-2"
                          data-bs-toggle="modal" data-bs-target="#statusModal"
                          data-id="<?= $item['id'] ?>"
                          data-name="<?= e($item['item_name']) ?>"
                          data-status="<?= e($item['status']) ?>"
                          data-type="<?= $type ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($pager['rows'])): ?>
              <tr><td colspan="7"><div class="empty-state py-3"><i class="bi bi-inbox"></i><h5>No items found</h5></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          <?php endif; ?>

        </div>
      </div>
      <?php if ($pager['pages'] > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?= $pager['from'] ?>–<?= $pager['to'] ?> of <?= $pager['total'] ?></small>
        <?= renderPagination($pager) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-pencil"></i> Update Item Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="?type=<?= $type ?>">
        <?= csrfField() ?>
        <input type="hidden" name="action" id="modalAction" value="">
        <input type="hidden" name="item_id" id="modalItemId" value="">
        <div class="modal-body">
          <p style="font-size:.9rem;" id="modalItemName"></p>
          <div class="mb-3">
            <label class="form-label">New Status</label>
            <select name="new_status" class="form-select" id="modalStatus">
              <!-- Dynamically populated by JS based on item type -->
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Admin Notes (optional)</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Add a note…"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Status</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('statusModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    const id   = btn.dataset.id;
    const name = btn.dataset.name;
    const stat = btn.dataset.status;
    const type = btn.dataset.type;

    document.getElementById('modalItemId').value   = id;
    document.getElementById('modalItemName').textContent = name;
    document.getElementById('modalAction').value   = type === 'lost' ? 'update_status_lost' : 'update_status_found';

    const select = document.getElementById('modalStatus');
    select.innerHTML = '';

    const statuses = type === 'lost'
        ? ['pending', 'active', 'matched', 'claimed', 'closed']
        : ['pending', 'active', 'matched', 'claimed', 'released'];

    statuses.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s.charAt(0).toUpperCase() + s.slice(1);
        if (s === stat) {
            opt.selected = true;
        }
        select.appendChild(opt);
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
