<?php
/**
 * API — QR Lookup
 * GET /api/qr_lookup.php?id=<item_id_or_report_number>
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireRole('security', 'admin');

$id = sanitize($_GET['id'] ?? '');

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'No ID provided.']);
    exit;
}

// Try to find by numeric ID first, then by report_number
$item = null;
if (is_numeric($id)) {
    $item = db()->fetchOne(
        'SELECT fi.*, c.name AS category, l.name AS location FROM found_items fi
         LEFT JOIN categories c ON fi.category_id=c.id
         LEFT JOIN locations l ON fi.location_id=l.id
         WHERE fi.id=?',
        [(int)$id]
    );
}
if (!$item) {
    $item = db()->fetchOne(
        'SELECT fi.*, c.name AS category, l.name AS location FROM found_items fi
         LEFT JOIN categories c ON fi.category_id=c.id
         LEFT JOIN locations l ON fi.location_id=l.id
         WHERE fi.report_number=?',
        [$id]
    );
}

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit;
}

// Return safe subset
echo json_encode([
    'success' => true,
    'item' => [
        'id'              => $item['id'],
        'report_number'   => $item['report_number'],
        'item_name'       => $item['item_name'],
        'category'        => $item['category'],
        'location'        => $item['location'],
        'brand'           => $item['brand'],
        'color'           => $item['color'],
        'description'     => $item['description'],
        'date_found'      => $item['date_found'],
        'storage_location'=> $item['storage_location'],
        'status'          => $item['status'],
    ],
]);
