<?php
/** Optional real Apache/PHP HTTP tests, called only by integration.php. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function runHttpSmoke(PDO $pdo, ClaimMlService $service, string $testDb, callable $makeFound): void
{
    $apache = getenv('LAF_TEST_APACHE');
    $apacheRoot = dirname(dirname($apache));
    $phpRoot = dirname(PHP_BINARY);
    if (!is_file($apache) || !is_file($phpRoot . '/php8apache2_4.dll')) {
        throw new RuntimeException('HTTP suite requires XAMPP Apache and matching PHP 8 Apache module.');
    }
    $tmpRoot = getenv('LAF_TEST_TMP') ?: sys_get_temp_dir();
    if (!is_dir($tmpRoot)) throw new RuntimeException('LAF_TEST_TMP parent directory must exist.');
    $temp = $tmpRoot . '/laf_dt_http_' . bin2hex(random_bytes(6));
    mkdir($temp, 0700);
    mkdir($temp . '/sessions', 0700);
    $process = null;
    $clients = $uploaded = [];
    $quote = static fn(string $s): string => '"' . str_replace('"', '\\"', str_replace('\\', '/', $s)) . '"';
    try {
        $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$listener) throw new RuntimeException($error);
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $port = (int)substr(strrchr($address, ':'), 1);
        $base = 'http://127.0.0.1:' . $port . '/lost_and_found_system';
        $config = [
            'ServerRoot ' . $quote($apacheRoot), 'Listen 127.0.0.1:' . $port, 'ServerName 127.0.0.1',
            'PidFile ' . $quote($temp . '/httpd.pid'), 'ErrorLog ' . $quote($temp . '/apache-error.log'), 'LogLevel warn',
            'LoadModule authz_core_module modules/mod_authz_core.so',
            'LoadModule authz_host_module modules/mod_authz_host.so',
            'LoadModule access_compat_module modules/mod_access_compat.so',
            'LoadModule dir_module modules/mod_dir.so', 'LoadModule mime_module modules/mod_mime.so',
            'LoadModule alias_module modules/mod_alias.so', 'LoadModule rewrite_module modules/mod_rewrite.so',
            'LoadModule env_module modules/mod_env.so',
            'LoadFile ' . $quote($phpRoot . '/php8ts.dll'),
            'LoadModule php_module ' . $quote($phpRoot . '/php8apache2_4.dll'),
            'PHPIniDir ' . $quote($phpRoot), 'TypesConfig conf/mime.types', 'DirectoryIndex index.php',
            'DocumentRoot ' . $quote($temp), 'Alias /lost_and_found_system ' . $quote(BASE_PATH),
            '<Directory />', 'Require all denied', '</Directory>',
            '<Directory ' . $quote(BASE_PATH) . '>', 'AllowOverride All', 'Require all granted', '</Directory>',
            '<FilesMatch "\\.php$">', 'SetHandler application/x-httpd-php', '</FilesMatch>',
            'php_value session.save_path ' . $quote($temp . '/sessions'),
            'php_value upload_tmp_dir ' . $quote($temp),
            'SetEnv LAF_DB_NAME ' . $quote($testDb), 'SetEnv LAF_DB_HOST ' . $quote(DB_HOST),
            'SetEnv LAF_DB_PORT ' . $quote(DB_PORT), 'SetEnv LAF_DB_USER ' . $quote(DB_USER), 'SetEnv LAF_DB_PASS ' . $quote(DB_PASS),
        ];
        file_put_contents($temp . '/httpd.conf', implode("\n", $config) . "\n");
        $environment = getenv();
        $environment['LAF_DB_NAME'] = $testDb;
        $environment['PATH'] = $phpRoot . PATH_SEPARATOR . ($environment['PATH'] ?? $environment['Path'] ?? '');
        $process = proc_open([$apache, '-f', $temp . '/httpd.conf', '-X'],
            [0 => ['pipe', 'r'], 1 => ['file', $temp . '/stdout.log', 'a'], 2 => ['file', $temp . '/stderr.log', 'a']], $pipes, $apacheRoot, $environment);
        if (!is_resource($process)) throw new RuntimeException('Could not start isolated Apache.');
        fclose($pipes[0]);
        $started = false;
        for ($i = 0; $i < 50; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);
            if ($socket) { fclose($socket); $started = true; break; }
            if (!proc_get_status($process)['running']) break;
            usleep(100000);
        }
        if (!$started) throw new RuntimeException('Apache startup failed: ' . file_get_contents($temp . '/stderr.log'));
        $request = static function (string $client, string $path, ?array $post = null) use (&$clients, $base): array {
            if (!isset($clients[$client])) {
                $clients[$client] = curl_init();
                curl_setopt_array($clients[$client], [CURLOPT_COOKIEFILE => '', CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
            }
            $curl = $clients[$client];
            curl_setopt($curl, CURLOPT_URL, $base . $path);
            if ($post === null) { curl_setopt($curl, CURLOPT_HTTPGET, true); }
            else {
                $multipart = (bool)array_filter($post, static fn($v): bool => $v instanceof CURLFile);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $post : http_build_query($post));
            }
            $body = curl_exec($curl);
            if ($body === false) throw new RuntimeException('HTTP request failed: ' . curl_error($curl));
            tcheck(!preg_match('/(?:Fatal error|Parse error|Warning|Notice|Deprecated):/', $body), 'HTTP page emitted a PHP error: ' . $path);
            return ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => $body];
        };
        $csrf = [];
        foreach (['admin' => 1, 'student' => 2, 'security' => 4] as $role => $id) {
            $response = $request($role, '/login.php');
            preg_match('/name="_csrf_token" value="([a-f0-9]{64})"/', $response['body'], $match);
            tcheck(isset($match[1]), 'Login must issue a CSRF token.');
            $csrf[$role] = $match[1];
            $response = $request($role, '/login.php', ['_csrf_token' => $csrf[$role], 'email' => "user{$id}@test.invalid", 'password' => 'Test-only-password-42']);
            tcheck($response['status'] === 302, 'Test account login failed; check isolated DB routing.');
        }
        $foundId = $makeFound('HTTP verification bag', 'PRIVATE-FOUND-SECRET canvas bag silver zipper');
        $reference = testReference();
        $reference['unique_characteristics'] = 'REFERENCE-SECRET-992';
        $reference['verification_answers'] = ['contents' => 'ANSWER-SECRET-771', 'detail' => 'ANSWER-SECRET-883'];
        $service->saveReference(4, $foundId, $reference);
        sql($pdo, 'INSERT INTO matches (lost_item_id,found_item_id,similarity_score) VALUES (1,?,90)', [$foundId]);
        $matchId = (int)$pdo->lastInsertId();

        $anonymous = $request('anonymous', '/index.php');
        tcheck($anonymous['status'] === 200 && !str_contains($anonymous['body'], 'PRIVATE-FOUND-SECRET') && !str_contains($anonymous['body'], 'LOST-PRIVATE-SECRET'), 'Public feed must hide original identifying descriptions.');
        $newForm = $request('student', '/student/claims.php?action=new&found_id=' . $foundId);
        tcheck($newForm['status'] === 200 && !str_contains($newForm['body'], 'REFERENCE-SECRET-992') && !str_contains($newForm['body'], 'ANSWER-SECRET-771'), 'Claim form must never reveal the correct answers.');
        $matches = $request('student', '/student/matches.php?id=' . $matchId);
        tcheck($matches['status'] === 200 && !str_contains($matches['body'], 'PRIVATE-FOUND-SECRET'), 'Student matches must use only public-safe found descriptions.');
        foreach (['/admin/decision_tree.php', '/admin/claim_review.php?id=1', '/security/item_verification.php?item_id=' . $foundId, '/api/qr_lookup.php?id=' . $foundId] as $path) {
            tcheck($request('student', $path)['status'] === 403, 'Student access to private reference/model information blocked: ' . $path);
        }
        tcheck($request('security', '/admin/decision_tree.php')['status'] === 403, 'Security cannot view model evaluation/prediction controls.');
        tcheck($request('anonymous', '/scripts/train_decision_tree.php')['status'] === 403, 'CLI training must be inaccessible over HTTP.');
        tcheck($request('anonymous', '/database/migrations/001_decision_tree.sql')['status'] === 403, 'SQL migration must be inaccessible over HTTP.');
        $missingCsrf = $request('student', '/student/claims.php?action=new', testInput($foundId));
        tcheck($missingCsrf['status'] === 403, 'Missing CSRF cannot submit a claim.');
        $invalid = $request('student', '/student/claims.php?action=new', array_replace(testInput($foundId), ['_csrf_token' => $csrf['student'], 'proof_description' => ['not-text']]));
        tcheck($invalid['status'] === 200 && str_contains($invalid['body'], 'Invalid Proof of ownership'), 'Malformed input must render a recoverable validation error.');

        // Actual upload and Apache direct-URL protection, using a synthetic 1x1 image.
        $image = imagecreatetruecolor(1, 1);
        imagepng($image, $temp . '/fixture.png');
        imagedestroy($image);
        $upload = $request('security', '/api/upload_photo.php', ['_csrf_token' => $csrf['security'], 'type' => 'found', 'item_id' => $foundId,
            'file' => new CURLFile($temp . '/fixture.png', 'image/png', 'fixture.png')]);
        $data = json_decode($upload['body'], true, 32, JSON_THROW_ON_ERROR);
        tcheck($data['success'] === true, 'Existing image upload must continue to work with CSRF.');
        $uploaded[] = $data['path'];
        $photo = row($pdo, 'SELECT id FROM item_photos WHERE photo_path=?', [$data['path']]);
        $photoUrl = '/api/item_photo.php?path=' . rawurlencode($data['path']);
        tcheck($request('anonymous', '/assets/uploads/' . $data['path'])['status'] === 403, 'Apache must block original item image URLs.');
        tcheck($request('anonymous', $photoUrl)['status'] === 403 && $request('student', $photoUrl)['status'] === 403, 'Private image cannot expose correct identifying details to claimants.');
        tcheck($request('security', $photoUrl)['status'] === 200, 'Authorized staff can inspect private item images.');
        $photoPage = '/admin/photo_review.php?type=found&item_id=' . $foundId;
        tcheck($request('admin', $photoPage, ['_csrf_token' => $csrf['admin'], 'photo_id' => $photo['id'], 'is_public' => '1'])['status'] === 302, 'Admin can publish a reviewed safe photo.');
        tcheck($request('anonymous', $photoUrl)['status'] === 200, 'Explicitly reviewed public-safe images remain available.');
        $request('admin', $photoPage, ['_csrf_token' => $csrf['admin'], 'photo_id' => $photo['id']]);
        tcheck($request('anonymous', $photoUrl)['status'] === 403, 'Revoking photo visibility takes effect immediately.');

        $input = testInput($foundId);
        $input['match_id'] = $matchId;
        $input['proof_description'] = 'PRIVATE-FOUND-SECRET canvas bag silver zipper <script>alert("claim-test")</script>';
        $input['unique_characteristics'] = 'REFERENCE-SECRET-992';
        $input['verification_answers'] = $reference['verification_answers'];
        $input['_csrf_token'] = $csrf['student'];
        // Flatten nested inputs for multipart uploads.
        foreach ($input['verification_answers'] as $key => $answer) $input['verification_answers[' . $key . ']'] = $answer;
        unset($input['verification_answers']);
        $input['proof_photo'] = new CURLFile($temp . '/fixture.png', 'image/png', 'proof.png');
        tcheck($request('student', '/student/claims.php?action=new&found_id=' . $foundId, $input)['status'] === 302, 'A real form submission must save claim, evidence, prediction, and proof.');
        $claim = row($pdo, 'SELECT * FROM claims WHERE found_item_id=? AND claimant_id=2', [$foundId]);
        tcheck((bool)$claim && $claim['status'] === 'pending', 'HTTP submission remains pending.');
        if ($claim['proof_photo']) $uploaded[] = $claim['proof_photo'];
        $proofUrl = '/api/item_photo.php?path=' . rawurlencode($claim['proof_photo']);
        tcheck($request('student', $proofUrl)['status'] === 200 && $request('anonymous', $proofUrl)['status'] === 403, 'Claimant may view their own proof, public may not.');
        tcheck($request('anonymous', '/assets/uploads/' . $claim['proof_photo'])['status'] === 403, 'Apache must also protect direct claim proof URLs.');
        $claimId = (int)$claim['id'];
        $review = $request('admin', '/admin/claim_review.php?id=' . $claimId);
        tcheck($review['status'] === 200 && str_contains($review['body'], 'REFERENCE-SECRET-992') && str_contains($review['body'], 'Prediction Details'), 'Admin can review prediction and supporting private evidence.');
        tcheck(str_contains($review['body'], '&lt;script&gt;') && !str_contains($review['body'], '<script>alert("claim-test")'), 'Claim evidence is escaped against stored script injection.');
        $history = $request('student', '/student/claims.php');
        tcheck(!str_contains($history['body'], 'Likely Legitimate') && !str_contains($history['body'], 'REFERENCE-SECRET-992'), 'Student history must not disclose internal classifications or correct answers.');
        $before = row($pdo, 'SELECT * FROM claim_assessments WHERE claim_id=?', [$claimId]);
        tcheck($request('student', '/admin/claim_review.php?id=' . $claimId, ['_csrf_token' => $csrf['student'], 'action' => 'reassess'])['status'] === 403, 'Unauthorized prediction mutation rejected.');
        tcheck(row($pdo, 'SELECT * FROM claim_assessments WHERE claim_id=?', [$claimId]) === $before, 'Rejected mutation must preserve stored classification.');
        tcheck($request('security', '/security/claims.php', ['_csrf_token' => $csrf['security'], 'action' => 'approve', 'claim_id' => $claimId])['status'] === 403, 'Old security approval route cannot bypass administrator control.');
        tcheck($request('admin', '/admin/claim_review.php?id=' . $claimId, ['action' => 'decide', 'decision' => 'approved'])['status'] === 403, 'Admin decision requires CSRF.');

        $pages = [
            'admin' => ['/admin/dashboard.php','/admin/claims.php','/admin/decision_tree.php','/admin/reports.php?type=found', '/admin/reports.php?type=lost', '/admin/reports.php?type=match', '/reports/export_csv.php?type=found', $photoPage],
            'student' => ['/student/dashboard.php','/student/my_reports.php','/student/lost_items.php','/student/found_items.php','/student/notifications.php'],
            'security' => ['/security/dashboard.php','/security/found_items.php','/security/claims.php','/security/release.php','/security/storage.php','/security/qr_scanner.php','/api/qr_lookup.php?id=' . $foundId,'/security/item_verification.php?item_id=' . $foundId],
        ];
        foreach ($pages as $role => $paths) foreach ($paths as $path) tcheck($request($role, $path)['status'] === 200, 'Existing/new page must load: ' . $path);
        tcheck($request('admin', '/admin/claim_review.php?id=' . $claimId, ['_csrf_token' => $csrf['admin'], 'action' => 'decide', 'decision' => 'approved', 'review_notes' => 'Original proof checked.'])['status'] === 302, 'Administrator can make the final decision from review page.');
        tcheck(!row($pdo, 'SELECT id FROM releases WHERE claim_id=?', [$claimId]), 'HTTP approval must not auto-release.');
        tcheck($request('security', '/security/release.php', ['_csrf_token' => $csrf['security'], 'claim_id' => $claimId, 'notes' => 'Physical handover verified.'])['status'] === 302, 'Security can release an administrator-approved claim.');
        tcheck((bool)row($pdo, 'SELECT id FROM releases WHERE claim_id=?', [$claimId]), 'HTTP release persisted.');

        // A stale admin session cannot retain access after a role change in the database.
        sql($pdo, 'UPDATE users SET role="student" WHERE id=1');
        tcheck($request('admin', '/admin/decision_tree.php')['status'] === 403, 'Current DB role must override stale session role.');
        sql($pdo, 'UPDATE users SET role="admin" WHERE id=1');
        echo "PASS: Real Apache HTTP workflow, privacy, CSRF, role access, uploads, and existing-page smoke checks.\n";
    } finally {
        foreach ($clients as $client) curl_close($client);
        if (is_resource($process)) {
            if (proc_get_status($process)['running']) proc_terminate($process);
            proc_close($process);
        }
        foreach ($uploaded as $path) {
            if (preg_match('~^(found_items|claim_proofs)/img_[a-zA-Z0-9_.-]+\.png$~D', $path)) @unlink(UPLOADS_PATH . '/' . $path);
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) { $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname()); }
        @rmdir($temp);
    }
}
