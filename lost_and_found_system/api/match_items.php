<?php
/**
 * API — Run Match Engine
 * POST /api/match_items.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/matching_engine.php';

header('Content-Type: application/json; charset=utf-8');

requireRole('admin', 'security');

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$type     = $data['type']    ?? '';
$itemId   = (int)($data['item_id'] ?? 0);

if (!$itemId || !in_array($type, ['lost', 'found'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    if ($type === 'lost') {
        $count = MatchingEngine::runForLostItem($itemId);
    } else {
        $count = MatchingEngine::runForFoundItem($itemId);
    }
    echo json_encode(['success' => true, 'matches_created' => $count]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
