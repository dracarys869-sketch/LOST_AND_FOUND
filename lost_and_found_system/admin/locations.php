<?php
/**
 * Admin — Locations Management
 */
$pageTitle  = 'Locations';
$activePage = 'locations';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = sanitize($_POST['name']     ?? '');
        $bldg  = sanitize($_POST['building'] ?? '');
        $floor = sanitize($_POST['floor']    ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        if ($name) {
            db()->insert('INSERT INTO locations (name,building,floor,description) VALUES (?,?,?,?)', [$name,$bldg,$floor,$desc]);
            setFlash('success', "Location '{$name}' added.");
        }
    }
    if ($action === 'update') {
        $id    = (int)($_POST['loc_id'] ?? 0);
        $name  = sanitize($_POST['name']     ?? '');
        $bldg  = sanitize($_POST['building'] ?? '');
        $floor = sanitize($_POST['floor']    ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $stat  = in_array($_POST['status'],['active','inactive']) ? $_POST['status'] : 'active';
        if ($id && $name) {
            db()->execute('UPDATE locations SET name=?,building=?,floor=?,description=?,status=? WHERE id=?', [$name,$bldg,$floor,$desc,$stat,$id]);
            setFlash('success', 'Location updated.');
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['loc_id'] ?? 0);
        $inUse = db()->fetchOne('SELECT COUNT(*) AS c FROM lost_items WHERE location_id=?',[$id])['c'] +
                 db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE location_id=?',[$id])['c'];
        if ($inUse > 0) { setFlash('danger','Cannot delete: location is in use.'); }
        else { db()->execute('DELETE FROM locations WHERE id=?',[$id]); setFlash('success','Location deleted.'); }
    }
    redirect(BASE_URL . '/admin/locations.php');
}

$locations = db()->fetchAll(
    'SELECT l.*, (SELECT COUNT(*) FROM lost_items WHERE location_id=l.id)+(SELECT COUNT(*) FROM found_items WHERE location_id=l.id) AS item_count
     FROM locations l ORDER BY l.building, l.name ASC'
);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Locations</div>
    <div class="topbar-actions">
      
    </div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Campus Locations</h1><p class="page-subtitle">Manage areas where items can be lost or found.</p></div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLocModal">
        <i class="bi bi-plus-lg me-1"></i> Add Location
      </button>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>Location Name</th><th>Building</th><th>Floor</th><th>Items</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($locations as $loc): ?>
              <tr>
                <td><?= $loc['id'] ?></td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="stat-icon icon-sm"><i class="bi bi-geo-alt"></i></div>
                    <span class="fw-600"><?= e($loc['name']) ?></span>
                  </div>
                </td>
                <td><?= e($loc['building'] ?? '—') ?></td>
                <td><?= e($loc['floor']    ?? '—') ?></td>
                <td>
                  <span class="badge bg-secondary"><?= number_format((int)$loc['item_count']) ?></span>
                </td>
                <td><?= statusBadge($loc['status']) ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary py-0 px-2"
                            data-bs-toggle="modal" data-bs-target="#editLocModal"
                            data-id="<?= $loc['id'] ?>" data-name="<?= e($loc['name']) ?>"
                            data-building="<?= e($loc['building']??'') ?>" data-floor="<?= e($loc['floor']??'') ?>"
                            data-desc="<?= e($loc['description']??'') ?>" data-status="<?= $loc['status'] ?>" aria-label="Edit <?= e($loc['name']) ?>">
                      <i class="bi bi-pencil"></i>
                    </button>
                    <?php if ((int)$loc['item_count'] === 0): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirmDelete('Delete location?')">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="loc_id" value="<?= $loc['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger py-0 px-2" aria-label="Delete <?= e($loc['name']) ?>" data-pending-label="Deleting…"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($locations)): ?>
              <tr><td colspan="7"><div class="empty-state py-3"><i class="bi bi-geo"></i><h5>No locations yet</h5></div></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Create Modal -->
<div class="modal fade" id="createLocModal" tabindex="-1" aria-labelledby="create-loc-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title" id="create-loc-title">Add location</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="create-loc-name">Location Name <span class="text-danger">*</span></label><input type="text" name="name" id="create-loc-name" class="form-control" required placeholder="e.g. Main Library"></div>
          <div class="row g-3 mb-3">
            <div class="col-7"><label class="form-label" for="create-loc-building">Building</label><input type="text" name="building" id="create-loc-building" class="form-control" placeholder="e.g. Main Building"></div>
            <div class="col-5"><label class="form-label" for="create-loc-floor">Floor</label><input type="text" name="floor" id="create-loc-floor" class="form-control" placeholder="e.g. 2nd Floor"></div>
          </div>
          <div class="mb-3"><label class="form-label" for="create-loc-desc">Description</label><textarea name="description" id="create-loc-desc" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Location</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editLocModal" tabindex="-1" aria-labelledby="edit-loc-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title" id="edit-loc-title">Edit location</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="update"><input type="hidden" name="loc_id" id="editLocId">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="editLocName">Name</label><input type="text" name="name" class="form-control" id="editLocName" required></div>
          <div class="row g-3 mb-3">
            <div class="col-7"><label class="form-label" for="editLocBuilding">Building</label><input type="text" name="building" class="form-control" id="editLocBuilding"></div>
            <div class="col-5"><label class="form-label" for="editLocFloor">Floor</label><input type="text" name="floor" class="form-control" id="editLocFloor"></div>
          </div>
          <div class="mb-3"><label class="form-label" for="editLocDesc">Description</label><textarea name="description" class="form-control" rows="2" id="editLocDesc"></textarea></div>
          <div class="mb-3"><label class="form-label" for="editLocStatus">Status</label>
            <select name="status" class="form-select" id="editLocStatus">
              <option value="active">Active</option><option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('editLocModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editLocId').value       = b.dataset.id;
    document.getElementById('editLocName').value     = b.dataset.name;
    document.getElementById('editLocBuilding').value = b.dataset.building;
    document.getElementById('editLocFloor').value    = b.dataset.floor;
    document.getElementById('editLocDesc').value     = b.dataset.desc;
    document.getElementById('editLocStatus').value   = b.dataset.status;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
