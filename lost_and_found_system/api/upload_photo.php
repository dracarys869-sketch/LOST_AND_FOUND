<?php
/**
 * API — Upload Photo
 * POST /api/upload_photo.php
 * FormData: file=<image>, type=lost|found, item_id=<int>
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required.']); exit;
}
verifyCsrf();

$type   = sanitize($_POST['type']   ?? '');
$itemId = (int)($_POST['item_id']   ?? 0);

if (!$itemId || !in_array($type, ['lost', 'found'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']); exit;
}

// Check ownership (student can only upload to their own items)
if ($user['role'] === 'student') {
    $table = $type === 'lost' ? 'lost_items' : 'found_items';
    $row   = db()->fetchOne("SELECT user_id FROM {$table} WHERE id=?", [$itemId]);
    if (!$row || (int)$row['user_id'] !== (int)$user['id']) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']); exit;
    }
}

if (empty($_FILES['file']['name'])) {
    echo json_encode(['success' => false, 'message' => 'No file provided.']); exit;
}

$path = uploadFile($_FILES['file'], $type . '_items');

if (!$path) {
    echo json_encode(['success' => false, 'message' => 'Upload failed. Check file type and size.']); exit;
}

// Save to DB
$existing = db()->fetchOne("SELECT id FROM item_photos WHERE item_type=? AND item_id=?", [$type, $itemId]);
db()->insert(
    'INSERT INTO item_photos (item_type, item_id, photo_path, is_primary) VALUES (?,?,?,?)',
    [$type, $itemId, $path, $existing ? 0 : 1]
);

echo json_encode([
    'success'  => true,
    'path'     => $path,
    'url'      => photoUrl($path),
]);
