<?php
require_once __DIR__ . '/ClaimFeatures.php';

/** A single CART classification tree, trained by minimizing weighted Gini impurity. */
final class DecisionTree
{
    private array $tree = [];
    private int $maxDepth;
    private int $minLeaf;

    public function __construct(int $maxDepth = 5, int $minLeaf = 2)
    {
        if ($maxDepth < 1 || $maxDepth > 10 || $minLeaf < 1) throw new InvalidArgumentException('Invalid tree parameters.');
        $this->maxDepth = $maxDepth;
        $this->minLeaf = $minLeaf;
    }

    public function fit(array $rows): void
    {
        if (count($rows) < 2 || count($rows) > 10000) throw new InvalidArgumentException('Training requires 2–10,000 records.');
        foreach ($rows as $row) {
            ClaimFeatures::validate($row['features']);
            if (!in_array($row['label'], ClaimFeatures::LABELS, true)) throw new InvalidArgumentException('Invalid training label.');
        }
        $this->tree = $this->build(array_values($rows), 0);
    }

    public function predict(array $features): array
    {
        ClaimFeatures::validate($features);
        if (!$this->tree) throw new LogicException('The Decision Tree has not been trained.');
        $node = $this->tree;
        $path = [];
        while (isset($node['feature'])) {
            $name = $node['feature'];
            $left = $features[$name] <= $node['threshold'];
            $path[] = ['feature' => $name, 'value' => $features[$name], 'threshold' => $node['threshold'], 'branch' => $left ? '<=' : '>'];
            $node = $node[$left ? 'left' : 'right'];
        }
        return ['classification' => $node['label'], 'path' => $path, 'leaf_samples' => $node['samples'], 'leaf_counts' => $node['counts']];
    }

    public function toJson(): string
    {
        if (!$this->tree) throw new LogicException('Cannot save an untrained tree.');
        return json_encode([
            'algorithm' => 'DecisionTree-CART', 'format_version' => 1,
            'feature_version' => ClaimFeatures::VERSION, 'feature_names' => ClaimFeatures::NAMES,
            'labels' => ClaimFeatures::LABELS, 'max_depth' => $this->maxDepth,
            'min_samples_leaf' => $this->minLeaf, 'tree' => $this->tree,
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function fromJson(string $json): self
    {
        if (strlen($json) > 2097152) throw new UnexpectedValueException('Model is too large.');
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['algorithm'] ?? '') !== 'DecisionTree-CART'
            || ($data['format_version'] ?? null) !== 1 || ($data['feature_version'] ?? '') !== ClaimFeatures::VERSION
            || ($data['feature_names'] ?? []) !== ClaimFeatures::NAMES || ($data['labels'] ?? []) !== ClaimFeatures::LABELS
            || !is_int($data['max_depth'] ?? null) || !is_int($data['min_samples_leaf'] ?? null)
            || !is_array($data['tree'] ?? null)) {
            throw new UnexpectedValueException('Incompatible Decision Tree model.');
        }
        $model = new self($data['max_depth'], $data['min_samples_leaf']);
        $model->validateNode($data['tree'], 0);
        $model->tree = $data['tree'];
        return $model;
    }

    private function build(array $rows, int $depth): array
    {
        $counts = array_fill_keys(ClaimFeatures::LABELS, 0);
        foreach ($rows as $row) $counts[$row['label']]++;
        $n = count($rows);
        // Deterministic tie-breaking only; actual classes come from labeled training samples.
        $label = 'Needs Verification';
        foreach (['Needs Verification', 'Suspicious', 'Likely Legitimate'] as $candidate) {
            if ($counts[$candidate] > $counts[$label]) $label = $candidate;
        }
        $node = ['label' => $label, 'samples' => $n, 'counts' => $counts];
        $gini = self::gini($counts, $n);
        if ($depth >= $this->maxDepth || $n < 2 * $this->minLeaf || $gini < 1e-12) return $node;

        $bestGain = 1e-7;
        $best = null;
        foreach (ClaimFeatures::NAMES as $feature) {
            $ordered = $rows;
            usort($ordered, static fn($a, $b): int => $a['features'][$feature] <=> $b['features'][$feature]);
            $leftCounts = array_fill_keys(ClaimFeatures::LABELS, 0);
            $rightCounts = $counts;
            for ($i = 0; $i < $n - 1; $i++) {
                $leftCounts[$ordered[$i]['label']]++;
                $rightCounts[$ordered[$i]['label']]--;
                $nl = $i + 1;
                $nr = $n - $nl;
                $value = $ordered[$i]['features'][$feature];
                $next = $ordered[$i + 1]['features'][$feature];
                if ($nl < $this->minLeaf || $nr < $this->minLeaf || $value == $next) continue;
                $gain = $gini - ($nl * self::gini($leftCounts, $nl) + $nr * self::gini($rightCounts, $nr)) / $n;
                if ($gain > $bestGain) {
                    $bestGain = $gain;
                    $best = ['feature' => $feature, 'threshold' => ($value + $next) / 2];
                }
            }
        }
        if ($best === null) return $node;
        $left = $right = [];
        foreach ($rows as $row) {
            if ($row['features'][$best['feature']] <= $best['threshold']) $left[] = $row;
            else $right[] = $row;
        }
        return $node + $best + ['left' => $this->build($left, $depth + 1), 'right' => $this->build($right, $depth + 1)];
    }

    private static function gini(array $counts, int $n): float
    {
        if ($n === 0) return 0.0;
        $sum = 0.0;
        foreach ($counts as $count) $sum += ($count / $n) ** 2;
        return 1.0 - $sum;
    }

    private function validateNode(array $node, int $depth): void
    {
        if ($depth > $this->maxDepth || !in_array($node['label'] ?? '', ClaimFeatures::LABELS, true)
            || !is_int($node['samples'] ?? null) || $node['samples'] < 1 || !is_array($node['counts'] ?? null)
            || array_keys($node['counts']) !== ClaimFeatures::LABELS) {
            throw new UnexpectedValueException('Invalid tree node.');
        }
        foreach ($node['counts'] as $count) {
            if (!is_int($count) || $count < 0) throw new UnexpectedValueException('Invalid class counts.');
        }
        if (array_sum($node['counts']) !== $node['samples'] || $node['counts'][$node['label']] !== max($node['counts'])) {
            throw new UnexpectedValueException('Inconsistent tree node.');
        }
        if (isset($node['feature'])) {
            $threshold = $node['threshold'] ?? null;
            if (!in_array($node['feature'], ClaimFeatures::NAMES, true)
                || (!is_int($threshold) && !is_float($threshold)) || !is_finite((float)$threshold)
                || $threshold < -1 || $threshold > 1 || !is_array($node['left'] ?? null) || !is_array($node['right'] ?? null)) {
                throw new UnexpectedValueException('Invalid tree split.');
            }
            $this->validateNode($node['left'], $depth + 1);
            $this->validateNode($node['right'], $depth + 1);
            foreach (ClaimFeatures::LABELS as $label) {
                if ($node['counts'][$label] !== $node['left']['counts'][$label] + $node['right']['counts'][$label]) {
                    throw new UnexpectedValueException('Inconsistent child counts.');
                }
            }
        } elseif (isset($node['left']) || isset($node['right']) || isset($node['threshold'])) {
            throw new UnexpectedValueException('Invalid leaf.');
        }
    }
}
