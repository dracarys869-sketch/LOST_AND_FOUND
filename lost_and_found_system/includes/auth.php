<?php
/**
 * Authentication Helpers
 * Handles sessions, role access, CSRF tokens, and audit logging.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ─── Session Timeout ────────────────────────────────────────
function checkSessionTimeout(): void
{
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . '/login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// ─── Login Check ────────────────────────────────────────────
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

// ─── Require Login ──────────────────────────────────────────
function requireLogin(): void
{
    checkSessionTimeout();
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

// ─── Require Specific Role ──────────────────────────────────
function requireRole(string ...$roles): void
{
    requireLogin();
    // Use the current database role, including changes made since this session began.
    if (!in_array(currentUser()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// ─── Current User ───────────────────────────────────────────
function currentUser(): array
{
    if (!isLoggedIn()) return [];

    static $user = null;
    if ($user === null) {
        $user = db()->fetchOne(
            'SELECT id, name, email, role, student_id, phone, profile_photo, status FROM users WHERE id = ? AND status = "active"',
            [$_SESSION['user_id']]
        );
        if (!$user) {
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
    }
    return $user;
}

// ─── Login ──────────────────────────────────────────────────
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']      = $user['id'];
    $_SESSION['role']         = $user['role'];
    $_SESSION['name']         = $user['name'];
    $_SESSION['last_activity'] = time();

    // Update last_login
    db()->execute('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);

    // Audit
    auditLog($user['id'], 'LOGIN', 'users', $user['id']);
}

/** Honor return links only within this installation and the current role's routes. */
function safeLoginRedirect(mixed $target, string $role): string
{
    $fallback = BASE_URL . '/' . (in_array($role, ['student', 'admin', 'security'], true) ? $role : 'student') . '/dashboard.php';
    if (!is_string($target) || $target === '' || preg_match('/[\\\\\x00-\x20]/', $target)) return $fallback;
    $url = parse_url($target);
    $base = parse_url(BASE_URL);
    if ($url === false || isset($url['user']) || isset($url['pass'])) return $fallback;
    if (isset($url['host']) && (strcasecmp($url['host'], $base['host']) !== 0 || ($url['port'] ?? null) !== ($base['port'] ?? null))) return $fallback;
    if (isset($url['scheme']) && $url['scheme'] !== $base['scheme']) return $fallback;
    $path = rawurldecode($url['path'] ?? '');
    $prefix = ($base['path'] ?? '') . '/';
    if (!str_starts_with($path, $prefix) || preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x20]~', $path)) return $fallback;
    $relative = substr($path, strlen($prefix));
    if (!preg_match('~^(student|admin|security)/[a-z_]+\.php$~D', $relative)) return $fallback;
    $destinationRole = strtok($relative, '/');
    $shared = ['student/notifications.php'];
    if ($role !== 'student') $shared[] = 'student/lost_items.php';
    if ($destinationRole !== $role && !in_array($relative, $shared, true)) return $fallback;
    return BASE_URL . '/' . $relative . (isset($url['query']) ? '?' . $url['query'] : '');
}

/** The cookie carries its expiry; hashing the entire value prevents extending it. */
function rememberUser(int $userId, ?int $expires = null): void
{
    $expires ??= time() + 30 * 86400;
    $token = $expires . '.' . bin2hex(random_bytes(32));
    db()->execute('UPDATE users SET remember_token=? WHERE id=?', [hash('sha256', $token), $userId]);
    setcookie('remember_token', $token, [
        'expires' => $expires, 'path' => '/', 'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax',
    ]);
}

function forgetRememberedUser(?int $userId = null): void
{
    if ($userId) db()->execute('UPDATE users SET remember_token=NULL WHERE id=?', [$userId]);
    setcookie('remember_token', '', [
        'expires' => time() - 3600, 'path' => '/', 'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax',
    ]);
    unset($_COOKIE['remember_token']);
}

function restoreRememberedUser(): void
{
    if (isLoggedIn() || !isset($_COOKIE['remember_token'])) return;
    $token = $_COOKIE['remember_token'];
    if (!is_string($token) || !preg_match('/^([0-9]{10})\.[a-f0-9]{64}$/D', $token, $match) || (int)$match[1] <= time()) {
        forgetRememberedUser();
        return;
    }
    $remembered = db()->fetchOne('SELECT id,name,role FROM users WHERE remember_token=? AND status="active" LIMIT 1', [hash('sha256', $token)]);
    if (!$remembered) { forgetRememberedUser(); return; }
    loginUser($remembered);
    rememberUser((int)$remembered['id'], (int)$match[1]);
}

// ─── Logout ─────────────────────────────────────────────────
function logoutUser(): void
{
    $uid = $_SESSION['user_id'] ?? null;
    auditLog($uid, 'LOGOUT', 'users', $uid);
    forgetRememberedUser($uid ? (int)$uid : null);
    session_unset();
    session_destroy();
}

// ─── CSRF ────────────────────────────────────────────────────
function generateCsrfToken(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $token . '">';
}

function verifyCsrf(): void
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!is_string($token) || !is_string($expected) || $token === '' || $expected === '' || !hash_equals($expected, $token)) {
        http_response_code(403);
        die('CSRF token mismatch. Please go back and try again.');
    }
}

// ─── Audit Logging ──────────────────────────────────────────
function auditLog(
    ?int   $userId,
    string $action,
    ?string $table = null,
    ?int   $recordId = null,
    ?array $oldValues = null,
    ?array $newValues = null
): void {
    try {
        db()->execute(
            'INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $table,
                $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR']   ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
    } catch (Exception $e) {
        // Silently fail — audit logging should not break the app
    }
}

// ─── Role Helpers ────────────────────────────────────────────
function isAdmin():    bool { return ($_SESSION['role'] ?? '') === 'admin'; }
function isStudent():  bool { return ($_SESSION['role'] ?? '') === 'student'; }
function isSecurity(): bool { return ($_SESSION['role'] ?? '') === 'security'; }

function roleLabel(): string
{
    return match($_SESSION['role'] ?? '') {
        'admin'    => 'Administrator',
        'student'  => 'Student',
        'security' => 'Security Staff',
        default    => 'Unknown',
    };
}

if (!function_exists('dashboardUrl')) {
    function dashboardUrl(): string
    {
        return match($_SESSION['role'] ?? '') {
            'admin'    => BASE_URL . '/admin/dashboard.php',
            'student'  => BASE_URL . '/student/dashboard.php',
            'security' => BASE_URL . '/security/dashboard.php',
            default    => BASE_URL . '/login.php',
        };
    }
}

// ─── Avatar Initials ─────────────────────────────────────────
if (!function_exists('userInitials')) {
    function userInitials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper($part[0] ?? '');
        }
        return $initials ?: '?';
    }
}

// Global user variable initialization
restoreRememberedUser();
$user = isLoggedIn() ? currentUser() : [];
