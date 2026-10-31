<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';

// PDO::exec supports this additive SQL script on the existing MySQL/MariaDB stack.
$sql = file_get_contents(__DIR__ . '/../database/migrations/001_decision_tree.sql');
try {
    db()->getConnection()->exec($sql);
    echo "Decision Tree tables ready. Existing records were preserved; no labels or model were invented.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
