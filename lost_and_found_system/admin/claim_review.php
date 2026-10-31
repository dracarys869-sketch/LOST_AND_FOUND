<?php
$pageTitle = 'Review Claim';
$activePage = 'claims';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/claim_ml.php';
requireRole('admin');
header('Cache-Control: private, no-store');

if (!claimMl()->schemaReady()) {
    setFlash('warning', 'Run the Decision Tree database migration first.');
    redirect(BASE_URL . '/admin/decision_tree.php');
}
$claimId = (int)filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        switch ($_POST['action'] ?? '') {
            case 'decide':
                claimMl()->decide((int)$user['id'], $claimId,
                    ClaimFeatures::text($_POST['decision'] ?? '', 'Decision', 20),
                    ClaimFeatures::text($_POST['review_notes'] ?? '', 'Review notes', 2000));
                setFlash('success', 'Final ownership decision saved. The claimant has been notified.');
                break;
            case 'label':
                claimMl()->saveVerification((int)$user['id'], $claimId, $_POST);
                setFlash('success', 'Independent verification record saved. The ownership decision is recorded separately.');
                break;
            case 'reassess':
                claimMl()->reassess((int)$user['id'], $claimId);
                setFlash('info', 'Assessment refreshed using the original submission-time features.');
                break;
            default:
                throw new InvalidArgumentException('Invalid review action.');
        }
        redirect(BASE_URL . '/admin/claim_review.php?id=' . $claimId);
    } catch (InvalidArgumentException | DomainException $e) {
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        error_log('Claim review failed: ' . get_class($e));
        $errors[] = 'The review could not be saved. Please try again.';
    }
}
$claim = db()->fetchOne('SELECT c.*,fi.item_name,fi.report_number,fi.description AS found_description,
    u.name AS claimant_name,u.email AS claimant_email,rv.name AS reviewer_name
    FROM claims c JOIN found_items fi ON fi.id=c.found_item_id JOIN users u ON u.id=c.claimant_id
    LEFT JOIN users rv ON rv.id=c.reviewed_by WHERE c.id=?', [$claimId]);
if (!$claim) { http_response_code(404); exit('Claim not found.'); }
$assessment = db()->fetchOne('SELECT * FROM claim_assessments WHERE claim_id=?', [$claimId]);
$verification = db()->fetchOne('SELECT v.*,u.name AS verifier_name,s.name AS security_name,d.name AS decider_name
    FROM claim_verifications v LEFT JOIN users u ON u.id=v.verified_by LEFT JOIN users s ON s.id=v.security_reviewed_by
    LEFT JOIN users d ON d.id=v.decided_by WHERE v.claim_id=?', [$claimId]) ?: [];
$released = db()->fetchOne('SELECT release_date FROM releases WHERE claim_id=? LIMIT 1', [$claimId]);
$submitted = $reference = $evidence = $prediction = [];
if ($assessment) {
    try {
        $submitted = ClaimMlService::decode($assessment['submitted_json']);
        $reference = ClaimMlService::decode($assessment['reference_json']);
        $evidence = ClaimMlService::decode($assessment['evidence_json']);
        $prediction = $assessment['prediction_json'] ? ClaimMlService::decode($assessment['prediction_json']) : [];
    } catch (Throwable $e) { $errors[] = 'The stored assessment is unreadable. Review the original claim manually.'; }
}
$posted = static fn(string $key, string $default = ''): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : $default;
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<div class="main-content">
  <div class="topbar">
    <button class="btn-icon me-2" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
    <div class="topbar-title"><span class="text-muted-sm">Admin / Claims /</span> #<?= $claimId ?></div>
  </div>
  <div class="content-area">
    <div class="page-header">
      <div><h1 class="page-title">Claim #<?= $claimId ?> — <?= e($claim['item_name']) ?></h1>
        <p class="page-subtitle"><?= e($claim['claimant_name']) ?> · <?= e($claim['claimant_email']) ?> · Submitted <?= formatDate($claim['created_at'], 'M j, Y h:i A') ?></p>
      </div>
      <a href="<?= BASE_URL ?>/admin/claims.php" class="btn btn-outline-secondary btn-sm">All Claims</a>
    </div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <div class="alert alert-info">
      <strong>Recommendation only.</strong> A Decision Tree result is not proof of ownership. Independently inspect the evidence before approving or rejecting this claim.
    </div>
    <div class="row g-3">
      <div class="col-lg-8">
        <div class="card mb-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-diagram-3"></i> Decision Tree Assessment</span><?= claimClassificationBadge($assessment['classification'] ?? null) ?></div>
          <div class="card-body">
            <?php if (!$assessment): ?>
              <p class="mb-0">Legacy claim: no submission-time feature snapshot exists. It is excluded from training and requires manual review.</p>
            <?php elseif ($assessment['prediction_status'] !== 'predicted'): ?>
              <p><?= e($assessment['prediction_reason']) ?></p>
            <?php endif; ?>
            <?php if ($evidence): ?>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th>Feature</th><th>Comparison</th><th>Supporting information</th></tr></thead>
                <tbody><?php foreach ($evidence as $row): ?>
                  <tr>
                    <td><?= e($row['title']) ?></td>
                    <td><?= e(ucfirst($row['state'])) ?><?= $row['score'] >= 0 ? ' (' . number_format($row['score'] * 100, 1) . '%)' : '' ?></td>
                    <td><?= e($row['detail']) ?></td>
                  </tr>
                <?php endforeach; ?></tbody>
              </table>
            </div>
            <small class="text-muted">Percentages describe feature agreement, not the probability of ownership. Missing values are encoded as -1, never as a match.</small>
            <?php endif; ?>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-shield-lock"></i> Private Ownership Evidence</span></div>
          <div class="card-body">
            <h6>Claimant's proof</h6><p style="white-space:pre-wrap;"><?= e($claim['proof_description'] ?? 'No proof supplied.') ?></p>
            <?php if ($claim['proof_photo']): ?>
              <a href="<?= e(photoUrl($claim['proof_photo'])) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-3">View Private Supporting Photo</a>
            <?php endif; ?>
            <?php if ($assessment): ?>
            <p class="text-muted">Frozen at submission: <?= e($assessment['captured_at']) ?>. Item-reference revision: <?= e($reference['reference_version'] ?? 'not recorded') ?>. Later item edits do not change these features.</p>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th>Information</th><th>Claimant supplied</th><th>Recorded found item (private)</th></tr></thead>
                <tbody>
                <?php foreach ([
                    ['Category', $submitted['category_name'] ?? null, $reference['category_name'] ?? null],
                    ['Location', $submitted['location_name'] ?? null, $reference['location_name'] ?? null],
                    ['Lost / found date', $submitted['date_lost'] ?? null, $reference['date_found'] ?? null],
                    ['Description', $submitted['description'] ?? null, $reference['description'] ?? null],
                    ['Unique characteristics', $submitted['unique_characteristics'] ?? null, $reference['unique_characteristics'] ?? null],
                ] as [$title, $answer, $expected]): ?>
                  <tr><th><?= e($title) ?></th><td style="white-space:pre-wrap;"><?= e($answer ?: 'Not supplied') ?></td><td style="white-space:pre-wrap;"><?= e($expected ?: 'Not recorded') ?></td></tr>
                <?php endforeach; ?>
                <?php foreach (ClaimFeatures::QUESTIONS as $key => $question): ?>
                  <tr><th><?= e($question) ?></th><td style="white-space:pre-wrap;"><?= e($submitted['verification_answers'][$key] ?? '') ?></td><td style="white-space:pre-wrap;"><?= e($reference['verification_answers'][$key] ?? '') ?></td></tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
              <h6>Current found-item description (not a historical feature snapshot)</h6>
              <p style="white-space:pre-wrap;"><?= e($claim['found_description']) ?></p>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/security/item_verification.php?item_id=<?= $claim['found_item_id'] ?>" class="btn btn-sm btn-outline-secondary">Inspect / Update Private Item Reference</a>
            <?php if (!empty($verification['security_notes'])): ?>
              <hr><h6>Security staff's private review</h6><p style="white-space:pre-wrap;"><?= e($verification['security_notes']) ?></p>
              <small class="text-muted"><?= e($verification['security_name']) ?> · <?= e($verification['security_reviewed_at']) ?></small>
            <?php endif; ?>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-person-check"></i> Final Administrator Decision</span><?= statusBadge($claim['status']) ?></div>
          <div class="card-body">
            <?php if ($released || !empty($verification['final_decision'])): ?>
              <p>Decision: <strong><?= e(ucfirst($verification['final_decision'] ?? $claim['status'])) ?></strong> by <?= e($verification['decider_name'] ?? $claim['reviewer_name'] ?? 'Historical reviewer') ?>.</p>
              <p><?= e($claim['review_notes']) ?></p>
              <?php if ($released): ?><p>Released: <?= e($released['release_date']) ?></p><?php endif; ?>
            <?php else: ?>
              <?php if ($claim['status'] !== 'pending'): ?><p class="text-muted">This historical decision has no administrator authorization record. An administrator must confirm it before any new release.</p><?php endif; ?>
              <form method="POST">
                <?= csrfField() ?><input type="hidden" name="action" value="decide">
                <label class="form-label" for="publicNotes">Notes shown to the claimant</label>
                <textarea name="review_notes" id="publicNotes" class="form-control mb-2" rows="3" maxlength="2000"><?= e($posted('review_notes')) ?></textarea>
                <p class="form-text">Keep correct verification answers and internal assessment details in the private evidence record.</p>
                <div class="d-flex gap-2">
                  <button name="decision" value="approved" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Approve Claim</button>
                  <button name="decision" value="rejected" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Reject Claim</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="card mb-3">
          <div class="card-header"><span class="card-title"><i class="bi bi-journal-check"></i> Independent Verification / Training Label</span></div>
          <div class="card-body">
            <p class="text-muted">Label only evidence checked independently of the model. Approval or rejection alone does not create a training label.</p>
            <ul class="small">
              <li><strong>Likely Legitimate:</strong> independent ownership evidence strongly matches.</li>
              <li><strong>Needs Verification:</strong> a documented verification attempt remains inconclusive.</li>
              <li><strong>Suspicious:</strong> independently confirmed, significant ownership inconsistencies.</li>
            </ul>
            <form method="POST">
              <?= csrfField() ?><input type="hidden" name="action" value="label">
              <label class="form-label" for="trainingLabel">Evidence label</label>
              <select name="training_label" id="trainingLabel" class="form-select mb-3">
                <option value="">Unverified / exclude from training</option>
                <?php foreach (ClaimFeatures::LABELS as $label): ?>
                <option <?= $posted('training_label', $verification['training_label'] ?? '') === $label ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <label class="form-label" for="verificationNotes">Private evidence and verification method</label>
              <textarea name="verification_notes" id="verificationNotes" class="form-control mb-3" rows="5" maxlength="4000" placeholder="Record what was independently checked and what matched or conflicted. At least 20 characters for a training label."><?= e($posted('verification_notes', $verification['verification_notes'] ?? '')) ?></textarea>
              <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="independent_evidence" id="independentEvidence" value="1">
                <label class="form-check-label small" for="independentEvidence">This label is based on independently checked ownership evidence, not the model recommendation.</label>
              </div>
              <button class="btn btn-primary btn-sm">Save Verification Record</button>
            </form>
            <p class="small text-muted mt-3 mb-0">Verification status: <?= e($verification['verification_status'] ?? 'unverified') ?><?= !empty($verification['verified_at']) ? ' · ' . e($verification['verified_at']) : '' ?></p>
            <?php if (!$assessment): ?><p class="small text-muted mt-2">This legacy claim remains excluded from model training because it has no submission-time snapshot.</p><?php endif; ?>
          </div>
        </div>

        <?php if ($assessment): ?>
        <div class="card">
          <div class="card-header"><span class="card-title"><i class="bi bi-info-circle"></i> Prediction Details</span></div>
          <div class="card-body">
            <p class="small">Model: <code><?= e($assessment['model_version'] ?? 'none') ?></code><br>
              Prediction date: <?= e($assessment['predicted_at'] ?? 'not predicted') ?><br>Last attempt: <?= e($assessment['attempted_at']) ?></p>
            <?php if ($prediction): ?>
              <p class="small">Leaf support: <?= (int)$prediction['leaf_samples'] ?> training claims. These counts are not calibrated ownership probabilities.</p>
              <ul class="small"><?php foreach ($prediction['leaf_counts'] as $label => $count): ?><li><?= e($label) ?>: <?= (int)$count ?></li><?php endforeach; ?></ul>
              <details><summary>Learned decision path</summary><ol class="small mt-2">
                <?php foreach ($prediction['path'] as $node): ?><li><?= e(ClaimFeatures::TITLES[$node['feature']]) ?>: <?= e($node['value']) ?> <?= e($node['branch']) ?> <?= e($node['threshold']) ?></li><?php endforeach; ?>
                <?php if (!$prediction['path']): ?><li>Root leaf: no useful split was learned.</li><?php endif; ?>
              </ol></details>
            <?php endif; ?>
            <form method="POST" class="mt-3"><?= csrfField() ?><input type="hidden" name="action" value="reassess"><button class="btn btn-sm btn-outline-primary">Reassess with Active Tree</button></form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
