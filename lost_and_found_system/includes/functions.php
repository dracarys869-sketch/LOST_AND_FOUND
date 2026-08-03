<?php
/**
 * Shared Utility Functions
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ─── Report Number Generator ─────────────────────────────────
function generateReportNumber(string $prefix, string $table): string
{
    $year = date('Y');
    $row  = db()->fetchOne(
        "SELECT COUNT(*) AS cnt FROM {$table} WHERE YEAR(created_at) = ?",
        [$year]
    );
    $seq = str_pad(($row['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return "{$prefix}-{$year}-{$seq}";
}

// ─── File Upload ─────────────────────────────────────────────
/**
 * Handle single file upload.
 * Returns relative path (from uploads dir) or false on failure.
 */
function uploadFile(array $file, string $subDir = ''): string|false
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validate size
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return false;
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTS, true)) {
        return false;
    }

    // Build destination
    $destDir = UPLOADS_PATH . ($subDir ? '/' . trim($subDir, '/') : '');
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $destPath = $destDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }

    return ($subDir ? trim($subDir, '/') . '/' : '') . $filename;
}

/**
 * Handle multiple file uploads.
 * @return array Array of uploaded file paths
 */
function uploadMultipleFiles(array $files, string $subDir = ''): array
{
    $paths = [];
    // Normalize $_FILES array structure
    $count = is_array($files['name']) ? count($files['name']) : 1;

    if (!is_array($files['name'])) {
        // Single file wrapped in array format
        $result = uploadFile($files, $subDir);
        if ($result) $paths[] = $result;
        return $paths;
    }

    for ($i = 0; $i < $count; $i++) {
        $single = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
        $result = uploadFile($single, $subDir);
        if ($result) $paths[] = $result;
    }

    return $paths;
}

// ─── Sanitization ────────────────────────────────────────────
function e(mixed $val): string
{
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function sanitize(string $input): string
{
    return trim(strip_tags($input));
}

// ─── Flash Messages ──────────────────────────────────────────
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function renderFlash(): void
{
    $flash = getFlash();
    if (!$flash) return;
    $type = $flash['type'];
    $msg  = e($flash['message']);
    echo "<div class='alert alert-{$type} alert-dismissible fade show animate-slide-down' role='alert'>
            <i class='bi bi-" . ($type === 'success' ? 'check-circle' : ($type === 'danger' ? 'exclamation-circle' : 'info-circle')) . " me-2'></i>
            {$msg}
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
          </div>";
}

// ─── Pagination ──────────────────────────────────────────────
function paginate(string $sql, array $params, int $page, int $perPage = ITEMS_PER_PAGE): array
{
    // Count total
    $countSql = preg_replace('/SELECT\s.+?\sFROM/is', 'SELECT COUNT(*) AS total FROM', $sql);
    $countSql = preg_replace('/ORDER BY.+$/is', '', $countSql);
    $total    = (int)(db()->fetchOne($countSql, $params)['total'] ?? 0);
    $pages    = max(1, (int)ceil($total / $perPage));
    $page     = max(1, min($page, $pages));
    $offset   = ($page - 1) * $perPage;

    $rows = db()->fetchAll($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);

    return [
        'rows'       => $rows,
        'total'      => $total,
        'page'       => $page,
        'pages'      => $pages,
        'per_page'   => $perPage,
        'from'       => $total === 0 ? 0 : $offset + 1,
        'to'         => min($offset + $perPage, $total),
    ];
}

function renderPagination(array $pager, string $baseUrl = ''): string
{
    if ($pager['pages'] <= 1) return '';
    $base = $baseUrl ?: (strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_diff_key($_GET, ['page' => ''])) . '&');
    $html = '<nav><ul class="pagination mb-0">';
    $html .= '<li class="page-item' . ($pager['page'] <= 1 ? ' disabled' : '') . '">
                <a class="page-link" href="' . $base . 'page=' . ($pager['page'] - 1) . '"><i class="bi bi-chevron-left"></i></a></li>';

    $start = max(1, $pager['page'] - 2);
    $end   = min($pager['pages'], $pager['page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base . 'page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pager['page'] ? ' active' : '';
        $html .= "<li class='page-item{$active}'><a class='page-link' href='{$base}page={$i}'>{$i}</a></li>";
    }
    if ($end < $pager['pages']) {
        if ($end < $pager['pages'] - 1) $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $base . 'page=' . $pager['pages'] . '">' . $pager['pages'] . '</a></li>';
    }

    $html .= '<li class="page-item' . ($pager['page'] >= $pager['pages'] ? ' disabled' : '') . '">
                <a class="page-link" href="' . $base . 'page=' . ($pager['page'] + 1) . '"><i class="bi bi-chevron-right"></i></a></li>';
    $html .= '</ul></nav>';
    return $html;
}

// ─── Status Badge ────────────────────────────────────────────
function statusBadge(string $status): string
{
    $labels = [
        'pending'   => 'Pending',
        'active'    => 'Active',
        'matched'   => 'Matched',
        'claimed'   => 'Claimed',
        'closed'    => 'Closed',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'released'  => 'Released',
        'confirmed' => 'Confirmed',
        'inactive'  => 'Inactive',
        'suspended' => 'Suspended',
    ];
    $label = $labels[$status] ?? ucfirst($status);
    return "<span class='badge-status badge-{$status}'>" . e($label) . "</span>";
}

function typeBadge(string $type): string
{
    $icons = [
        'match'   => 'bi-link-45deg',
        'success' => 'bi-check-circle',
        'warning' => 'bi-exclamation-triangle',
        'info'    => 'bi-info-circle',
        'claim'   => 'bi-file-earmark-check',
    ];
    $icon = $icons[$type] ?? 'bi-bell';
    return "<span class='badge-status badge-{$type}'><i class='{$icon} me-1'></i>" . ucfirst($type) . "</span>";
}

// ─── Date Helpers ─────────────────────────────────────────────
function formatDate(string $date, string $format = 'M j, Y'): string
{
    return date($format, strtotime($date));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60)   . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600)  . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

// ─── Photo URL Helper ─────────────────────────────────────────
function photoUrl(string $path): string
{
    return UPLOADS_URL . '/' . ltrim($path, '/');
}

function itemPrimaryPhoto(string $type, int $id): ?string
{
    $row = db()->fetchOne(
        'SELECT photo_path FROM item_photos WHERE item_type = ? AND item_id = ? AND is_primary = 1 LIMIT 1',
        [$type, $id]
    );
    return $row ? photoUrl($row['photo_path']) : null;
}

function itemPhotos(string $type, int $id): array
{
    $rows = db()->fetchAll(
        'SELECT id, photo_path, is_primary FROM item_photos WHERE item_type = ? AND item_id = ? ORDER BY is_primary DESC, id ASC',
        [$type, $id]
    );
    return array_map(fn($r) => array_merge($r, ['url' => photoUrl($r['photo_path'])]), $rows);
}

// ─── Settings ─────────────────────────────────────────────────
function getSetting(string $key, mixed $default = null): mixed
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $row = db()->fetchOne('SELECT setting_value, setting_type FROM settings WHERE setting_key = ?', [$key]);
        if (!$row) return $default;
        $cache[$key] = match($row['setting_type']) {
            'number'  => (float)$row['setting_value'],
            'boolean' => (bool)(int)$row['setting_value'],
            'json'    => json_decode($row['setting_value'], true),
            default   => $row['setting_value'],
        };
    }
    return $cache[$key];
}

// ─── Categories & Locations ──────────────────────────────────
function getCategories(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE status = "active"' : '';
    return db()->fetchAll("SELECT * FROM categories {$where} ORDER BY name ASC");
}

function getLocations(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'WHERE status = "active"' : '';
    return db()->fetchAll("SELECT * FROM locations {$where} ORDER BY name ASC");
}

// ─── Notification Count ──────────────────────────────────────
function unreadNotifCount(int $userId): int
{
    $row = db()->fetchOne(
        'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
    return (int)($row['cnt'] ?? 0);
}

// ─── JSON Response ───────────────────────────────────────────
function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ─── Redirect ────────────────────────────────────────────────
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Text snippet ────────────────────────────────────────────
function snippet(string $text, int $length = 80): string
{
    $plain = strip_tags($text);
    if (mb_strlen($plain) <= $length) return e($plain);
    return e(mb_substr($plain, 0, $length)) . '…';
}

// ─── User Initials ───────────────────────────────────────────
if (!function_exists('userInitials')) {
    function userInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $init  = mb_strtoupper(mb_substr($parts[0], 0, 1));
        if (count($parts) > 1) {
            $init .= mb_strtoupper(mb_substr($parts[count($parts) - 1], 0, 1));
        }
        return e($init);
    }
}

// ─── Dashboard URL by role ───────────────────────────────────
if (!function_exists('dashboardUrl')) {
    function dashboardUrl(): string
    {
        $role = $_SESSION['role'] ?? 'student';
        return BASE_URL . '/' . $role . '/dashboard.php';
    }
}



// ─── Audit Log ───────────────────────────────────────────────
// (proxy to auth.php auditLog — included before functions.php in pages)
if (!function_exists('auditLog')) {
    function auditLog(
        ?int $userId, string $action, ?string $table = null,
        ?int $recordId = null, ?array $oldData = null, ?array $newData = null
    ): void {
        // no-op if auth.php not loaded yet
    }
}

// ─── fw helpers ─────────────────────────────────────────────
function fw(string $weight): string { return "fw-{$weight}"; }
