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
$action = $_GET['action'] ?? 'count';

match ($action) {
    'count' => (function () use ($uid) {
        echo json_encode(['success' => true, 'count' => unreadCount($uid)]);
    })(),

    'list' => (function () use ($uid) {
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 10)));
        $notifs = getNotifications($uid, $limit);
        echo json_encode(['success' => true, 'notifications' => $notifs]);
    })(),

    'mark_read' => (function () use ($uid) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id) markNotificationRead($id, $uid);
        echo json_encode(['success' => true]);
    })(),

    'mark_all_read' => (function () use ($uid) {
        markAllNotificationsRead($uid);
        echo json_encode(['success' => true]);
    })(),

    default => json_encode(['success' => false, 'message' => 'Unknown action.']),
};
