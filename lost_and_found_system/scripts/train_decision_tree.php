<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/claim_ml.php';
try {
    $options = getopt('', ['admin-id:']);
    $adminId = ClaimFeatures::id($options['admin-id'] ?? null, 'administrator ID (use --admin-id=ID)');
    $version = claimMl()->train($adminId);
    $row = db()->fetchOne('SELECT metrics_json FROM decision_tree_models WHERE version=?', [$version]);
    echo "Saved and activated Decision Tree {$version}.\n";
    echo json_encode(ClaimMlService::decode($row['metrics_json']), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Training not completed: ' . $e->getMessage() . "\n");
    exit(1);
}
