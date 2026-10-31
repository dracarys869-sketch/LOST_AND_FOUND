<?php
require_once __DIR__ . '/DecisionTree.php';

/** Dataset partitioning and held-out evaluation for the single Decision Tree. */
final class ClaimTraining
{
    public const MIN_RECORDS = 30;
    public const MIN_PER_CLASS = 10;
    public const MIN_TRAIN_PER_CLASS = 6;
    public const MIN_TEST_PER_CLASS = 2;

    /**
     * Connected claims stay together: found item, claimant, linked lost report, or
     * duplicate submission. IDs are used ONLY to partition, never as model inputs.
     * Previous assignments persist across retraining. A newly connected train/test
     * component is excluded rather than moving old training examples into the test set.
     */
    public static function split(array $records, array $registry = []): array
    {
        if (count($records) > 10000) throw new InvalidArgumentException('Training is limited to 10,000 verified records per run.');
        foreach ($registry as $partition) {
            if (!in_array($partition, ['train', 'test'], true)) throw new UnexpectedValueException('Invalid saved dataset partition.');
        }
        usort($records, static fn($a, $b): int => $a['id'] <=> $b['id']);
        $parent = $entities = $owner = $seen = [];
        foreach ($records as $i => $row) {
            ClaimFeatures::validate($row['features']);
            if (!in_array($row['label'], ClaimFeatures::LABELS, true) || isset($seen[$row['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate labeled claim.');
            }
            foreach (['id', 'found_item_id', 'claimant_id'] as $field) ClaimFeatures::id($row[$field], $field);
            $seen[$row['id']] = true;
            $parent[$i] = $i;
            $entities[$i] = ['found:' . $row['found_item_id'], 'claimant:' . $row['claimant_id']];
            if (!empty($row['lost_item_id'])) $entities[$i][] = 'lost:' . ClaimFeatures::id($row['lost_item_id'], 'lost item');
            if (!empty($row['fingerprint'])) $entities[$i][] = 'submission:' . $row['fingerprint'];
            foreach ($entities[$i] as $key) {
                if (isset($owner[$key])) {
                    $a = self::root($parent, $i);
                    $b = self::root($parent, $owner[$key]);
                    $parent[$a] = $b;
                } else {
                    $owner[$key] = $i;
                }
            }
        }
        $groups = [];
        foreach ($records as $i => $row) $groups[self::root($parent, $i)][] = $i;
        $parts = ['train' => [], 'test' => [], 'excluded' => [], 'registry' => $registry, 'groups' => ['train' => 0, 'test' => 0, 'excluded' => 0]];
        foreach ($groups as $indices) {
            $keys = [];
            foreach ($indices as $i) $keys = array_merge($keys, $entities[$i]);
            $keys = array_values(array_unique($keys));
            sort($keys, SORT_STRING);
            $known = [];
            foreach ($keys as $key) if (isset($registry[$key])) $known[$registry[$key]] = true;
            if (count($known) > 1) {
                $part = 'excluded';
            } else {
                $part = $known ? array_key_first($known)
                    : (hexdec(substr(hash('sha256', 'ownership-holdout-v1:' . $keys[0]), 0, 8)) % 5 === 0 ? 'test' : 'train');
                foreach ($keys as $key) $parts['registry'][$key] = $part;
            }
            $parts['groups'][$part]++;
            foreach ($indices as $i) $parts[$part][] = $records[$i];
        }
        return $parts;
    }

    public static function readiness(array $parts): array
    {
        $counts = [];
        foreach (['train', 'test'] as $part) {
            $counts[$part] = array_fill_keys(ClaimFeatures::LABELS, 0);
            foreach ($parts[$part] as $row) $counts[$part][$row['label']]++;
        }
        $problems = [];
        $total = count($parts['train']) + count($parts['test']);
        if ($total < self::MIN_RECORDS) $problems[] = 'At least 30 eligible, independently reviewed claims are required.';
        if (count($parts['train']) < 20) $problems[] = 'At least 20 claims must be in the training partition.';
        foreach (ClaimFeatures::LABELS as $label) {
            if ($counts['train'][$label] + $counts['test'][$label] < self::MIN_PER_CLASS) {
                $problems[] = "Collect at least 10 independently labeled “{$label}” claims.";
            }
            if ($counts['train'][$label] < self::MIN_TRAIN_PER_CLASS || $counts['test'][$label] < self::MIN_TEST_PER_CLASS) {
                $problems[] = "“{$label}” needs at least 6 training and 2 held-out test claims.";
            }
        }
        if ($parts['groups']['train'] < 2 || $parts['groups']['test'] < 2) {
            $problems[] = 'Each partition needs at least two independent groups of items/claimants.';
        }
        return ['ready' => !$problems, 'problems' => $problems, 'counts' => $counts, 'eligible' => $total, 'excluded' => count($parts['excluded'])];
    }

    public static function train(array $records, array $registry = []): array
    {
        $parts = self::split($records, $registry);
        $ready = self::readiness($parts);
        if (!$ready['ready']) throw new DomainException(implode(' ', $ready['problems']));
        $model = new DecisionTree(5, 2);
        $model->fit($parts['train']);
        $actual = $predicted = [];
        foreach ($parts['test'] as $row) {
            $actual[] = $row['label'];
            $predicted[] = $model->predict($row['features'])['classification'];
        }
        // The saved tree is the same tree evaluated here; it is NOT refit on test records.
        return [
            'model_json' => $model->toJson(), 'metrics' => self::evaluate($actual, $predicted),
            'split' => [
                'method' => 'persistent connected-group holdout, approximately 80/20, fixed hash v1',
                'train_claim_ids' => array_column($parts['train'], 'id'),
                'test_claim_ids' => array_column($parts['test'], 'id'),
                'excluded_claim_ids' => array_column($parts['excluded'], 'id'),
                'groups' => $parts['groups'], 'registry' => $parts['registry'], 'class_counts' => $ready['counts'],
            ],
            'dataset_hash' => hash('sha256', json_encode($records, JSON_THROW_ON_ERROR)),
            'train_count' => count($parts['train']), 'test_count' => count($parts['test']),
        ];
    }

    /** Confusion matrix rows = actual, columns = predicted. Undefined divisions = 0. */
    public static function evaluate(array $actual, array $predicted): array
    {
        if (!$actual || count($actual) !== count($predicted)) throw new InvalidArgumentException('A nonempty held-out test set is required.');
        $matrix = array_fill_keys(ClaimFeatures::LABELS, array_fill_keys(ClaimFeatures::LABELS, 0));
        foreach ($actual as $i => $label) {
            if (!in_array($label, ClaimFeatures::LABELS, true) || !in_array($predicted[$i], ClaimFeatures::LABELS, true)) {
                throw new InvalidArgumentException('Invalid evaluation label.');
            }
            $matrix[$label][$predicted[$i]]++;
        }
        $correct = 0;
        $perClass = [];
        $macro = $weighted = ['precision' => 0.0, 'recall' => 0.0, 'f1' => 0.0];
        $n = count($actual);
        foreach (ClaimFeatures::LABELS as $label) {
            $tp = $matrix[$label][$label];
            $correct += $tp;
            $support = array_sum($matrix[$label]);
            $predCount = array_sum(array_column($matrix, $label));
            $precision = $predCount ? $tp / $predCount : 0.0;
            $recall = $support ? $tp / $support : 0.0;
            $f1 = $precision + $recall > 0 ? 2 * $precision * $recall / ($precision + $recall) : 0.0;
            $perClass[$label] = ['precision' => $precision, 'recall' => $recall, 'f1' => $f1, 'support' => $support, 'predicted' => $predCount];
            foreach ($macro as $metric => $unused) {
                $macro[$metric] += $perClass[$label][$metric] / count(ClaimFeatures::LABELS);
                $weighted[$metric] += $perClass[$label][$metric] * $support / $n;
            }
        }
        return ['test_samples' => $n, 'accuracy' => $correct / $n, 'macro' => $macro, 'weighted' => $weighted, 'per_class' => $perClass, 'confusion_matrix' => $matrix];
    }

    private static function root(array &$parent, int $i): int
    {
        while ($parent[$i] !== $i) {
            $parent[$i] = $parent[$parent[$i]];
            $i = $parent[$i];
        }
        return $i;
    }
}
