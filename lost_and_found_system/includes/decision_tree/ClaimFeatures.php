<?php
/** Fixed, ownership-only feature extraction. No labels or decisions enter this code. */
final class ClaimFeatures
{
    public const VERSION = 'ownership-v1';
    public const NAMES = [
        'category_match', 'description_similarity', 'location_similarity',
        'date_consistency', 'unique_characteristics_match', 'verification_answers_match',
    ];
    public const LABELS = ['Likely Legitimate', 'Needs Verification', 'Suspicious'];
    public const QUESTIONS = [
        'contents' => 'What contents or accessories were with your item?',
        'detail' => 'Describe another private detail (such as an inner label or customization).',
    ];
    public const TITLES = [
        'category_match' => 'Item category',
        'description_similarity' => 'Description similarity',
        'location_similarity' => 'Location similarity',
        'date_consistency' => 'Date consistency',
        'unique_characteristics_match' => 'Unique identifying characteristics',
        'verification_answers_match' => 'Additional verification answers',
    ];

    public static function text(mixed $value, string $name, int $max, int $min = 0): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
        $value = trim($value);
        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$name} must contain {$min}–{$max} characters.");
        }
        return $value;
    }

    public static function id(mixed $value, string $name, bool $optional = false): ?int
    {
        if ($optional && ($value === '' || $value === null || $value === '0' || $value === 0)) return null;
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || (float)$value > 4294967295) {
            throw new InvalidArgumentException("Invalid {$name}.");
        }
        return (int)$value;
    }

    public static function date(mixed $value): ?string
    {
        if ($value === '' || $value === null) return null;
        if (!is_string($value)) throw new InvalidArgumentException('Invalid date lost.');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value || $value > date('Y-m-d')) {
            throw new InvalidArgumentException('Date lost must be a real date, today or earlier.');
        }
        return $value;
    }

    public static function submission(array $input): array
    {
        $answers = $input['verification_answers'] ?? [];
        if (!is_array($answers) || array_diff(array_keys($answers), array_keys(self::QUESTIONS))) {
            throw new InvalidArgumentException('Invalid verification answers.');
        }
        $clean = [];
        foreach (self::QUESTIONS as $key => $question) {
            $clean[$key] = self::text($answers[$key] ?? '', $question, 1000);
        }
        return [
            'category_id' => self::id($input['claimed_category_id'] ?? null, 'category', true),
            'location_id' => self::id($input['claimed_location_id'] ?? null, 'location', true),
            'date_lost' => self::date($input['claimed_date_lost'] ?? null),
            'description' => self::text($input['proof_description'] ?? '', 'Proof of ownership', 4000, 10),
            'unique_characteristics' => self::text($input['unique_characteristics'] ?? '', 'Unique characteristics', 2000),
            'verification_answers' => $clean,
        ];
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    /** Jaccard overlap of unique tokens; fixed preprocessing, never fitted on test data. */
    public static function similarity(string $a, string $b): float
    {
        $stop = ['a','an','the','is','was','in','on','at','to','and','of','with','my','it','this','item','found','lost'];
        $tokens = static fn(string $s): array => array_values(array_diff(
            array_unique(explode(' ', self::normalize($s))), array_merge([''], $stop)
        ));
        $left = $tokens($a);
        $right = $tokens($b);
        if (!$left || !$right) return -1.0;
        return count(array_intersect($left, $right)) / count(array_unique(array_merge($left, $right)));
    }

    /** Both arrays are immutable submission-time snapshots, assembled on the server. */
    public static function extract(array $submitted, array $reference): array
    {
        $features = array_fill_keys(self::NAMES, -1.0);
        $notes = array_fill_keys(self::NAMES, 'Missing claimant information or independently recorded reference.');

        if (!empty($submitted['category_id']) && !empty($reference['category_id'])) {
            $features['category_match'] = (int)$submitted['category_id'] === (int)$reference['category_id'] ? 1.0 : 0.0;
            $notes['category_match'] = $features['category_match'] === 1.0 ? 'Same category.' : 'Different categories.';
        }
        $features['description_similarity'] = self::similarity($submitted['description'] ?? '', $reference['description'] ?? '');
        if ($features['description_similarity'] >= 0) {
            $notes['description_similarity'] = 'Word overlap only; wording and paraphrases can change this score.';
        }
        if (!empty($submitted['location_id']) && !empty($reference['location_id'])) {
            $same = (int)$submitted['location_id'] === (int)$reference['location_id'];
            $building = self::normalize($submitted['building'] ?? '');
            $near = $building !== '' && $building === self::normalize($reference['building'] ?? '');
            $features['location_similarity'] = $same ? 1.0 : ($near ? 0.5 : 0.0);
            $notes['location_similarity'] = $same ? 'Same location.' : ($near ? 'Different locations in the same building.' : 'Different locations/buildings. Items may have been moved.');
        }
        $lost = self::validStoredDate($submitted['date_lost'] ?? null);
        $found = self::validStoredDate($reference['date_found'] ?? null);
        if ($lost && $found) {
            $days = (int)$lost->diff($found)->format('%r%a');
            $features['date_consistency'] = $days >= 0 ? 1.0 : 0.0;
            $notes['date_consistency'] = $days >= 0
                ? "Reported lost {$days} day(s) before it was found."
                : 'Reported loss is after the found date; check the dates with the claimant.';
        }

        $marks = array_values(array_filter(array_unique(array_map(
            [self::class, 'normalize'], preg_split('/\R/u', $reference['unique_characteristics'] ?? '')
        )), static fn(string $s): bool => $s !== ''));
        $claimedMarks = self::normalize($submitted['unique_characteristics'] ?? '');
        if ($marks && $claimedMarks !== '') {
            $matches = count(array_filter($marks, static fn(string $mark): bool => str_contains(' ' . $claimedMarks . ' ', ' ' . $mark . ' ')));
            $features['unique_characteristics_match'] = $matches / count($marks);
            $notes['unique_characteristics_match'] = "{$matches} of " . count($marks) . ' private identifying phrases matched (case/punctuation normalized).';
        }

        $expected = array_filter($reference['verification_answers'] ?? [], static fn($v): bool => is_string($v) && trim($v) !== '');
        $answered = 0;
        $sum = 0.0;
        foreach (self::QUESTIONS as $key => $question) {
            if (!isset($expected[$key])) continue;
            $answer = $submitted['verification_answers'][$key] ?? '';
            if (trim($answer) !== '') {
                $answered++;
                $sum += max(0.0, self::similarity($answer, $expected[$key]));
            }
        }
        if ($expected && $answered) {
            // Unanswered expected questions contribute zero, not a falsely perfect match.
            $features['verification_answers_match'] = $sum / count($expected);
            $notes['verification_answers_match'] = "{$answered} of " . count($expected) . ' expected questions answered; score is average word overlap.';
        }

        $evidence = [];
        foreach ($features as $key => $value) {
            $features[$key] = round($value, 6);
            $evidence[] = [
                'feature' => $key, 'title' => self::TITLES[$key], 'score' => $features[$key],
                'state' => $value < 0 ? 'missing' : ($value == 0.0 ? 'conflicting' : ($value == 1.0 ? 'matching' : 'partial')),
                'detail' => $notes[$key],
            ];
        }
        self::validate($features);
        return ['features' => $features, 'evidence' => $evidence];
    }

    public static function validate(array $features): void
    {
        if (count($features) !== count(self::NAMES) || array_diff(self::NAMES, array_keys($features))) {
            throw new InvalidArgumentException('The ownership feature schema does not match.');
        }
        foreach ($features as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value)
                || ($value != -1 && ($value < 0 || $value > 1))) {
                throw new InvalidArgumentException('Features must be -1 (unknown) or a number from 0 to 1.');
            }
        }
    }

    private static function validStoredDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
