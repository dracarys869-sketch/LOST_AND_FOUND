<?php
/** Regression checks for report recovery, validation, and sign-in return links. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';

if (($argv[1] ?? '') !== '--worker') {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $name = 'laf_report_test_' . bin2hex(random_bytes(6));
    $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $code = 1;
    try {
        $pdo->exec("USE `{$name}`");
        $schema = strstr(file_get_contents(__DIR__ . '/../database/lost_and_found_db.sql'), '--  SEED DATA', true);
        if ($schema === false) throw new RuntimeException('Missing test schema.');
        $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS `lost_and_found_db`.*?;/s', '', $schema);
        $schema = str_replace('USE `lost_and_found_db`;', '', $schema);
        if (preg_match('/\b(CREATE\s+DATABASE|USE\s+`)/i', $schema)) throw new RuntimeException('Unexpected database switch.');
        $pdo->exec($schema);
        $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/001_decision_tree.sql'));
        $environment = getenv();
        $environment['LAF_DB_NAME'] = $name;
        $process = proc_open([PHP_BINARY, '-d', 'session.save_path="' . str_replace('\\', '/', ini_get('session.save_path')) . '"', __FILE__, '--worker', $name], [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, __DIR__, $environment);
        if (!is_resource($process)) throw new RuntimeException('Could not start isolated report tests.');
        echo stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($process);
    } finally { $pdo->exec("DROP DATABASE `{$name}`"); }
    exit($code);
}

if (!preg_match('/^laf_report_test_[a-f0-9]{12}$/D', DB_NAME) || ($argv[2] ?? '') !== DB_NAME) throw new RuntimeException('An isolated database is required.');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/item_reports.php';
require_once __DIR__ . '/../includes/item_board.php';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new RuntimeException($message);
    $checks++;
};
$categories = [['id' => 1]];
$locations = [['id' => 1]];
$valid = ['item_name' => 'Campus backpack', 'category_id' => '1', 'location_id' => '1', 'date_lost' => date('Y-m-d'), 'time_lost' => '14:30', 'brand' => 'Example', 'color' => 'Green'];
$check(reportInput($valid, 'lost', $categories, $locations)['errors'] === [], 'A valid lost report should pass.');
foreach ([
    ['item_name' => 'x'], ['item_name' => str_repeat('a', 151)], ['category_id' => '99'],
    ['location_id' => '99'], ['date_lost' => '2026-02-30'], ['date_lost' => 'garbage'],
    ['date_lost' => date('Y-m-d', strtotime('+1 day'))], ['date_lost' => "2026-01-01\0"],
    ['time_lost' => '25:00'], ['item_name' => ['not text']], ['color' => str_repeat('x', 51)],
] as $invalid) {
    $check((bool)reportInput(array_replace($valid, $invalid), 'lost', $categories, $locations)['errors'], 'Malformed report must fail validation: ' . json_encode($invalid));
}
$check(reportInput(array_replace($valid, ['item_name' => '  éé  ', 'time_lost' => '']), 'lost', $categories, $locations)['errors'] === [], 'Unicode names and an unknown time remain valid.');
$found = array_replace($valid, ['date_found' => date('Y-m-d'), 'time_found' => '09:15']);
$check(reportInput($found, 'found', $categories, $locations)['errors'] === [], 'A valid found report should pass.');

$root = parse_url(BASE_URL, PHP_URL_PATH);
$fallback = BASE_URL . '/student/dashboard.php';
$check(safeLoginRedirect($root . '/student/claims.php?action=new&found_id=4', 'student') === BASE_URL . '/student/claims.php?action=new&found_id=4', 'Claim return links must survive sign-in.');
$check(safeLoginRedirect(BASE_URL . '/student/lost_items.php', 'security') === BASE_URL . '/student/lost_items.php', 'Security can return to the shared lost-report form.');
foreach ([null, [], 'https://example.invalid', '//example.invalid/x', '/other/student/dashboard.php', $root . '/admin/dashboard.php', $root . '/student/../admin/dashboard.php', $root . '/student/%2e%2e/admin/dashboard.php', $root . '/student/\\evil.php', "\r\nLocation: x"] as $target) {
    $check(safeLoginRedirect($target, 'student') === $fallback, 'Unsafe / cross-role return link must use the dashboard.');
}

db()->execute('INSERT INTO users (id,name,email,password,role) VALUES (1,"Report Test","report@test.invalid","not-a-login-password","student")');
db()->execute('INSERT INTO categories (id,name) VALUES (1,"Bags")');
db()->execute('INSERT INTO locations (id,name) VALUES (1,"Library")');
$values = reportInput($valid, 'lost', $categories, $locations)['values'];
$saved = saveItemReport('lost', 1, $values);
$check((bool)db()->fetchOne('SELECT id FROM lost_items WHERE id=?', [$saved['id']]), 'A valid report must persist.');
$check($saved['matching_complete'], 'Matching completes when its dependencies are available.');
$second = saveItemReport('lost', 1, $values);
db()->execute('DELETE FROM lost_items WHERE id=?', [$saved['id']]);
$third = saveItemReport('lost', 1, $values);
$check($third['report_number'] !== $second['report_number'], 'Deleting an older report must not cause duplicate report numbers.');

$before = (int)db()->fetchOne('SELECT COUNT(*) AS n FROM lost_items')['n'];
try {
    saveItemReport('lost', 1, $values, ['name' => ['oversized.png'], 'tmp_name' => [''], 'type' => ['image/png'], 'error' => [UPLOAD_ERR_INI_SIZE], 'size' => [6000000]]);
    throw new RuntimeException('Invalid photos must not be silently accepted.');
} catch (InvalidArgumentException $error) { $checks++; }
$check((int)db()->fetchOne('SELECT COUNT(*) AS n FROM lost_items')['n'] === $before, 'Failed uploads must not leave partial reports.');
$check(!db()->getConnection()->inTransaction(), 'Failed uploads must close their transaction.');

db()->execute('RENAME TABLE found_items TO found_items_temporarily_unavailable');
try {
    $deferred = saveItemReport('lost', 1, $values);
    $check(!$deferred['matching_complete'], 'Post-save matching failure is reported separately.');
    $check((bool)db()->fetchOne('SELECT id FROM lost_items WHERE id=?', [$deferred['id']]), 'A matching outage must preserve the submitted report.');
    $check(!db()->getConnection()->inTransaction(), 'Matching recovery must not try to roll back an already committed report.');
} finally { db()->execute('RENAME TABLE found_items_temporarily_unavailable TO found_items'); }

db()->execute('INSERT INTO found_items (report_number,user_id,category_id,location_id,item_name,brand,color,date_found,status) VALUES ("FI-TEST-PUBLIC",1,1,1,"Notebook 100%","Example","Blue",CURDATE(),"active"),("FI-TEST-MATCHED",1,1,1,"Matched backpack","Example","Green",CURDATE(),"matched")');
$board = loadItemBoard(['search' => '%'], BASE_URL . '/index.php');
$check($board['pager']['total'] === 1 && $board['pager']['rows'][0]['item_name'] === 'Notebook 100%', 'Search percent must be literal, not a wildcard.');
$check(loadItemBoard(['search' => 'Library'], BASE_URL . '/index.php')['pager']['total'] === 1, 'Campus location search must work.');
$check(loadItemBoard([], BASE_URL . '/student/browse.php', true)['pager']['total'] === 2, 'Students can browse items with unconfirmed matches.');
$check(loadItemBoard(['type' => 'invalid', 'search' => [], 'category' => []], BASE_URL . '/index.php')['type'] === 'found', 'Malformed board filters must recover safely.');

// Exercise the real found-item / QR path with a unique filename owned by this test.
$qrSequence = random_int(900000000, 999999900);
$guardNumber = FOUND_PREFIX . '-' . date('Y') . '-' . $qrSequence;
$nextNumber = FOUND_PREFIX . '-' . date('Y') . '-' . ($qrSequence + 1);
$testQr = QR_CODES_PATH . '/qr_' . $nextNumber . '.png';
if (file_exists($testQr)) throw new RuntimeException('Refusing to overwrite an existing QR file.');
db()->execute('INSERT INTO found_items (report_number,user_id,category_id,location_id,item_name,date_found,status) VALUES (?,1,1,1,"QR sequence fixture",CURDATE(),"released")', [$guardNumber]);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $foundValues = reportInput($found, 'found', $categories, $locations)['values'];
    $foundValues['storage_location'] = 'Test cabinet';
    $savedFound = saveItemReport('found', 1, $foundValues);
    $storedFound = db()->fetchOne('SELECT * FROM found_items WHERE id=?', [$savedFound['id']]);
    $check($storedFound['storage_location'] === 'Test cabinet', 'Found reports preserve their storage location.');
    $check($savedFound['report_number'] === $nextNumber, 'Found reports use their own sequence.');
    $check($savedFound['qr_generated'] && is_file($testQr), 'A real found report generates its lookup QR code.');
} finally {
    restore_error_handler();
    if (is_file($testQr)) unlink($testQr);
}
echo "PASS: {$checks} report validation, save/recovery, search, and safe sign-in redirect checks. Isolated synthetic database only.\n";
