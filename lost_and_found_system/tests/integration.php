<?php
/** Creates and drops a NEW isolated database. All records below are synthetic test fixtures. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/claim_ml.php';
$checks = 0;
function tcheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function treject(callable $operation, string $message): void
{
    try { $operation(); } catch (DomainException | InvalidArgumentException $e) { tcheck(true, $message); return; }
    tcheck(false, $message);
}
function sql(PDO $pdo, string $query, array $args = []): PDOStatement
{
    $stmt = $pdo->prepare($query); $stmt->execute($args); return $stmt;
}
function row(PDO $pdo, string $query, array $args = []): array|false { return sql($pdo, $query, $args)->fetch(PDO::FETCH_ASSOC); }
function testInput(int $foundId): array
{
    return ['found_item_id' => $foundId, 'claimed_category_id' => '1', 'claimed_location_id' => '1',
        'claimed_date_lost' => '2024-04-01', 'proof_description' => 'Blue canvas bag with silver zipper',
        'unique_characteristics' => 'ZX91 triangle sticker',
        'verification_answers' => ['contents' => 'red notebook', 'detail' => 'striped inner pocket']];
}
function testReference(): array
{
    return ['reference_checked' => '1', 'unique_characteristics' => "ZX91\ntriangle sticker",
        'verification_answers' => ['contents' => 'red notebook', 'detail' => 'striped inner pocket'], 'public_description' => 'A blue canvas bag.'];
}

$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
$server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
$testDb = 'laf_dt_test_' . bin2hex(random_bytes(6));
$created = false;
$failure = null;
try {
    // CREATE without IF NOT EXISTS: never reuse or delete anyone's existing database.
    $server->exec("CREATE DATABASE `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . $testDb . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    $schema = file_get_contents(__DIR__ . '/../database/lost_and_found_db.sql');
    $schema = strstr($schema, '--  SEED DATA', true);
    if ($schema === false) throw new RuntimeException('Could not locate base schema boundary.');
    $schema = preg_replace('/CREATE DATABASE IF NOT EXISTS `lost_and_found_db`.*?;/s', '', $schema);
    $schema = str_replace('USE `lost_and_found_db`;', '', $schema);
    if (preg_match('/\b(CREATE\s+DATABASE|USE\s+`)/i', $schema)) throw new RuntimeException('Unsafe database switch in test schema.');
    $pdo->exec($schema);
    $password = password_hash('Test-only-password-42', PASSWORD_BCRYPT);
    foreach ([[1,'Administrator','admin'],[2,'Claimant A','student'],[3,'Claimant B','student'],[4,'Custody Officer','security']] as [$id,$name,$role]) {
        sql($pdo, 'INSERT INTO users (id,name,email,password,role) VALUES (?,?,?,?,?)', [$id,$name,"user{$id}@test.invalid",$password,$role]);
    }
    $pdo->exec('INSERT INTO categories (id,name) VALUES (1,"Bags"),(2,"Clothing")');
    $pdo->exec('INSERT INTO locations (id,name,building) VALUES (1,"Library","Main"),(2,"Classroom","Main"),(3,"Gym","Annex")');
    $pdo->exec('INSERT INTO found_items (id,report_number,user_id,category_id,location_id,item_name,description,date_found,status)
        VALUES (1,"FI-TEST-LEGACY",4,1,1,"Legacy bag","Historical private description","2024-04-02","released")');
    $pdo->exec('INSERT INTO lost_items (id,report_number,user_id,category_id,location_id,item_name,description,date_lost,status)
        VALUES (1,"LI-TEST-LEGACY",2,1,1,"Legacy bag","LOST-PRIVATE-SECRET","2024-04-01","active"),
               (2,"LI-TEST-OTHER",3,1,1,"Other bag","Private report","2024-04-01","active")');
    $pdo->exec('INSERT INTO claims (id,found_item_id,lost_item_id,claimant_id,status,reviewed_by,proof_description)
        VALUES (1,1,1,2,"approved",4,"Legacy approval is not a verified ML label")');
    $pdo->exec('INSERT INTO releases (claim_id,found_item_id,released_by,released_to) VALUES (1,1,4,2)');
    $before = row($pdo, 'SELECT * FROM claims WHERE id=1');
    $migration = file_get_contents(__DIR__ . '/../database/migrations/001_decision_tree.sql');
    $pdo->exec($migration);
    $pdo->exec($migration);
    tcheck(row($pdo, 'SELECT * FROM claims WHERE id=1') === $before, 'Additive, repeatable migration must preserve legacy records.');
    $service = new ClaimMlService($pdo);
    tcheck($service->schemaReady(), 'All new tables must be available.');
    tcheck($service->trainingOverview(1)['eligible'] === 0, 'Historical approvals must not be invented training labels.');
    treject(fn() => $service->train(1), 'Training without reliable records must fail.');
    tcheck((int)row($pdo, 'SELECT COUNT(*) AS n FROM decision_tree_models')['n'] === 0, 'Failed training must not create a dummy model.');

    $makeFound = static function (string $name = 'Blue canvas bag', string $description = 'Blue canvas bag with silver zipper', string $status = 'active') use ($pdo): int {
        sql($pdo, 'INSERT INTO found_items (report_number,user_id,category_id,location_id,item_name,description,date_found,status)
            VALUES (?,4,1,1,?,?,"2024-04-02",?)', ['FI-T-' . bin2hex(random_bytes(5)), $name, $description, $status]);
        return (int)$pdo->lastInsertId();
    };
    $foundId = $makeFound();
    $service->saveReference(4, $foundId, testReference());
    treject(fn() => $service->saveReference(2, $foundId, testReference()), 'Claimants cannot set correct answers.');
    $input = testInput($foundId) + ['classification' => 'Likely Legitimate', 'status' => 'approved', 'features_json' => '{}'];
    $claimId = $service->submitClaim(2, $input);
    $assessment = row($pdo, 'SELECT * FROM claim_assessments WHERE claim_id=?', [$claimId]);
    tcheck($assessment['classification'] === null && $assessment['prediction_status'] === 'unavailable' && $assessment['model_version'] === null,
        'Untrained claims must be saved without an invented classification/version.');
    tcheck(row($pdo, 'SELECT status FROM claims WHERE id=?', [$claimId])['status'] === 'pending', 'Submitting a claim cannot approve it.');
    tcheck(count(ClaimMlService::decode($assessment['features_json'])) === 6, 'Persist all six validated features.');
    $beforeCount = (int)row($pdo, 'SELECT COUNT(*) AS n FROM claims')['n'];
    treject(fn() => $service->submitClaim(2, $input), 'Duplicate active claims rejected.');
    treject(fn() => $service->submitClaim(3, array_replace($input, ['proof_description' => ''])), 'Missing proof rejected.');
    treject(fn() => $service->submitClaim(3, array_replace($input, ['found_item_id' => 999999])), 'Nonexistent items rejected.');
    treject(fn() => $service->submitClaim(3, array_replace($input, ['claimed_category_id' => 999999])), 'Forged categories rejected.');
    treject(fn() => $service->submitClaim(3, array_replace($input, ['lost_item_id' => 1])), 'Other users’ lost reports cannot be linked.');
    sql($pdo, 'INSERT INTO matches (lost_item_id,found_item_id,similarity_score) VALUES (1,?,90)', [$foundId]);
    $matchId = (int)$pdo->lastInsertId();
    treject(fn() => $service->submitClaim(3, array_replace($input, ['match_id' => $matchId])), 'Other users’ matches cannot be linked.');
    $unavailableId = $makeFound('Pending bag', 'A bag in custody', 'pending');
    treject(fn() => $service->submitClaim(3, testInput($unavailableId)), 'Unavailable items rejected.');
    tcheck((int)row($pdo, 'SELECT COUNT(*) AS n FROM claims')['n'] === $beforeCount, 'Invalid submissions leave no partial claims.');
    treject(fn() => $service->decide(4, $claimId, 'approved', ''), 'Security cannot approve claims.');
    treject(fn() => $service->decide(2, $claimId, 'rejected', ''), 'Claimants cannot change ownership decisions.');
    treject(fn() => $service->releaseClaim(4, $claimId, ''), 'Pending claims cannot be released.');
    treject(fn() => $service->reassess(2, $claimId), 'Claimants cannot modify predictions.');
    treject(fn() => $service->reassess(4, $claimId), 'Prediction controls restricted to administrators.');
    treject(fn() => $service->train(4), 'Security cannot train or replace models.');
    $label = ['training_label' => 'Likely Legitimate', 'verification_notes' => 'Synthetic test: ownership was checked using original evidence.', 'independent_evidence' => '1'];
    treject(fn() => $service->saveVerification(4, $claimId, $label), 'Only admins can create reliable labels.');
    treject(fn() => $service->saveVerification(1, $claimId, array_replace($label, ['independent_evidence' => ''])), 'A model result alone cannot become a label.');
    $service->saveVerification(1, $claimId, array_replace($label, ['training_label' => 'Needs Verification']));
    tcheck(row($pdo, 'SELECT verification_status FROM claim_verifications WHERE claim_id=?', [$claimId])['verification_status'] === 'inconclusive', 'Needs Verification records a documented inconclusive review.');
    $service->saveVerification(1, $claimId, $label);
    $service->saveSecurityReview(4, $claimId, 'Synthetic staff evidence: document inspected.');
    tcheck(row($pdo, 'SELECT training_label FROM claim_verifications WHERE claim_id=?', [$claimId])['training_label'] === 'Likely Legitimate', 'Security notes cannot overwrite training labels.');
    $service->saveReference(4, $foundId, array_replace(testReference(), ['unique_characteristics' => 'CHANGED PRIVATE MARK']));
    sql($pdo, 'UPDATE found_items SET description="Changed after claim" WHERE id=?', [$foundId]);
    $service->reassess(1, $claimId);
    $frozen = row($pdo, 'SELECT features_json,reference_json FROM claim_assessments WHERE claim_id=?', [$claimId]);
    tcheck($frozen['features_json'] === $assessment['features_json'] && $frozen['reference_json'] === $assessment['reference_json'], 'Reassessment must not leak post-submission item edits into features.');
    treject(fn() => $service->reassess(1, 1), 'Legacy features must not be fabricated after the outcome.');

    $otherClaim = $service->submitClaim(3, testInput($foundId));
    $stale = new PDO($dsn, DB_USER, DB_PASS, $options);
    $stale->beginTransaction();
    row($stale, 'SELECT * FROM claims WHERE id=?', [$otherClaim]); // establish old repeatable-read snapshot
    $service->decide(1, $claimId, 'approved', 'Please collect your item.');
    treject(fn() => (new ClaimMlService($stale))->decide(1, $otherClaim, 'approved', ''), 'Even stale concurrent transactions must not approve a second claimant.');
    $stale->rollBack();
    tcheck(row($pdo, 'SELECT final_decision FROM claim_verifications WHERE claim_id=?', [$claimId])['final_decision'] === 'approved', 'Persist administrator authorization separately from labels.');
    tcheck((int)row($pdo, 'SELECT COUNT(*) AS n FROM releases')['n'] === 1, 'Manual approval alone must not release the item.');
    $service->releaseClaim(4, $claimId, 'Synthetic physical handover');
    treject(fn() => $service->releaseClaim(4, $claimId, ''), 'Double release blocked.');
    treject(fn() => $service->decide(1, $claimId, 'rejected', ''), 'Released decisions cannot be rewritten.');
    tcheck((int)row($pdo, 'SELECT COUNT(*) AS n FROM releases')['n'] === 2, 'Only one new release recorded.');
    $legacyId = $makeFound('Legacy approved, unreleased bag');
    sql($pdo, 'INSERT INTO claims (found_item_id,claimant_id,status,reviewed_by) VALUES (?,3,"approved",4)', [$legacyId]);
    $legacyClaim = (int)$pdo->lastInsertId();
    treject(fn() => $service->releaseClaim(4, $legacyClaim, ''), 'Old security approval cannot bypass new administrator authorization.');
    $service->decide(1, $legacyClaim, 'approved', 'Administrator rechecked this historical claim.');
    $service->releaseClaim(4, $legacyClaim, 'Manual handover after admin confirmation');
    tcheck(row($pdo, 'SELECT status FROM found_items WHERE id=?', [$legacyId])['status'] === 'released', 'Historical records remain usable after administrator confirmation.');

    // Artificial data below exercises the training plumbing ONLY inside the temporary database.
    for ($i = 0; $i < 180; $i++) {
        sql($pdo, 'INSERT INTO users (name,email,password,role) VALUES (?, ?, ?, "student")', ["Synthetic claimant {$i}", "fixture{$i}@test.invalid", $password]);
        $studentId = (int)$pdo->lastInsertId();
        $fi = $makeFound('Synthetic training bag ' . $i, 'Blue canvas bag with silver zipper Case-' . $i);
        $service->saveReference(4, $fi, testReference());
        $claimInput = testInput($fi);
        $class = ClaimFeatures::LABELS[$i % 3];
        if ($class === 'Needs Verification') {
            $claimInput = array_replace($claimInput, ['claimed_location_id' => 2, 'proof_description' => 'Blue bag with long straps', 'unique_characteristics' => 'triangle sticker',
                'verification_answers' => ['contents' => 'notebook', 'detail' => '']]);
        } elseif ($class === 'Suspicious') {
            $claimInput = array_replace($claimInput, ['claimed_category_id' => 2, 'claimed_location_id' => 3, 'claimed_date_lost' => '2024-04-05',
                'proof_description' => 'Black wool coat with wooden buttons', 'unique_characteristics' => 'round hole',
                'verification_answers' => ['contents' => 'keys', 'detail' => 'leather collar']]);
        }
        $claimInput['proof_description'] .= ' Case-' . $i;
        $id = $service->submitClaim($studentId, $claimInput);
        $service->saveVerification(1, $id, array_replace($label, ['training_label' => $class]));
    }
    tcheck($service->trainingOverview(1)['ready'], 'Diverse independently labeled test fixtures enable training.');
    $version = $service->train(1);
    $modelRow = row($pdo, 'SELECT * FROM decision_tree_models WHERE version=?', [$version]);
    $metrics = ClaimMlService::decode($modelRow['metrics_json']);
    $split = ClaimMlService::decode($modelRow['split_json']);
    tcheck($modelRow['is_active'] == 1 && $modelRow['train_count'] >= 20 && $modelRow['test_count'] >= 6, 'Trained model and train/test counts saved.');
    tcheck(isset($metrics['accuracy'], $metrics['per_class'], $metrics['macro']['precision'], $metrics['macro']['recall'], $metrics['macro']['f1'], $metrics['confusion_matrix']), 'Persist every required evaluation metric.');
    tcheck(!array_intersect($split['train_claim_ids'], $split['test_claim_ids']), 'Saved training/test memberships are disjoint.');
    tcheck(!in_array(1, array_merge($split['train_claim_ids'], $split['test_claim_ids']), true), 'Seed/historical approvals excluded from training and testing.');
    tcheck(!str_contains($modelRow['model_json'], 'test.invalid') && !str_contains($modelRow['model_json'], 'ZX91'), 'Saved tree contains no personal identities or correct answers.');
    $probeFound = $makeFound('Prediction probe bag');
    $service->saveReference(4, $probeFound, testReference());
    $probe = $service->submitClaim(2, testInput($probeFound));
    $prediction = row($pdo, 'SELECT * FROM claim_assessments WHERE claim_id=?', [$probe]);
    tcheck($prediction['classification'] === 'Likely Legitimate' && $prediction['model_version'] === $version && $prediction['predicted_at'] !== null, 'Prediction, version, and date persist correctly.');
    tcheck(row($pdo, 'SELECT status FROM claims WHERE id=?', [$probe])['status'] === 'pending', 'High recommendation must never auto-approve.');
    sql($pdo, 'UPDATE decision_tree_models SET model_json="{}" WHERE version=?', [$version]);
    $service->reassess(1, $probe);
    $broken = row($pdo, 'SELECT * FROM claim_assessments WHERE claim_id=?', [$probe]);
    tcheck($broken['prediction_status'] === 'error' && $broken['classification'] === null, 'Corrupt model degrades to saved manual-review state.');
    sql($pdo, 'UPDATE decision_tree_models SET model_json=? WHERE version=?', [$modelRow['model_json'], $version]);
    $service->reassess(1, $probe);
    $secondVersion = $service->train(1);
    tcheck($secondVersion !== $version && (int)row($pdo, 'SELECT COUNT(*) AS n FROM decision_tree_models WHERE is_active=1')['n'] === 1, 'Retraining saves a new version and exactly one active tree.');
    tcheck(row($pdo, 'SELECT model_version FROM claim_assessments WHERE claim_id=?', [$probe])['model_version'] === $version, 'Retraining does not silently rewrite historical predictions.');
    $service->deactivate(1);
    $unknown = $service->submitClaim(3, testInput($probeFound));
    tcheck(row($pdo, 'SELECT classification FROM claim_assessments WHERE claim_id=?', [$unknown])['classification'] === null, 'Deactivated tree does not produce invented classifications.');
    $service->train(1);

    if (getenv('LAF_TEST_APACHE')) {
        require __DIR__ . '/http_smoke.php';
        runHttpSmoke($pdo, $service, $testDb, $makeFound);
    } else {
        echo "HTTP checks skipped. Set LAF_TEST_APACHE to an Apache executable to run the XAMPP HTTP suite.\n";
    }
    echo "PASS: {$checks} database/workflow/authorization checks; synthetic fixtures in an isolated database only.\n";
} catch (Throwable $e) {
    $failure = $e;
} finally {
    if ($created) {
        $server->exec("DROP DATABASE `{$testDb}`");
        echo "Temporary test database removed. Application database was not used for test writes.\n";
    }
}
if ($failure) { fwrite(STDERR, 'FAIL: ' . $failure->getMessage() . "\n" . $failure->getTraceAsString() . "\n"); exit(1); }
