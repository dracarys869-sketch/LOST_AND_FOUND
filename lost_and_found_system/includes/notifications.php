<?php
/**
 * In-App Notification Helpers
 */

require_once __DIR__ . '/../config/database.php';

function createNotification(
    int    $userId,
    string $title,
    string $message,
    string $type       = 'info',
    ?int   $relatedId  = null,
    ?string $relatedType = null
): int {
    return (int)db()->insert(
        'INSERT INTO notifications (user_id, title, message, type, related_id, related_type)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $title, $message, $type, $relatedId, $relatedType]
    );
}

function markNotificationRead(int $notifId, int $userId): void
{
    db()->execute(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
        [$notifId, $userId]
    );
}

function markAllNotificationsRead(int $userId): void
{
    db()->execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);
}

function getNotifications(int $userId, int $limit = 20, bool $unreadOnly = false): array
{
    $where = $unreadOnly ? 'AND is_read = 0' : '';
    return db()->fetchAll(
        "SELECT * FROM notifications WHERE user_id = ? {$where} ORDER BY created_at DESC LIMIT ?",
        [$userId, $limit]
    );
}

function unreadCount(int $userId): int
{
    $row = db()->fetchOne(
        'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
    return (int)($row['cnt'] ?? 0);
}
