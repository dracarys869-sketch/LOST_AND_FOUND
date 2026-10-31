<?php
require_once __DIR__ . '/decision_tree/ClaimTraining.php';

/** Persistence and ownership workflow. Every privileged write rechecks the actor in the DB. */
final class ClaimMlService
{
    private PDO $pdo;
    private ?bool $ready = null;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function schemaReady(): bool
    {
        if ($this->ready === null) {
            $row = $this->one("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE()
                AND table_name IN ('found_item_verification','decision_tree_models','claim_assessments','claim_verifications','item_photo_visibility')");
            $this->ready = (int)$row['n'] === 5;
        }
        return $this->ready;
    }

    public function submitClaim(int $actorId, array $input, ?string $proofPhoto = null): int
    {
        $actor = $this->actor($actorId, ['student', 'admin']);
        $this->requireSchema();
        $foundId = ClaimFeatures::id($input['found_item_id'] ?? null, 'found item');
        $matchId = ClaimFeatures::id($input['match_id'] ?? null, 'match', true);
        $lostId = ClaimFeatures::id($input['lost_item_id'] ?? null, 'lost item', true);
        $submitted = ClaimFeatures::submission($input);
        return $this->transaction(function () use ($actorId, $actor, $foundId, $matchId, $lostId, $submitted, $proofPhoto): int {
            // All claim/decision/release operations lock the found item first.
            $found = $this->one('SELECT * FROM found_items WHERE id=? FOR UPDATE', [$foundId]);
            if (!$found || !in_array($found['status'], ['active', 'matched'], true)
                || $this->one('SELECT id FROM releases WHERE found_item_id=? LIMIT 1', [$foundId])
                || $this->one('SELECT id FROM claims WHERE found_item_id=? AND status="approved" LIMIT 1', [$foundId])) {
                throw new DomainException('This item is unavailable for new claims.');
            }
            if ($this->one('SELECT id FROM claims WHERE claimant_id=? AND found_item_id=? AND status<>"rejected"', [$actorId, $foundId])) {
                throw new DomainException('You already have an active claim for this item.');
            }
            if ($matchId) {
                $match = $this->one('SELECT m.lost_item_id FROM matches m JOIN lost_items li ON li.id=m.lost_item_id
                    WHERE m.id=? AND m.found_item_id=? AND li.user_id=? AND m.status<>"rejected"', [$matchId, $foundId, $actorId]);
                if (!$match || ($lostId && $lostId !== (int)$match['lost_item_id'])) throw new DomainException('Invalid linked match.');
                $lostId = (int)$match['lost_item_id'];
            }
            if ($lostId && !$this->one('SELECT id FROM lost_items WHERE id=? AND user_id=?', [$lostId, $actorId])) {
                throw new DomainException('The linked lost report must belong to you.');
            }
            $submitted += $this->lookupDetails($submitted['category_id'], $submitted['location_id']);
            $reference = [
                'category_id' => (int)$found['category_id'], 'location_id' => (int)$found['location_id'],
                'description' => $found['description'] ?? '', 'date_found' => $found['date_found'],
            ] + $this->lookupDetails((int)$found['category_id'], (int)$found['location_id']);
            $private = $this->one('SELECT * FROM found_item_verification WHERE found_item_id=?', [$foundId]);
            $reference += [
                'unique_characteristics' => $private['unique_characteristics'] ?? '',
                'verification_answers' => $private ? self::decode($private['verification_answers']) : [],
                'reference_version' => $private ? (int)$private['reference_version'] : null,
                'recorded_at' => $private['recorded_at'] ?? null,
            ];
            $extracted = ClaimFeatures::extract($submitted, $reference);
            $this->run('INSERT INTO claims (lost_item_id,found_item_id,match_id,claimant_id,proof_description,proof_photo)
                VALUES (?,?,?,?,?,?)', [$lostId, $foundId, $matchId, $actorId, $submitted['description'], $proofPhoto]);
            $claimId = (int)$this->pdo->lastInsertId();
            $fingerprint = [];
            foreach (['category_id', 'location_id', 'date_lost', 'description', 'unique_characteristics'] as $key) {
                $fingerprint[$key] = ClaimFeatures::normalize((string)($submitted[$key] ?? ''));
            }
            foreach ($submitted['verification_answers'] as $key => $answer) $fingerprint[$key] = ClaimFeatures::normalize($answer);
            $this->run('INSERT INTO claim_assessments (claim_id,feature_version,submitted_json,reference_json,features_json,evidence_json,submission_fingerprint)
                VALUES (?,?,?,?,?,?,?)', [$claimId, ClaimFeatures::VERSION, self::json($submitted), self::json($reference),
                self::json($extracted['features']), self::json($extracted['evidence']), hash('sha256', self::json($fingerprint))]);
            $this->predictSaved($claimId);
            foreach ($this->all('SELECT id FROM users WHERE role IN ("admin","security") AND status="active"') as $staff) {
                $this->notify((int)$staff['id'], 'New Ownership Claim Filed',
                    $actor['name'] . ' filed a claim for "' . $found['item_name'] . '". Please review.', 'claim', $claimId, 'claim');
            }
            $this->audit($actorId, 'FILE_CLAIM', 'claims', $claimId);
            return $claimId;
        });
    }

    public function saveReference(int $actorId, int $foundId, array $input): void
    {
        $this->actor($actorId, ['admin', 'security']);
        $this->requireSchema();
        if (($input['reference_checked'] ?? '') !== '1') throw new DomainException('Confirm that you inspected the item independently.');
        $marks = ClaimFeatures::text($input['unique_characteristics'] ?? '', 'Private identifying characteristics', 2000);
        if (count(preg_split('/\R/u', $marks)) > 10) throw new InvalidArgumentException('Use at most 10 identifying phrases, one per line.');
        $public = ClaimFeatures::text($input['public_description'] ?? '', 'Public description', 500);
        $answers = $input['verification_answers'] ?? [];
        if (!is_array($answers) || array_diff(array_keys($answers), array_keys(ClaimFeatures::QUESTIONS))) throw new InvalidArgumentException('Invalid reference answers.');
        $clean = [];
        foreach (ClaimFeatures::QUESTIONS as $key => $question) $clean[$key] = ClaimFeatures::text($answers[$key] ?? '', $question, 1000);
        $this->transaction(function () use ($actorId, $foundId, $marks, $public, $clean): void {
            if (!$this->one('SELECT id FROM found_items WHERE id=? FOR UPDATE', [$foundId])) throw new DomainException('Found item not found.');
            $this->run('INSERT INTO found_item_verification
                (found_item_id,unique_characteristics,verification_answers,public_description,recorded_by)
                VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE unique_characteristics=VALUES(unique_characteristics),
                verification_answers=VALUES(verification_answers), public_description=VALUES(public_description),
                recorded_by=VALUES(recorded_by), reference_version=reference_version+1, recorded_at=NOW()',
                [$foundId, $marks, self::json($clean), $public, $actorId]);
            // No raw correct answers in audit logs, notifications, or model artifacts.
            $this->audit($actorId, 'SAVE_PRIVATE_ITEM_REFERENCE', 'found_item_verification', $foundId);
        });
    }

    public function saveVerification(int $actorId, int $claimId, array $input): void
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        $label = ClaimFeatures::text($input['training_label'] ?? '', 'Training label', 30);
        $notes = ClaimFeatures::text($input['verification_notes'] ?? '', 'Private verification evidence', 4000, $label === '' ? 0 : 20);
        if ($label !== '' && (!in_array($label, ClaimFeatures::LABELS, true) || ($input['independent_evidence'] ?? '') !== '1')) {
            throw new DomainException('Select a valid label and confirm independently checked ownership evidence.');
        }
        $status = $label === '' ? 'unverified' : ($label === 'Needs Verification' ? 'inconclusive' : 'verified');
        $this->transaction(function () use ($actorId, $claimId, $label, $notes, $status): void {
            $this->lockClaim($claimId);
            $this->ensureVerification($claimId);
            $this->run('UPDATE claim_verifications SET verification_status=?,training_label=?,verification_notes=?,
                verified_by=?,verified_at=' . ($label === '' ? 'NULL' : 'NOW()') . ' WHERE claim_id=?',
                [$status, $label ?: null, $notes ?: null, $label === '' ? null : $actorId, $claimId]);
            $this->audit($actorId, 'LABEL_CLAIM_EVIDENCE', 'claim_verifications', $claimId, ['label' => $label ?: null, 'verification_status' => $status]);
        });
    }

    public function saveSecurityReview(int $actorId, int $claimId, string $notes): void
    {
        $this->actor($actorId, ['security', 'admin']);
        $this->requireSchema();
        $notes = ClaimFeatures::text($notes, 'Private review notes', 4000, 10);
        $this->transaction(function () use ($actorId, $claimId, $notes): void {
            $this->lockClaim($claimId);
            $this->ensureVerification($claimId);
            $this->run('UPDATE claim_verifications SET security_notes=?,security_reviewed_by=?,security_reviewed_at=NOW() WHERE claim_id=?', [$notes, $actorId, $claimId]);
            $this->audit($actorId, 'REVIEW_CLAIM_EVIDENCE', 'claim_verifications', $claimId);
        });
    }

    /** Human-only final decision. The prediction is deliberately never read here. */
    public function decide(int $actorId, int $claimId, string $decision, string $notes): void
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        if (!in_array($decision, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Invalid final decision.');
        $notes = ClaimFeatures::text($notes, 'Claimant-facing review notes', 2000);
        $this->transaction(function () use ($actorId, $claimId, $decision, $notes): void {
            [$claim, $found] = $this->lockClaim($claimId);
            $verification = $this->one('SELECT final_decision FROM claim_verifications WHERE claim_id=? FOR UPDATE', [$claimId]);
            if (!empty($verification['final_decision']) || $this->one('SELECT id FROM releases WHERE claim_id=? LIMIT 1 FOR UPDATE', [$claimId])) {
                throw new DomainException('This claim has already received a final decision or has been released.');
            }
            if ($decision === 'approved') {
                if (!in_array($found['status'], ['active', 'matched', 'claimed'], true)
                    || $this->one('SELECT id FROM releases WHERE found_item_id=? LIMIT 1 FOR UPDATE', [$found['id']])
                    || $this->one('SELECT id FROM claims WHERE found_item_id=? AND id<>? AND status="approved" LIMIT 1 FOR UPDATE', [$found['id'], $claimId])) {
                    throw new DomainException('This item is unavailable or already approved for another claim.');
                }
                $this->run('UPDATE found_items SET status="claimed" WHERE id=?', [$found['id']]);
                if ($claim['match_id']) $this->run('UPDATE matches SET status="confirmed" WHERE id=?', [$claim['match_id']]);
            }
            $this->run('UPDATE claims SET status=?,reviewed_by=?,review_notes=?,updated_at=NOW() WHERE id=?', [$decision, $actorId, $notes, $claimId]);
            $this->ensureVerification($claimId);
            $this->run('UPDATE claim_verifications SET final_decision=?,decided_by=?,decided_at=NOW() WHERE claim_id=?', [$decision, $actorId, $claimId]);
            $message = $decision === 'approved'
                ? 'Your claim for "' . $found['item_name'] . '" was approved. Please visit the Security Office to collect your item.'
                : 'Your claim for "' . $found['item_name'] . '" was rejected. ' . ($notes ?: 'Please contact the administrator for details.');
            $this->notify((int)$claim['claimant_id'], $decision === 'approved' ? 'Claim Approved!' : 'Claim Rejected', $message,
                $decision === 'approved' ? 'success' : 'warning', $claimId, 'claim');
            $this->audit($actorId, $decision === 'approved' ? 'APPROVE_CLAIM' : 'REJECT_CLAIM', 'claims', $claimId);
        });
    }

    /** Physical handover remains a separate, explicit staff action after admin approval. */
    public function releaseClaim(int $actorId, int $claimId, string $notes): int
    {
        $this->actor($actorId, ['security', 'admin']);
        $this->requireSchema();
        $notes = ClaimFeatures::text($notes, 'Release notes', 2000);
        return $this->transaction(function () use ($actorId, $claimId, $notes): int {
            [$claim, $found] = $this->lockClaim($claimId);
            $verified = $this->one('SELECT final_decision,decided_by,decided_at FROM claim_verifications WHERE claim_id=? FOR UPDATE', [$claimId]);
            if ($claim['status'] !== 'approved' || ($verified['final_decision'] ?? '') !== 'approved'
                || empty($verified['decided_by']) || empty($verified['decided_at'])) {
                throw new DomainException('An administrator must approve this claim before release.');
            }
            if (!in_array($found['status'], ['active', 'matched', 'claimed'], true)
                || $this->one('SELECT id FROM releases WHERE found_item_id=? LIMIT 1 FOR UPDATE', [$found['id']])) {
                throw new DomainException('This item is unavailable or has already been released.');
            }
            $this->run('INSERT INTO releases (claim_id,found_item_id,released_by,released_to,notes) VALUES (?,?,?,?,?)',
                [$claimId, $found['id'], $actorId, $claim['claimant_id'], $notes ?: null]);
            $releaseId = (int)$this->pdo->lastInsertId();
            $this->run('UPDATE found_items SET status="released" WHERE id=?', [$found['id']]);
            if ($claim['lost_item_id']) $this->run('UPDATE lost_items SET status="claimed" WHERE id=?', [$claim['lost_item_id']]);
            if ($claim['match_id']) $this->run('UPDATE matches SET status="confirmed" WHERE id=?', [$claim['match_id']]);
            $this->notify((int)$claim['claimant_id'], 'Item Released!', 'Your item "' . $found['item_name'] . '" has been released to you. Thank you!', 'success', $releaseId, 'release');
            $this->audit($actorId, 'RELEASE_ITEM', 'releases', $releaseId);
            return $releaseId;
        });
    }

    public function reassess(int $actorId, int $claimId): void
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        $this->transaction(function () use ($actorId, $claimId): void {
            $this->lockClaim($claimId);
            if (!$this->one('SELECT claim_id FROM claim_assessments WHERE claim_id=?', [$claimId])) {
                throw new DomainException('This legacy claim has no submission-time snapshot. Review it manually.');
            }
            $this->predictSaved($claimId);
            $this->audit($actorId, 'REASSESS_CLAIM', 'claim_assessments', $claimId);
        });
    }

    public function trainingOverview(int $actorId): array
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        $data = $this->trainingData();
        $parts = ClaimTraining::split($data['records'], $this->partitionRegistry());
        return ClaimTraining::readiness($parts) + ['skipped' => $data['skipped'], 'groups' => $parts['groups']];
    }

    public function train(int $actorId): string
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        $lock = 'claim-tree-' . substr(hash('sha256', (string)$this->one('SELECT DATABASE() AS name')['name']), 0, 32);
        if ((int)$this->one('SELECT GET_LOCK(?,0) AS acquired', [$lock])['acquired'] !== 1) {
            throw new DomainException('Another Decision Tree training operation is in progress.');
        }
        try {
            return $this->transaction(function () use ($actorId): string {
                $data = $this->trainingData();
                $result = ClaimTraining::train($data['records'], $this->partitionRegistry());
                $version = 'dt-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
                $this->run('UPDATE decision_tree_models SET is_active=0 WHERE is_active=1');
                $this->run('INSERT INTO decision_tree_models
                    (version,feature_version,model_json,metrics_json,split_json,dataset_hash,train_count,test_count,is_active,trained_by)
                    VALUES (?,?,?,?,?,?,?,?,1,?)', [$version, ClaimFeatures::VERSION, $result['model_json'], self::json($result['metrics']),
                    self::json($result['split']), $result['dataset_hash'], $result['train_count'], $result['test_count'], $actorId]);
                $this->audit($actorId, 'TRAIN_DECISION_TREE', 'decision_tree_models', (int)$this->pdo->lastInsertId(), ['version' => $version]);
                return $version;
            });
        } finally {
            $this->one('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    public function deactivate(int $actorId): void
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        $this->transaction(function () use ($actorId): void {
            $this->run('UPDATE decision_tree_models SET is_active=0 WHERE is_active=1');
            $this->audit($actorId, 'DEACTIVATE_DECISION_TREE', 'decision_tree_models', null);
        });
    }

    public function setPhotoVisibility(int $actorId, int $photoId, bool $public): void
    {
        $this->actor($actorId, ['admin']);
        $this->requireSchema();
        if (!$this->one('SELECT id FROM item_photos WHERE id=?', [$photoId])) throw new DomainException('Photo not found.');
        $this->transaction(function () use ($actorId, $photoId, $public): void {
            $this->run('INSERT INTO item_photo_visibility (photo_id,is_public,reviewed_by) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE is_public=VALUES(is_public),reviewed_by=VALUES(reviewed_by),reviewed_at=NOW()', [$photoId, (int)$public, $actorId]);
            $this->audit($actorId, 'REVIEW_PHOTO_PRIVACY', 'item_photos', $photoId, ['is_public' => $public]);
        });
    }

    private function predictSaved(int $claimId): void
    {
        $snapshot = $this->one('SELECT features_json,feature_version FROM claim_assessments WHERE claim_id=?', [$claimId]);
        $classification = $version = $prediction = $predictedAt = null;
        $status = 'unavailable';
        $reason = 'No trained Decision Tree is active. Collect independently verified claims, then train the model.';
        try {
            $features = self::decode($snapshot['features_json']);
            ClaimFeatures::validate($features);
            if ($snapshot['feature_version'] !== ClaimFeatures::VERSION) throw new UnexpectedValueException('Incompatible feature version.');
            $model = $this->one('SELECT version,feature_version,model_json FROM decision_tree_models WHERE is_active=1 ORDER BY id DESC LIMIT 1');
            if (!array_filter($features, static fn($v): bool => $v >= 0)) {
                $reason = 'Insufficient comparable information. An administrator must review this claim manually.';
            } elseif ($model) {
                if ($model['feature_version'] !== ClaimFeatures::VERSION) throw new UnexpectedValueException('Incompatible model version.');
                $prediction = DecisionTree::fromJson($model['model_json'])->predict($features);
                $classification = $prediction['classification'];
                $version = $model['version'];
                $predictedAt = date('Y-m-d H:i:s');
                $status = 'predicted';
                $reason = null;
            }
        } catch (Throwable $e) {
            $status = 'error';
            $reason = 'The saved model or feature snapshot could not be evaluated. Manual administrator review is required.';
            error_log('Decision Tree evaluation failed for claim #' . $claimId . ': ' . get_class($e));
        }
        $this->run('UPDATE claim_assessments SET classification=?,prediction_status=?,prediction_reason=?,model_version=?,
            prediction_json=?,predicted_at=?,attempted_at=NOW() WHERE claim_id=?',
            [$classification, $status, $reason, $version, $prediction ? self::json($prediction) : null, $predictedAt, $claimId]);
    }

    private function trainingData(): array
    {
        $rows = $this->all('SELECT c.id,c.found_item_id,c.claimant_id,c.lost_item_id,a.features_json,a.submission_fingerprint,v.training_label
            FROM claim_assessments a JOIN claims c ON c.id=a.claim_id JOIN claim_verifications v ON v.claim_id=c.id
            JOIN users reviewer ON reviewer.id=v.verified_by AND reviewer.role="admin"
            WHERE a.feature_version=? AND v.verified_at>=a.captured_at AND CHAR_LENGTH(TRIM(v.verification_notes))>=20
            AND ((v.verification_status="verified" AND v.training_label IN ("Likely Legitimate","Suspicious"))
                OR (v.verification_status="inconclusive" AND v.training_label="Needs Verification")) ORDER BY c.id LIMIT 10001', [ClaimFeatures::VERSION]);
        $records = [];
        $skipped = 0;
        foreach ($rows as $row) {
            try {
                $features = self::decode($row['features_json']);
                ClaimFeatures::validate($features);
                if (!array_filter($features, static fn($v): bool => $v >= 0)) { $skipped++; continue; }
                $records[] = [
                    'id' => (int)$row['id'], 'found_item_id' => (int)$row['found_item_id'], 'claimant_id' => (int)$row['claimant_id'],
                    'lost_item_id' => $row['lost_item_id'] ? (int)$row['lost_item_id'] : null,
                    'fingerprint' => $row['submission_fingerprint'], 'features' => $features, 'label' => $row['training_label'],
                ];
            } catch (JsonException | InvalidArgumentException | UnexpectedValueException $e) { $skipped++; }
        }
        return ['records' => $records, 'skipped' => $skipped];
    }

    private function partitionRegistry(): array
    {
        // Use the latest saved partition even when the model has been deactivated.
        $row = $this->one('SELECT split_json FROM decision_tree_models ORDER BY id DESC LIMIT 1');
        return $row ? (self::decode($row['split_json'])['registry'] ?? []) : [];
    }

    private function lookupDetails(?int $categoryId, ?int $locationId): array
    {
        $category = $categoryId ? $this->one('SELECT name FROM categories WHERE id=?', [$categoryId]) : null;
        $location = $locationId ? $this->one('SELECT name,building FROM locations WHERE id=?', [$locationId]) : null;
        if (($categoryId && !$category) || ($locationId && !$location)) throw new DomainException('Invalid category or campus location.');
        return ['category_name' => $category['name'] ?? null, 'location_name' => $location['name'] ?? null, 'building' => $location['building'] ?? null];
    }

    private function lockClaim(int $claimId): array
    {
        $claim = $this->one('SELECT found_item_id FROM claims WHERE id=?', [$claimId]);
        if (!$claim) throw new DomainException('Claim not found.');
        $found = $this->one('SELECT * FROM found_items WHERE id=? FOR UPDATE', [$claim['found_item_id']]);
        $claim = $this->one('SELECT * FROM claims WHERE id=? FOR UPDATE', [$claimId]);
        if (!$claim || !$found) throw new DomainException('Claim or item not found.');
        return [$claim, $found];
    }

    private function ensureVerification(int $claimId): void
    {
        $this->run('INSERT INTO claim_verifications (claim_id) VALUES (?) ON DUPLICATE KEY UPDATE claim_id=VALUES(claim_id)', [$claimId]);
    }

    private function actor(int $id, array $roles): array
    {
        $actor = $this->one('SELECT id,name,role FROM users WHERE id=? AND status="active"', [$id]);
        if (!$actor || !in_array($actor['role'], $roles, true)) throw new DomainException('You are not authorized to perform this action.');
        return $actor;
    }

    private function requireSchema(): void
    {
        if (!$this->schemaReady()) throw new DomainException('Run the Decision Tree database migration before using this feature.');
    }

    private function transaction(callable $operation): mixed
    {
        $owns = !$this->pdo->inTransaction();
        if ($owns) $this->pdo->beginTransaction();
        try {
            $result = $operation();
            if ($owns) $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($owns && $this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function notify(int $userId, string $title, string $message, string $type, int $id, string $relatedType): void
    {
        $this->run('INSERT INTO notifications (user_id,title,message,type,related_id,related_type) VALUES (?,?,?,?,?,?)', [$userId, $title, $message, $type, $id, $relatedType]);
    }

    private function audit(int $actorId, string $action, string $table, ?int $id, ?array $values = null): void
    {
        $this->run('INSERT INTO audit_logs (user_id,action,table_name,record_id,new_values,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)',
            [$actorId, $action, $table, $id, $values ? self::json($values) : null, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
    }

    private function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    private function one(string $sql, array $params = []): array|false { return $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC); }
    private function all(string $sql, array $params = []): array { return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
    private static function json(array $data): string { return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
    public static function decode(string $json): array
    {
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new UnexpectedValueException('Expected a JSON object or array.');
        return $data;
    }
}

function claimMl(): ClaimMlService
{
    static $service;
    return $service ??= new ClaimMlService(db()->getConnection());
}

function claimClassificationBadge(?string $classification): string
{
    $color = match ($classification) {
        'Likely Legitimate' => 'success', 'Needs Verification' => 'warning text-dark', 'Suspicious' => 'danger', default => 'secondary',
    };
    return '<span class="badge bg-' . $color . '">' . e($classification ?? 'Not classified') . '</span>';
}

function claimEvidenceSummary(?string $json): string
{
    if (!$json) return 'No submission-time assessment (legacy claim).';
    try {
        $evidence = ClaimMlService::decode($json);
        $parts = [];
        foreach ($evidence as $row) {
            $parts[] = ($row['title'] ?? 'Feature') . ': ' . ($row['state'] ?? 'missing');
        }
        return implode(' · ', $parts);
    } catch (Throwable $e) {
        return 'Assessment unavailable; review manually.';
    }
}
