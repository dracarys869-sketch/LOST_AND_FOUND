<?php
/**
 * API — Notifications
 * GET /api/notifications.php?action=count
 * GET /api/notifications.php?action=list&limit=5
 * POST /api/notifications.php (action in JSON body)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

$uid    = $user['id'];
$input = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) jsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
}
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($input['action'] ?? '') : ($_GET['action'] ?? 'count');
if (in_array($action, ['mark_read', 'mark_all_read'], true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'message' => 'Use POST to update notifications.'], 405);
    $token = $input[CSRF_TOKEN_NAME] ?? '';
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!is_string($token) || $token === '' || $expected === '' || !hash_equals($expected, $token)) {
        jsonResponse(['success' => false, 'message' => 'Your session could not be verified. Reload the page and try again.'], 403);
    }
}

match ($action) {
    'count' => (function () use ($uid) {
        echo json_encode(['success' => true, 'count' => unreadCount($uid)]);
    })(),

    'list' => (function () use ($uid) {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $notifs = getNotifications($uid, $limit);
        echo json_encode(['success' => true, 'notifications' => $notifs]);
    })(),

    'mark_read' => (function () use ($uid, $input) {
        $id = (int)($input['id'] ?? 0);
        if ($id) markNotificationRead($id, $uid);
        echo json_encode(['success' => true]);
    })(),

    'mark_all_read' => (function () use ($uid) {
        markAllNotificationsRead($uid);
        echo json_encode(['success' => true]);
    })(),

    default => jsonResponse(['success' => false, 'message' => 'Unknown action.'], 400),
};
