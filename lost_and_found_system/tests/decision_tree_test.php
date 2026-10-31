<?php
/** Synthetic unit fixtures only. None are historical records or production training data. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/decision_tree/ClaimTraining.php';
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rejects(callable $fn, string $message): void
{
    try { $fn(); } catch (InvalidArgumentException | DomainException | UnexpectedValueException | JsonException | LogicException $e) { check(true, $message); return; }
    check(false, $message);
}
function closeTo(float $a, float $b): bool { return abs($a - $b) < 0.000001; }
function vector(float $similarity): array
{
    return ['category_match' => 1.0, 'description_similarity' => $similarity, 'location_similarity' => 1.0,
        'date_consistency' => 1.0, 'unique_characteristics_match' => $similarity, 'verification_answers_match' => $similarity];
}

$input = [
    'claimed_category_id' => '1', 'claimed_location_id' => '2', 'claimed_date_lost' => '2024-02-28',
    'proof_description' => 'Blue canvas bag with silver zipper', 'unique_characteristics' => 'triangle sticker; ABC 42',
    'verification_answers' => ['contents' => 'red notebook', 'detail' => 'striped inner pocket'],
    'classification' => 'Likely Legitimate', 'status' => 'approved', 'claimant_id' => 999,
];
$submitted = ClaimFeatures::submission($input) + ['building' => 'Main Hall'];
$reference = ['category_id' => 1, 'location_id' => 2, 'building' => 'Main Hall', 'date_found' => '2024-03-01',
    'description' => $submitted['description'], 'unique_characteristics' => "triangle sticker\nABC-42",
    'verification_answers' => $submitted['verification_answers']];
$extracted = ClaimFeatures::extract($submitted, $reference);
check(count($extracted['evidence']) === 6, 'All six evidence features must be provided.');
foreach ($extracted['features'] as $feature) check($feature === 1.0, 'Fully matching evidence should score 1.');
foreach ($extracted['evidence'] as $row) check($row['state'] === 'matching', 'Perfect comparisons should be described as matching.');
check(!isset($submitted['classification'], $submitted['status'], $submitted['claimant_id']), 'Untrusted result/identity fields must not enter features.');
check(ClaimFeatures::similarity('red red red red bag', 'red bag') === 1.0, 'Repeated words must not inflate similarity.');
check(ClaimFeatures::normalize('ÉTIQUETTE: 青い-42') === 'étiquette 青い 42', 'Unicode normalization should retain identifying text.');
check(ClaimFeatures::similarity('', '') === -1.0, 'Empty texts must not be a match.');

$changed = $submitted;
$changed['category_id'] = 2;
$changed['location_id'] = 3;
$changed['date_lost'] = '2024-03-02';
$changed['unique_characteristics'] = 'ABC-420';
$changed['verification_answers']['detail'] = '';
$features = ClaimFeatures::extract($changed, $reference)['features'];
check($features['category_match'] === 0.0, 'Different categories conflict.');
check($features['location_similarity'] === 0.5, 'Same building gives partial location agreement.');
check($features['date_consistency'] === 0.0, 'Loss after finding is inconsistent.');
check($features['unique_characteristics_match'] === 0.0, 'Partial serial-number prefixes must not match.');
check($features['verification_answers_match'] === 0.5, 'Missing expected answers must reduce comparison coverage.');
$missing = ClaimFeatures::extract(['description' => 'A bag'], [])['features'];
foreach ($missing as $value) check($value === -1.0, 'Unknown reference must remain unknown.');
foreach ([['claimed_category_id' => []], ['claimed_location_id' => '-1'], ['claimed_date_lost' => '2025-02-29'],
    ['claimed_date_lost' => '2999-01-01'], ['proof_description' => ''], ['proof_description' => []],
    ['proof_description' => str_repeat('a', 4001)], ['verification_answers' => 'bad'],
    ['verification_answers' => ['unexpected' => 'secret']], ['unique_characteristics' => "bad\0value"]] as $invalid) {
    rejects(fn() => ClaimFeatures::submission(array_replace($input, $invalid)), 'Malformed claim must be rejected.');
}
rejects(fn() => ClaimFeatures::validate([]), 'Missing feature vector rejected.');
rejects(fn() => ClaimFeatures::validate(array_replace(vector(0.5), ['description_similarity' => NAN])), 'Nonfinite feature rejected.');
rejects(fn() => ClaimFeatures::validate(array_replace(vector(0.5), ['description_similarity' => '0.5'])), 'Unvalidated numeric strings rejected.');

$rows = [];
foreach (['Suspicious' => 0.1, 'Needs Verification' => 0.5, 'Likely Legitimate' => 0.9] as $label => $base) {
    for ($i = 0; $i < 6; $i++) $rows[] = ['features' => vector($base + $i / 1000), 'label' => $label];
}
$tree = new DecisionTree();
rejects(fn() => $tree->predict(vector(0.5)), 'Untrained tree must not predict.');
$tree->fit($rows);
foreach (['Suspicious' => 0.11, 'Needs Verification' => 0.51, 'Likely Legitimate' => 0.91] as $label => $value) {
    check($tree->predict(vector($value))['classification'] === $label, 'CART should learn all three classes.');
}
$loaded = DecisionTree::fromJson($tree->toJson());
check($loaded->predict(vector(0.51)) === $tree->predict(vector(0.51)), 'Model JSON round trip preserves predictions and decision path.');
$swapped = array_map(static function ($row) {
    $row['label'] = match ($row['label']) { 'Suspicious' => 'Likely Legitimate', 'Likely Legitimate' => 'Suspicious', default => $row['label'] };
    return $row;
}, $rows);
$learner = new DecisionTree();
$learner->fit($swapped);
check($learner->predict(vector(0.11))['classification'] === 'Likely Legitimate', 'Predictions must be learned from labels, not hard-coded rules.');
$constant = new DecisionTree();
$constant->fit([['features' => vector(0.5), 'label' => 'Suspicious'], ['features' => vector(0.5), 'label' => 'Needs Verification']]);
check($constant->predict(vector(0.5))['path'] === [], 'Unsplittable data produces a leaf safely.');
rejects(fn() => DecisionTree::fromJson('{broken'), 'Corrupt model rejected.');
$corrupt = json_decode($tree->toJson(), true);
$corrupt['tree']['feature'] = 'claimant_age';
rejects(fn() => DecisionTree::fromJson(json_encode($corrupt)), 'Unrelated personal features must not load.');
$corrupt = json_decode($tree->toJson(), true);
$corrupt['tree']['samples']++;
rejects(fn() => DecisionTree::fromJson(json_encode($corrupt)), 'Inconsistent class counts must not load.');

$l = ClaimFeatures::LABELS;
$metrics = ClaimTraining::evaluate([$l[0],$l[0],$l[1],$l[1],$l[2],$l[2]], [$l[0],$l[1],$l[1],$l[2],$l[2],$l[2]]);
check(closeTo($metrics['accuracy'], 4 / 6), 'Known confusion matrix accuracy.');
check(closeTo($metrics['per_class'][$l[0]]['precision'], 1), 'Known class precision.');
check(closeTo($metrics['per_class'][$l[0]]['recall'], 0.5), 'Known class recall.');
check(closeTo($metrics['per_class'][$l[2]]['f1'], 0.8), 'Known class F1.');
check(closeTo($metrics['macro']['f1'], (2 / 3 + 0.5 + 0.8) / 3), 'Macro F1 is mean per-class F1.');
check($metrics['confusion_matrix'][$l[0]][$l[1]] === 1, 'Matrix uses actual rows, predicted columns.');
check(ClaimTraining::evaluate([$l[0]], [$l[0]])['per_class'][$l[1]]['precision'] === 0.0, 'Undefined precision safely zero.');
rejects(fn() => ClaimTraining::evaluate([], []), 'Empty test set must not claim accuracy.');
rejects(fn() => ClaimTraining::train([]), 'Insufficient real data must block training.');

$dataset = [];
for ($i = 1; $i <= 180; $i++) {
    $class = $i % 3;
    $dataset[] = ['id' => $i, 'found_item_id' => $i, 'claimant_id' => $i + 1000, 'lost_item_id' => null,
        'fingerprint' => hash('sha256', 'synthetic-unit-' . $i), 'features' => vector([0.9,0.5,0.1][$class]), 'label' => $l[$class]];
}
$parts = ClaimTraining::split($dataset);
check(ClaimTraining::readiness($parts)['ready'], 'Diverse fixture supports separate training/test sets.');
check(!array_intersect(array_column($parts['train'], 'id'), array_column($parts['test'], 'id')), 'Train and test claims disjoint.');
$trained = ClaimTraining::train($dataset);
$perturbed = $dataset;
$testIds = array_column($parts['test'], 'id');
foreach ($perturbed as &$row) if (in_array($row['id'], $testIds, true)) { $row['features'] = vector(0.01); }
unset($row);
$trainedAgain = ClaimTraining::train($perturbed, $parts['registry']);
check($trained['model_json'] === $trainedAgain['model_json'], 'Changing only held-out features must not alter the fitted tree.');
check($trained['metrics'] !== $trainedAgain['metrics'], 'Evaluation actually uses held-out examples.');
$related = array_slice($dataset, 0, 3);
$related[1]['found_item_id'] = $related[0]['found_item_id'];
$related[2]['claimant_id'] = $related[1]['claimant_id'];
$grouped = ClaimTraining::split($related);
check(count($grouped['train']) === 3 || count($grouped['test']) === 3, 'Transitive relationships stay in one partition.');
$collision = ClaimTraining::split($related, ['found:1' => 'train', 'claimant:1002' => 'test']);
check(count($collision['excluded']) === 3, 'Connected old train/test groups are excluded, never leaked.');
$duplicates = [$dataset[0], $dataset[8]];
$duplicates[1]['fingerprint'] = $duplicates[0]['fingerprint'];
$dupeSplit = ClaimTraining::split($duplicates);
check(count($dupeSplit['train']) === 2 || count($dupeSplit['test']) === 2, 'Duplicate submissions stay in one partition.');
check(ClaimTraining::split($dataset, $parts['registry'])['registry'] === $parts['registry'], 'Retraining preserves holdout membership.');

echo "PASS: {$checks} Decision Tree / feature / leakage / metric checks. Synthetic fixtures only; no production accuracy claim.\n";
