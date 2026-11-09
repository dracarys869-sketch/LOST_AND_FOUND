<?php
/** Shared, read-only item browsing for the public board and student portal. */
require_once __DIR__ . '/functions.php';

function loadItemBoard(array $input, string $baseUrl, bool $includeMatches = false): array
{
    $type = ($input['type'] ?? '') === 'lost' ? 'lost' : 'found';
    $search = is_string($input['search'] ?? null) ? mb_substr(trim($input['search']), 0, 100) : '';
    $category = max(0, (int)filter_var($input['category'] ?? 0, FILTER_VALIDATE_INT));
    $sort = ($input['sort'] ?? '') === 'oldest' ? 'oldest' : 'newest';
    $page = max(1, (int)filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT));
    $table = $type === 'lost' ? 'lost_items' : 'found_items';
    $dateColumn = $type === 'lost' ? 'date_lost' : 'date_found';
    $statuses = $includeMatches ? '("active","matched")' : '("active")';

    // Only general report details are searchable. Ownership evidence is never selected.
    $sql = "SELECT i.id,i.item_name,i.brand,i.color,i.{$dateColumn} AS item_date,
                   c.name AS category_name,c.icon AS category_icon,l.name AS location_name
            FROM {$table} i
            LEFT JOIN categories c ON c.id=i.category_id
            LEFT JOIN locations l ON l.id=i.location_id
            WHERE i.status IN {$statuses}";
    $params = [];
    if ($search !== '') {
        $term = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $sql .= " AND (i.item_name LIKE ? ESCAPE '!' OR i.brand LIKE ? ESCAPE '!' OR i.color LIKE ? ESCAPE '!' OR l.name LIKE ? ESCAPE '!')";
        $params = [$term, $term, $term, $term];
    }
    if ($category) {
        $sql .= ' AND i.category_id=?';
        $params[] = $category;
    }
    $direction = $sort === 'oldest' ? 'ASC' : 'DESC';
    $sql .= " ORDER BY i.created_at {$direction},i.id {$direction}";

    return [
        'type' => $type, 'search' => $search, 'category' => $category, 'sort' => $sort,
        'base_url' => $baseUrl, 'categories' => getCategories(false),
        'pager' => paginate($sql, $params, $page, 9),
        'counts' => [
            'lost' => (int)(db()->fetchOne("SELECT COUNT(*) AS n FROM lost_items WHERE status IN {$statuses}")['n'] ?? 0),
            'found' => (int)(db()->fetchOne("SELECT COUNT(*) AS n FROM found_items WHERE status IN {$statuses}")['n'] ?? 0),
        ],
    ];
}

function itemBoardUrl(array $board, array $changes = []): string
{
    $query = array_replace([
        'type' => $board['type'], 'search' => $board['search'],
        'category' => $board['category'], 'sort' => $board['sort'],
    ], $changes);
    $query = array_filter($query, static fn($value) => $value !== '' && $value !== 0 && $value !== null);
    return $board['base_url'] . '?' . http_build_query($query) . '#item-board';
}
