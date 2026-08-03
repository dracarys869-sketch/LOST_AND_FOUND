<?php
/**
 * Reports — Export CSV
 * Streams a CSV file download for lost/found/match/release records
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$type   = sanitize($_GET['type']   ?? 'lost');
$search = sanitize($_GET['search'] ?? '');
$status = sanitize($_GET['status'] ?? '');

// Build query
switch ($type) {
    case 'found':
        $title  = 'Found_Items';
        $sql    = 'SELECT fi.report_number, fi.item_name, c.name AS category, l.name AS location,
                   fi.brand, fi.color, fi.date_found, fi.storage_location, fi.status,
                   u.name AS reported_by, fi.created_at
                   FROM found_items fi
                   LEFT JOIN categories c ON fi.category_id=c.id LEFT JOIN locations l ON fi.location_id=l.id LEFT JOIN users u ON fi.user_id=u.id
                   WHERE 1=1';
        $params = [];
        if ($search) { $sql .= ' AND fi.item_name LIKE ?'; $params[] = "%$search%"; }
        if ($status) { $sql .= ' AND fi.status=?';         $params[] = $status; }
        $headers = ['Report #','Item Name','Category','Location Found','Brand','Color','Date Found','Storage','Status','Reported By','Submitted At'];
        break;

    case 'match':
        $title  = 'Matches';
        $sql    = 'SELECT m.id, li.item_name AS lost_item, fi.item_name AS found_item, m.similarity_score, m.status, m.created_at FROM matches m LEFT JOIN lost_items li ON m.lost_item_id=li.id LEFT JOIN found_items fi ON m.found_item_id=fi.id WHERE 1=1';
        $params = [];
        $headers = ['Match ID','Lost Item','Found Item','Score (%)','Status','Date'];
        break;

    case 'release':
        $title  = 'Releases';
        $sql    = 'SELECT r.id, fi.item_name, u.name AS released_to, rb.name AS released_by, r.release_date, r.notes FROM releases r LEFT JOIN found_items fi ON r.found_item_id=fi.id LEFT JOIN users u ON r.released_to=u.id LEFT JOIN users rb ON r.released_by=rb.id WHERE 1=1';
        $params = [];
        $headers = ['Release ID','Item','Released To','Released By','Release Date','Notes'];
        break;

    default: // lost
        $title  = 'Lost_Items';
        $sql    = 'SELECT li.report_number, li.item_name, c.name AS category, l.name AS location,
                   li.brand, li.color, li.date_lost, li.status, u.name AS reported_by, li.created_at
                   FROM lost_items li
                   LEFT JOIN categories c ON li.category_id=c.id LEFT JOIN locations l ON li.location_id=l.id LEFT JOIN users u ON li.user_id=u.id
                   WHERE 1=1';
        $params = [];
        if ($search) { $sql .= ' AND li.item_name LIKE ?'; $params[] = "%$search%"; }
        if ($status) { $sql .= ' AND li.status=?';         $params[] = $status; }
        $headers = ['Report #','Item Name','Category','Location','Brand','Color','Date Lost','Status','Reported By','Submitted At'];
        break;
}
$sql .= ' ORDER BY 1 DESC';
$rows = db()->fetchAll($sql, $params);

// Stream CSV
$filename = $title . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

$f = fopen('php://output', 'w');
// BOM for Excel UTF-8
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, $headers);
foreach ($rows as $row) {
    fputcsv($f, array_values($row));
}
fclose($f);
auditLog($user['id'], 'EXPORT_CSV', $type . '_report', null);
exit;
