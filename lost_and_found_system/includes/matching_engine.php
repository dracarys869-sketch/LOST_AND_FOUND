<?php
/**
 * Smart Matching Engine
 *
 * Compares lost items against found items using a weighted similarity algorithm.
 * Score >= MATCH_THRESHOLD (75%) creates a match record and sends notifications.
 *
 * Weights:
 *   Category match    : 25 pts
 *   Item name (fuzzy) : 20 pts
 *   Color match       : 15 pts
 *   Brand match       : 15 pts
 *   Description kw    : 15 pts
 *   Location match    :  5 pts
 *   Date proximity    :  5 pts
 *   Total             : 100 pts
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/auth.php';

class MatchingEngine
{
    private static float $threshold;

    public static function init(): void
    {
        self::$threshold = defined('MATCH_THRESHOLD') ? MATCH_THRESHOLD : 75.0;
    }

    /**
     * Run matching for a newly submitted lost item against all active found items.
     */
    public static function runForLostItem(int $lostItemId): array
    {
        self::init();
        $lost = db()->fetchOne(
            'SELECT li.*, c.name AS category_name, l.name AS location_name
             FROM lost_items li
             LEFT JOIN categories c ON li.category_id = c.id
             LEFT JOIN locations l  ON li.location_id  = l.id
             WHERE li.id = ?',
            [$lostItemId]
        );
        if (!$lost) return [];

        $foundItems = db()->fetchAll(
            'SELECT fi.*, c.name AS category_name, l.name AS location_name
             FROM found_items fi
             LEFT JOIN categories c ON fi.category_id = c.id
             LEFT JOIN locations l  ON fi.location_id  = l.id
             WHERE fi.status IN ("active","matched")',
        );

        $matches = [];
        foreach ($foundItems as $found) {
            if (self::matchAlreadyExists($lostItemId, $found['id'])) continue;

            [$score, $reasons] = self::calculateScore($lost, $found);

            if ($score >= self::$threshold) {
                $matchId = self::createMatch($lostItemId, $found['id'], $score, $reasons);
                self::notifyMatch($lost, $found, $score, $matchId);
                $matches[] = ['found_item' => $found, 'score' => $score, 'match_id' => $matchId];
            }
        }
        return $matches;
    }

    /**
     * Run matching for a newly submitted found item against all active lost items.
     */
    public static function runForFoundItem(int $foundItemId): array
    {
        self::init();
        $found = db()->fetchOne(
            'SELECT fi.*, c.name AS category_name, l.name AS location_name
             FROM found_items fi
             LEFT JOIN categories c ON fi.category_id = c.id
             LEFT JOIN locations l  ON fi.location_id  = l.id
             WHERE fi.id = ?',
            [$foundItemId]
        );
        if (!$found) return [];

        $lostItems = db()->fetchAll(
            'SELECT li.*, c.name AS category_name, l.name AS location_name
             FROM lost_items li
             LEFT JOIN categories c ON li.category_id = c.id
             LEFT JOIN locations l  ON li.location_id  = l.id
             WHERE li.status IN ("active","matched")',
        );

        $matches = [];
        foreach ($lostItems as $lost) {
            if (self::matchAlreadyExists($lost['id'], $foundItemId)) continue;

            [$score, $reasons] = self::calculateScore($lost, $found);

            if ($score >= self::$threshold) {
                $matchId = self::createMatch($lost['id'], $foundItemId, $score, $reasons);
                self::notifyMatch($lost, $found, $score, $matchId);
                $matches[] = ['lost_item' => $lost, 'score' => $score, 'match_id' => $matchId];
            }
        }
        return $matches;
    }

    /**
     * Calculate similarity score between a lost item and a found item.
     * Returns [score (float), reasons (array)].
     */
    public static function calculateScore(array $lost, array $found): array
    {
        $score   = 0.0;
        $reasons = [];

        // ── Category (25 pts) ─────────────────────────────────
        if ($lost['category_id'] === $found['category_id']) {
            $score += 25;
            $reasons['category'] = 25;
        } else {
            $reasons['category'] = 0;
        }

        // ── Item Name (20 pts, fuzzy) ─────────────────────────
        $nameSim = self::stringSimilarity($lost['item_name'], $found['item_name']);
        $nameScore = round($nameSim * 20, 2);
        $score += $nameScore;
        $reasons['item_name'] = $nameScore;

        // ── Color (15 pts) ───────────────────────────────────
        if (!empty($lost['color']) && !empty($found['color'])) {
            $colorSim = self::stringSimilarity($lost['color'], $found['color']);
            $colorScore = round($colorSim * 15, 2);
            $score += $colorScore;
            $reasons['color'] = $colorScore;
        } else {
            $reasons['color'] = 0;
        }

        // ── Brand (15 pts) ───────────────────────────────────
        if (!empty($lost['brand']) && !empty($found['brand'])) {
            $brandSim = self::stringSimilarity($lost['brand'], $found['brand']);
            $brandScore = round($brandSim * 15, 2);
            $score += $brandScore;
            $reasons['brand'] = $brandScore;
        } else {
            $reasons['brand'] = 0;
        }

        // ── Description Keywords (15 pts) ─────────────────────
        if (!empty($lost['description']) && !empty($found['description'])) {
            $kwScore = round(self::keywordOverlap($lost['description'], $found['description']) * 15, 2);
            $score  += $kwScore;
            $reasons['description'] = $kwScore;
        } else {
            $reasons['description'] = 0;
        }

        // ── Location (5 pts) ─────────────────────────────────
        if ($lost['location_id'] === $found['location_id']) {
            $score += 5;
            $reasons['location'] = 5;
        } else {
            $reasons['location'] = 0;
        }

        // ── Date Proximity (5 pts) ───────────────────────────
        $dateLost  = $lost['date_lost']   ?? null;
        $dateFound = $found['date_found'] ?? null;
        if ($dateLost && $dateFound) {
            $diff = abs(strtotime($dateLost) - strtotime($dateFound)) / 86400;
            if ($diff <= 1)  { $score += 5; $reasons['date'] = 5; }
            elseif ($diff <= 3)  { $score += 4; $reasons['date'] = 4; }
            elseif ($diff <= 7)  { $score += 3; $reasons['date'] = 3; }
            elseif ($diff <= 14) { $score += 2; $reasons['date'] = 2; }
            elseif ($diff <= 30) { $score += 1; $reasons['date'] = 1; }
            else { $reasons['date'] = 0; }
        } else {
            $reasons['date'] = 0;
        }

        return [min(100.0, round($score, 2)), $reasons];
    }

    // ─── Private Helpers ──────────────────────────────────────

    private static function stringSimilarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ($a === $b) return 1.0;
        if (empty($a) || empty($b)) return 0.0;

        // similar_text gives percentage
        similar_text($a, $b, $pct);
        // Also check if one contains the other
        $contains = (str_contains($a, $b) || str_contains($b, $a)) ? 0.3 : 0;

        return min(1.0, ($pct / 100) + $contains);
    }

    private static function keywordOverlap(string $textA, string $textB): float
    {
        $stopwords = ['a','an','the','is','in','at','on','of','to','and','or','but','for','with','has','was','are','be','this','that','it','its'];
        $tokenize  = fn(string $t) => array_filter(
            preg_split('/\W+/u', mb_strtolower($t)),
            fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopwords, true)
        );

        $wordsA = array_values($tokenize($textA));
        $wordsB = array_values($tokenize($textB));

        if (empty($wordsA) || empty($wordsB)) return 0.0;

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union        = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union === 0 ? 0.0 : $intersection / $union; // Jaccard index
    }

    private static function matchAlreadyExists(int $lostId, int $foundId): bool
    {
        $row = db()->fetchOne(
            'SELECT id FROM matches WHERE lost_item_id = ? AND found_item_id = ? AND status != "rejected"',
            [$lostId, $foundId]
        );
        return $row !== false;
    }

    private static function createMatch(int $lostId, int $foundId, float $score, array $reasons): int
    {
        $id = (int)db()->insert(
            'INSERT INTO matches (lost_item_id, found_item_id, similarity_score, match_reasons, status, matched_by)
             VALUES (?, ?, ?, ?, "pending", "system")',
            [$lostId, $foundId, $score, json_encode($reasons)]
        );

        // Mark both items as "matched"
        db()->execute('UPDATE lost_items  SET status = "matched" WHERE id = ? AND status = "active"', [$lostId]);
        db()->execute('UPDATE found_items SET status = "matched" WHERE id = ? AND status = "active"', [$foundId]);

        auditLog(null, 'SYSTEM_MATCH_CREATE', 'matches', $id);
        return $id;
    }

    private static function notifyMatch(array $lost, array $found, float $score, int $matchId): void
    {
        $scoreStr = number_format($score, 1);

        // Notify the student who lost the item
        createNotification(
            $lost['user_id'],
            'Possible match found',
            "A possible match ({$scoreStr}% similarity) was found for your lost \"{$lost['item_name']}\". 
             Please review the match details and submit a claim if it's yours.",
            'match',
            $matchId,
            'match'
        );

        // Notify all admins
        $admins = db()->fetchAll('SELECT id FROM users WHERE role = "admin" AND status = "active"');
        foreach ($admins as $admin) {
            createNotification(
                $admin['id'],
                'New Match Detected',
                "System matched Lost #{$lost['report_number']} with Found #{$found['report_number']}. Score: {$scoreStr}%.",
                'match',
                $matchId,
                'match'
            );
        }
    }
}
