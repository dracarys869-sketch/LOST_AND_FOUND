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

/** Resolve a notification to an existing role-appropriate screen. */
function notificationUrl(array $notification, string $role): string
{
    $id = (int)($notification['related_id'] ?? 0);
    $type = $notification['related_type'] ?? $notification['type'] ?? '';
    if ($type === 'claim') {
        return match ($role) {
            'admin' => BASE_URL . '/admin/' . ($id ? 'claim_review.php?id=' . $id : 'claims.php'),
            'security' => BASE_URL . '/security/claims.php' . ($id ? '?focus=' . $id : ''),
            default => BASE_URL . '/student/claims.php',
        };
    }
    if ($type === 'match') {
        return $role === 'student' ? BASE_URL . '/student/matches.php' . ($id ? '?id=' . $id : '')
            : BASE_URL . ($role === 'admin' ? '/admin/reports.php?type=match' : '/security/claims.php');
    }
    if ($type === 'release') return BASE_URL . ($role === 'student' ? '/student/claims.php' : '/security/release.php');
    return match ($role) {
        'admin' => BASE_URL . '/admin/reports.php',
        'security' => BASE_URL . '/security/found_items.php',
        default => BASE_URL . '/student/my_reports.php' . ($type === 'found_item' ? '?type=found' : ''),
    };
}
