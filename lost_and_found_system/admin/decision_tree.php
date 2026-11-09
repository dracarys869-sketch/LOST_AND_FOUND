<?php
$pageTitle = 'Decision Tree';
$activePage = 'decision_tree';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/claim_ml.php';
requireRole('admin');
header('Cache-Control: private, no-store');
$ready = claimMl()->schemaReady();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    try {
        if (($_POST['action'] ?? '') === 'train') {
            $version = claimMl()->train((int)$user['id']);
            setFlash('success', 'Decision Tree ' . $version . ' trained, evaluated, saved, and activated. Review its held-out metrics below.');
        } elseif (($_POST['action'] ?? '') === 'deactivate') {
            claimMl()->deactivate((int)$user['id']);
            setFlash('info', 'Model deactivated. New claims will require manual review until a model is trained again.');
        } else {
            throw new InvalidArgumentException('Invalid model action.');
        }
        redirect(BASE_URL . '/admin/decision_tree.php');
    } catch (InvalidArgumentException | DomainException $e) { $errors[] = $e->getMessage(); }
    catch (Throwable $e) {
        error_log('Decision Tree training failed: ' . get_class($e));
        $errors[] = 'Training could not complete. The previously saved model was preserved. Check the server log.';
    }
}
$overview = $latest = $active = null;
$models = $metrics = [];
if ($ready) {
    try { $overview = claimMl()->trainingOverview((int)$user['id']); }
    catch (Throwable $e) { $errors[] = 'The training dataset or saved partition needs administrator maintenance: ' . $e->getMessage(); }
    $active = db()->fetchOne('SELECT version FROM decision_tree_models WHERE is_active=1 ORDER BY id DESC LIMIT 1');
    $models = db()->fetchAll('SELECT version,trained_at,train_count,test_count,is_active FROM decision_tree_models ORDER BY id DESC LIMIT 10');
    $latest = db()->fetchOne('SELECT version,metrics_json,split_json,dataset_hash,trained_at FROM decision_tree_models ORDER BY id DESC LIMIT 1');
    if ($latest) {
        try { $metrics = ClaimMlService::decode($latest['metrics_json']); }
        catch (Throwable $e) { $errors[] = 'Saved evaluation metrics are unreadable.'; }
    }
}
$percent = static fn(float $value): string => number_format($value * 100, 2) . '%';
$requiredLabels = count(ClaimFeatures::LABELS) * ClaimTraining::MIN_PER_CLASS;
$labeledTotal = $overview ? array_sum(array_map('array_sum', $overview['counts'])) : 0;
$readinessProgress = min(100, (int)round($labeledTotal / max(1, ClaimTraining::MIN_RECORDS) * 100));
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar_admin.php';
?>
<main class="main-content" id="main-content" tabindex="-1">
  <div class="topbar"><button class="btn-icon me-2" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button><div class="topbar-title"><span class="text-muted-sm">Admin /</span> Decision Tree</div></div>
  <div class="content-area">
    <div class="page-header"><div><h1 class="page-title">Decision Tree — Training &amp; Evaluation</h1><p class="page-subtitle">A single CART classification tree for ownership claim recommendations.</p></div></div>
    <?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>
    <?php if (!$ready): ?>
      <div class="alert alert-warning">Run <code>php scripts/migrate_decision_tree.php</code> from the application folder, or import <code>database/migrations/001_decision_tree.sql</code> into the existing database. Then refresh this page.</div>
    <?php else: ?>
      <div class="alert alert-<?= $active ? 'info' : 'warning' ?>">
        <?php if ($active): ?>Active model: <strong><?= e($active['version']) ?></strong>. Recommendations require independent administrator review.
        <?php else: ?><strong>No trained model is active.</strong> The system collects claim evidence for manual review. An unavailable prediction is not a “Needs Verification” model classification.<?php endif; ?>
      </div>
      <div class="row g-3 mb-4">
        <div class="col-lg-8"><div class="card h-100">
          <div class="card-header"><span class="card-title"><i class="bi bi-database-check"></i> Verified Data Readiness</span></div>
          <div class="card-body">
            <p>Only administrator-labeled, independently reviewed claims with original submission-time snapshots are eligible. Historical approvals, rejections, pending statuses, and predictions are never converted into labels automatically.</p>
            <?php if ($overview): ?>
              <div class="d-flex justify-content-between small mb-1"><span>Overall data collection progress</span><strong><?= $readinessProgress ?>%</strong></div>
              <div class="progress mb-3" role="progressbar" aria-label="Overall data collection progress" aria-valuenow="<?= $readinessProgress ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width:<?= $readinessProgress ?>%"></div>
              </div>
              <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Independent label</th><th>Training</th><th>Held-out test</th><th>Collection gap</th></tr></thead><tbody>
              <?php foreach (ClaimFeatures::LABELS as $label): $labelTotal = $overview['counts']['train'][$label] + $overview['counts']['test'][$label]; ?><tr><td><?= e($label) ?></td><td><?= $overview['counts']['train'][$label] ?></td><td><?= $overview['counts']['test'][$label] ?></td><td><?= max(0, ClaimTraining::MIN_PER_CLASS - $labelTotal) ?> more needed</td></tr><?php endforeach; ?>
              </tbody></table></div>
              <p class="small text-muted">Eligible: <?= $overview['eligible'] ?> · Invalid snapshots skipped: <?= $overview['skipped'] ?> · Connected train/test conflicts excluded: <?= $overview['excluded'] ?>.</p>
              <?php if ($overview['problems']): ?><ul class="small mb-3"><?php foreach ($overview['problems'] as $problem): ?><li><?= e($problem) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/claims.php" class="btn btn-sm btn-outline-primary">Review &amp; Label Claims</a>
          </div>
        </div></div>
        <div class="col-lg-4"><div class="card h-100">
          <div class="card-header"><span class="card-title"><i class="bi bi-diagram-3"></i> Train / Retrain</span></div>
          <div class="card-body">
            <p class="small">Fixed settings: maximum depth 5, minimum 2 samples per leaf. Approximately 80% training / 20% test, grouped by item, claimant, linked lost report, and duplicate submissions.</p>
            <p class="small">Old partition assignments persist on retraining. Test records never fit the saved tree. Minimum sample counts are readiness checks, not a guarantee of accuracy.</p>
            <form method="POST" data-pending-label="Training and evaluating the model…"><?= csrfField() ?><input type="hidden" name="action" value="train"><button class="btn btn-primary" <?= !($overview['ready'] ?? false) ? 'disabled' : '' ?>>Train, Evaluate &amp; Activate</button></form>
            <?php if ($active): ?><form method="POST" class="mt-2"><?= csrfField() ?><input type="hidden" name="action" value="deactivate"><button class="btn btn-outline-secondary btn-sm">Deactivate Model</button></form><?php endif; ?>
            <p class="small text-muted mt-3 mb-0">No claims are approved and no items are released by training or prediction. Existing assessments retain their model version; use “Reassess” on a claim to apply the active tree to its frozen features.</p>
          </div>
        </div></div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><span class="card-title"><i class="bi bi-bar-chart"></i> Latest Held-out Evaluation</span></div>
        <div class="card-body">
          <?php if (!$metrics): ?>
            <p class="mb-0">Accuracy, precision, recall, F1-score, and the confusion matrix are unavailable until there is sufficient valid training and test data. No historical evaluation results have been invented.</p>
          <?php else: ?>
            <p><strong><?= e($latest['version']) ?></strong> · <?= (int)$metrics['test_samples'] ?> test claims · Accuracy: <strong><?= $percent($metrics['accuracy']) ?></strong></p>
            <p class="small">Macro precision: <?= $percent($metrics['macro']['precision']) ?> · Macro recall: <?= $percent($metrics['macro']['recall']) ?> · Macro F1: <?= $percent($metrics['macro']['f1']) ?></p>
            <p class="small">Weighted precision: <?= $percent($metrics['weighted']['precision']) ?> · Weighted recall: <?= $percent($metrics['weighted']['recall']) ?> · Weighted F1: <?= $percent($metrics['weighted']['f1']) ?></p>
            <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Class</th><th>Precision</th><th>Recall</th><th>F1-score</th><th>Test support</th></tr></thead><tbody>
              <?php foreach ($metrics['per_class'] as $label => $values): ?><tr><td><?= e($label) ?></td><td><?= $percent($values['precision']) ?></td><td><?= $percent($values['recall']) ?></td><td><?= $percent($values['f1']) ?></td><td><?= (int)$values['support'] ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
            <h6>Confusion Matrix</h6><p class="small text-muted">Rows = actual independent labels. Columns = predicted labels.</p>
            <div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Actual / Predicted</th><?php foreach (ClaimFeatures::LABELS as $label): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
              <?php foreach ($metrics['confusion_matrix'] as $actual => $row): ?><tr><th><?= e($actual) ?></th><?php foreach ($row as $count): ?><td><?= (int)$count ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            </tbody></table></div>
            <p class="small text-muted">Undefined precision/recall divisions are reported as zero. Repeated evaluation on the same holdout is not a new independent test; do not tune the model against these results. These metrics describe this dataset, not proof of ownership or guaranteed future performance.</p>
            <p class="small text-muted mb-0">Dataset SHA-256: <code><?= e($latest['dataset_hash']) ?></code></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($models): ?>
      <div class="card"><div class="card-header"><span class="card-title">Saved Model Versions</span></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Version</th><th>Trained</th><th>Training records</th><th>Test records</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($models as $model): ?><tr><td><code><?= e($model['version']) ?></code></td><td><?= e($model['trained_at']) ?></td><td><?= (int)$model['train_count'] ?></td><td><?= (int)$model['test_count'] ?></td><td><?= $model['is_active'] ? 'Active' : 'Archived' ?></td></tr><?php endforeach; ?>
      </tbody></table></div></div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
