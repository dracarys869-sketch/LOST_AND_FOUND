<?php
/** Serve report photos publicly and ownership proof photos only to authorized users. */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
if (isLoggedIn()) checkSessionTimeout();
$path = $_GET['path'] ?? '';
if (!is_string($path) || !preg_match('~^(lost_items|found_items|claim_proofs)/[a-zA-Z0-9_.-]+\.(jpg|jpeg|png|gif|webp)$~D', $path)) {
    http_response_code(404); exit;
}
$staff = in_array($user['role'] ?? '', ['admin', 'security'], true);
$allowed = false;
if (str_starts_with($path, 'claim_proofs/')) {
    $claim = db()->fetchOne('SELECT claimant_id FROM claims WHERE proof_photo=? LIMIT 1', [$path]);
    $allowed = $claim && ($staff || (int)($user['id'] ?? 0) === (int)$claim['claimant_id']);
} else {
    $photo = db()->fetchOne('SELECT id,item_type,item_id FROM item_photos WHERE photo_path=? LIMIT 1', [$path]);
    if ($photo) {
        $table = $photo['item_type'] === 'found' ? 'found_items' : 'lost_items';
        $item = db()->fetchOne("SELECT user_id FROM {$table} WHERE id=?", [$photo['item_id']]);
        $allowed = (bool)$item;
    }
}
if (!$allowed) { http_response_code(403); exit; }
$file = realpath(UPLOADS_PATH . '/' . $path);
$root = realpath(UPLOADS_PATH);
if (!$file || !$root || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $root) . '/') || !is_file($file)) {
    http_response_code(404); exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file) . '"');
readfile($file);
