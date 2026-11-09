<?php
/**
 * QR Code Generator Helper
 * Uses phpqrcode library (bundled locally)
 */

// Path to phpqrcode library
define('PHPQRCODE_DIR', BASE_PATH . '/includes/phpqrcode/');

function generateQRCode(int $itemId, string $reportNumber): ?string
{
    $libFile = PHPQRCODE_DIR . 'qrlib.php';
    if (!file_exists($libFile)) {
        error_log("phpqrcode library not found at {$libFile}");
        return null;
    }

    require_once $libFile;

    // QR data = lookup URL for this item
    $url     = rtrim(BASE_URL, '/') . '/api/qr_lookup.php?id=' . $itemId;
    $outDir  = rtrim(QR_CODES_PATH, '/\\') . '/';

    if (!is_dir($outDir)) { mkdir($outDir, 0755, true); }

    $filename = $outDir . 'qr_' . $reportNumber . '.png';

    try {
        QRcode::png($url, $filename, QR_ECLEVEL_M, 6, 2);
        if (!is_file($filename) || filesize($filename) === 0) throw new RuntimeException('The QR image was not written.');
        return 'qr_codes/qr_' . $reportNumber . '.png';
    } catch (Throwable $e) {
        error_log('QR generation failed: ' . $e->getMessage());
        return null;
    }
}
