<?php
/**
 * Application Configuration
 * School Lost & Found Management System
 */

// ─── Environment ────────────────────────────────────────────
define('APP_ENV',     'development'); // 'development' | 'production'
define('APP_DEBUG',   APP_ENV === 'development');

define('BASE_PATH',   dirname(__DIR__)); // Absolute server path to project root

// Determine protocol and host dynamically to support accessing from external devices (e.g. phone) on the local network
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL',    $protocol . $host . '/lost_and_found_system');

// ─── Directory Paths ────────────────────────────────────────
define('UPLOADS_PATH',   BASE_PATH . '/assets/uploads');
define('QR_CODES_PATH',  BASE_PATH . '/qr_codes');
define('LIBS_PATH',      BASE_PATH . '/assets/libs');
define('REPORTS_PATH',   BASE_PATH . '/reports');

// ─── Directory URLs ─────────────────────────────────────────
define('UPLOADS_URL',    BASE_URL  . '/assets/uploads');
define('QR_CODES_URL',   BASE_URL  . '/qr_codes');
define('ASSETS_URL',     BASE_URL  . '/assets');

// ─── Database ───────────────────────────────────────────────
define('DB_HOST',    getenv('LAF_DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('LAF_DB_PORT') ?: '3367');
define('DB_NAME',    getenv('LAF_DB_NAME') ?: 'lost_and_found_db');
define('DB_USER',    getenv('LAF_DB_USER') ?: 'root');
define('DB_PASS',    getenv('LAF_DB_PASS') !== false ? getenv('LAF_DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ─── Session ────────────────────────────────────────────────
define('SESSION_NAME',    'laf_session');
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// ─── Security ───────────────────────────────────────────────
define('CSRF_TOKEN_NAME', '_csrf_token');
define('BCRYPT_COST',     12);

// ─── File Uploads ───────────────────────────────────────────
define('MAX_FILE_SIZE',       5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTS',  ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ─── Pagination ─────────────────────────────────────────────
define('ITEMS_PER_PAGE', 10);

// ─── Matching Engine ────────────────────────────────────────
define('MATCH_THRESHOLD', 75.0); // Minimum score (%) to flag as a possible match

// ─── Report Number Prefixes ─────────────────────────────────
define('LOST_PREFIX',  'LI');
define('FOUND_PREFIX', 'FI');

// ─── App Meta ───────────────────────────────────────────────
define('APP_NAME',    'Lost & Found System');
define('APP_VERSION', '1.0.0');

// ─── SMTP / Email ────────────────────────────────────────────
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'saad.giancarlo_cyn@isu.edu.ph');
define('SMTP_PASS',     'pvnwkmbtkspsquiv');
define('SMTP_FROM',     'saad.giancarlo_cyn@isu.edu.ph');
define('SMTP_FROM_NAME','Lost & Found System');

// ─── Error Reporting ────────────────────────────────────────
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ─── Timezone ───────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

// ─── Session Bootstrap ──────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
