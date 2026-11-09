<?php
/**
 * Admin — Categories Management
 */
$pageTitle  = 'Categories';
$activePage = 'categories';

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
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? 'bi-tag');
        $color= sanitize($_POST['color'] ?? '#065F46');
        if ($name) {
            db()->insert('INSERT INTO categories (name, description, icon, color) VALUES (?,?,?,?)', [$name,$desc,$icon,$color]);
            setFlash('success', "Category '{$name}' created.");
        }
    }
    if ($action === 'update') {
        $id   = (int)($_POST['cat_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $desc = sanitize($_POST['description'] ?? '');
        $icon = sanitize($_POST['icon'] ?? 'bi-tag');
        $color= sanitize($_POST['color'] ?? '#065F46');
        $stat = in_array($_POST['status'],['active','inactive']) ? $_POST['status'] : 'active';
        if ($id && $name) {
            db()->execute('UPDATE categories SET name=?,description=?,icon=?,color=?,status=? WHERE id=?', [$name,$desc,$icon,$color,$stat,$id]);
            setFlash('success', "Category updated.");
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['cat_id'] ?? 0);
        // Check if in use
        $inUse = db()->fetchOne('SELECT COUNT(*) AS c FROM lost_items WHERE category_id=?',[$id])['c'] +
                 db()->fetchOne('SELECT COUNT(*) AS c FROM found_items WHERE category_id=?',[$id])['c'];
        if ($inUse > 0) { setFlash('danger','Cannot delete: category is in use by existing items.'); }
        else { db()->execute('DELETE FROM categories WHERE id=?',[$id]); setFlash('success','Category deleted.'); }
    }
    redirect(BASE_URL . '/admin/categories.php');
}

$categories = db()->fetchAll(
    'SELECT c.*, (SELECT COUNT(*) FROM lost_items WHERE category_id=c.id)+(SELECT COUNT(*) FROM found_items WHERE category_id=c.id) AS item_count
     FROM categories c ORDER BY c.name ASC'
);

$extraHead = '<script>const BASE_URL="' . BASE_URL . '";</script>';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';

$ICONS = ['bi-phone','bi-laptop','bi-headphones','bi-person-badge','bi-bag','bi-book','bi-card-text','bi-gem','bi-activity','bi-key','bi-wallet2','bi-tag','bi-three-dots','bi-umbrella','bi-bicycle','bi-camera','bi-music-note','bi-sunglasses'];
?>

<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin /</span> Categories</div>
    <div class="topbar-actions">
      
    </div>
  </div>

  <div class="content-area">
    <div class="page-header">
      <div>
        <h1 class="page-title">Item Categories</h1>
        <p class="page-subtitle">Organize lost and found items by type.</p>
      </div>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCatModal">
        <i class="bi bi-plus-lg me-1"></i> Add Category
      </button>
    </div>

    <div class="row g-3">
      <?php foreach ($categories as $cat): ?>
      <div class="col-sm-6 col-md-4 col-xl-3">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
              <div class="stat-icon category-icon">
                <i class="bi <?= e($cat['icon']) ?>"></i>
              </div>
              <div>
                <h2 class="h6 mb-1"><?= e($cat['name']) ?></h2>
                <?= statusBadge($cat['status']) ?>
              </div>
            </div>
            <?php if ($cat['description']): ?>
            <p class="small"><?= e($cat['description']) ?></p>
            <?php endif; ?>
            <div class="text-muted-sm numeric">
              <span class="category-swatch" style="background-color:<?= e($cat['color']) ?>;" title="Category color: <?= e($cat['color']) ?>" aria-hidden="true"></span><?= number_format((int)$cat['item_count']) ?> items
            </div>
          </div>
          <div class="card-footer d-flex gap-2">
            <button class="btn btn-sm btn-outline-primary flex-grow-1"
                    data-bs-toggle="modal" data-bs-target="#editCatModal"
                    data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>"
                    data-desc="<?= e($cat['description'] ?? '') ?>" data-icon="<?= e($cat['icon']) ?>"
                    data-color="<?= e($cat['color']) ?>" data-status="<?= e($cat['status']) ?>">
              <i class="bi bi-pencil me-1"></i> Edit
            </button>
            <?php if ((int)$cat['item_count'] === 0): ?>
            <form method="POST" class="d-inline" onsubmit="return confirmDelete('Delete category?')">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="cat_id" value="<?= $cat['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" aria-label="Delete <?= e($cat['name']) ?>" data-pending-label="Deleting…"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($categories)): ?>
      <div class="col-12"><div class="empty-state"><i class="bi bi-tags"></i><h5>No categories yet</h5><p>Add item categories to organize reports.</p></div></div>
      <?php endif; ?>
    </div>
  </div>
</main>

<!-- Create Modal -->
<div class="modal fade" id="createCatModal" tabindex="-1" aria-labelledby="create-cat-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title" id="create-cat-title">Add category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="create-cat-name">Name <span class="text-danger">*</span></label><input type="text" name="name" id="create-cat-name" class="form-control" required></div>
          <div class="mb-3"><label class="form-label" for="create-cat-desc">Description</label><textarea name="description" id="create-cat-desc" class="form-control" rows="2"></textarea></div>
          <div class="row g-3 mb-3">
            <div class="col-8">
              <label class="form-label" for="create-cat-icon">Icon (Bootstrap Icons class)</label>
              <select name="icon" id="create-cat-icon" class="form-select">
                <?php foreach ($ICONS as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label" for="create-cat-color">Color</label>
              <input type="color" name="color" id="create-cat-color" class="form-control form-control-color w-100" value="#065F46">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCatModal" tabindex="-1" aria-labelledby="edit-cat-title">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title" id="edit-cat-title">Edit category</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="cat_id" id="editCatId">
        <div class="modal-body">
          <div class="mb-3"><label class="form-label" for="editCatName">Name</label><input type="text" name="name" class="form-control" id="editCatName" required></div>
          <div class="mb-3"><label class="form-label" for="editCatDesc">Description</label><textarea name="description" class="form-control" rows="2" id="editCatDesc"></textarea></div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label" for="editCatIcon">Icon</label>
              <select name="icon" class="form-select" id="editCatIcon">
                <?php foreach ($ICONS as $ic): ?><option value="<?= $ic ?>"><?= $ic ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-3"><label class="form-label" for="editCatColor">Color</label><input type="color" name="color" class="form-control form-control-color w-100" id="editCatColor"></div>
            <div class="col-3">
              <label class="form-label" for="editCatStatus">Status</label>
              <select name="status" class="form-select" id="editCatStatus">
                <option value="active">Active</option><option value="inactive">Inactive</option>
              </select>
            </div>
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
document.getElementById('editCatModal')?.addEventListener('show.bs.modal', function(e) {
    const b = e.relatedTarget;
    document.getElementById('editCatId').value      = b.dataset.id;
    document.getElementById('editCatName').value    = b.dataset.name;
    document.getElementById('editCatDesc').value    = b.dataset.desc;
    document.getElementById('editCatIcon').value    = b.dataset.icon;
    document.getElementById('editCatColor').value   = b.dataset.color;
    document.getElementById('editCatStatus').value  = b.dataset.status;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
