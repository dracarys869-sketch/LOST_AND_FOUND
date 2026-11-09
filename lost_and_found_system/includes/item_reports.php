<?php
/** Shared validation and persistence for lost/found report forms. */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/matching_engine.php';
require_once __DIR__ . '/qr_generator.php';

function reportInput(array $input, string $type, array $categories, array $locations): array
{
    if (!in_array($type, ['lost', 'found'], true)) throw new InvalidArgumentException('Invalid report type.');
    $values = [];
    $errors = [];
    foreach (['item_name', 'brand', 'color', 'description', 'storage_location', 'date_' . $type, 'time_' . $type] as $key) {
        $values[$key] = is_string($input[$key] ?? null) ? sanitize($input[$key]) : '';
        if (isset($input[$key]) && !is_string($input[$key])) $errors[] = 'Please enter a valid ' . str_replace('_', ' ', $key) . '.';
        if (is_string($input[$key] ?? null) && str_contains($input[$key], "\0")) $errors[] = 'Please remove invalid characters from ' . str_replace('_', ' ', $key) . '.';
    }
    $values['category_id'] = (int)filter_var($input['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $values['location_id'] = (int)filter_var($input['location_id'] ?? 0, FILTER_VALIDATE_INT);
    if (mb_strlen($values['item_name']) < 2) $errors[] = 'Enter an item name with at least 2 characters.';
    foreach (['item_name' => 150, 'brand' => 100, 'color' => 50, 'description' => 4000, 'storage_location' => 200] as $key => $limit) {
        if (mb_strlen($values[$key]) > $limit) $errors[] = ucfirst(str_replace('_', ' ', $key)) . " must be {$limit} characters or fewer.";
    }
    if (!in_array($values['category_id'], array_map('intval', array_column($categories, 'id')), true)) $errors[] = 'Select an available category.';
    if (!in_array($values['location_id'], array_map('intval', array_column($locations, 'id')), true)) $errors[] = 'Select an available campus location.';
    $dateValue = $values['date_' . $type];
    $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $dateValue) ? DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue) : false;
    if (!$date || $date->format('Y-m-d') !== $dateValue) $errors[] = 'Enter a valid date ' . $type . '.';
    elseif ($dateValue < '1000-01-01' || $dateValue > date('Y-m-d')) $errors[] = 'The date ' . $type . ' must be a valid past date or today.';
    $time = $values['time_' . $type];
    if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/D', $time)) $errors[] = 'Enter a valid time.';
    return ['values' => $values, 'errors' => array_unique($errors)];
}

/** Invalid photos abort the whole report, rather than disappearing silently. */
function uploadReportPhotos(array $files, string $directory): array
{
    if (!$files || empty($files['name'])) return [];
    if (!is_array($files['name'])) throw new InvalidArgumentException('Please select the photos again.');
    $paths = [];
    try {
        foreach ($files['name'] as $index => $name) {
            $file = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) $file[$key] = $files[$key][$index] ?? null;
            if ($file['error'] === UPLOAD_ERR_NO_FILE) continue;
            $path = uploadFile($file, $directory);
            if (!$path) throw new InvalidArgumentException('A photo could not be uploaded. Choose JPG, PNG, GIF, or WebP images up to 5 MB, then select your photos again.');
            $paths[] = $path;
        }
    } catch (Throwable $error) {
        foreach ($paths as $path) @unlink(UPLOADS_PATH . '/' . $path);
        throw $error;
    }
    return $paths;
}

/** A matching failure after commit must not undo or duplicate a saved report. */
function saveItemReport(string $type, int $userId, array $values, array $files = []): array
{
    if (!in_array($type, ['lost', 'found'], true)) throw new InvalidArgumentException('Invalid report type.');
    $table = $type === 'lost' ? 'lost_items' : 'found_items';
    $prefix = $type === 'lost' ? LOST_PREFIX : FOUND_PREFIX;
    $paths = [];
    $qrPath = null;
    db()->beginTransaction();
    try {
        $report = generateReportNumber($prefix, $table);
        $columns = 'report_number,user_id,category_id,location_id,item_name,brand,color,description,date_' . $type . ',time_' . $type . ',status';
        $params = [$report, $userId, $values['category_id'], $values['location_id'], $values['item_name'],
            $values['brand'] ?: null, $values['color'] ?: null, $values['description'] ?: null,
            $values['date_' . $type], $values['time_' . $type] ?: null, 'active'];
        if ($type === 'found') { $columns .= ',storage_location'; $params[] = $values['storage_location'] ?: null; }
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $id = (int)db()->insert("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})", $params);
        $paths = uploadReportPhotos($files, $table);
        foreach ($paths as $index => $path) {
            db()->insert('INSERT INTO item_photos (item_type,item_id,photo_path,is_primary) VALUES (?,?,?,?)', [$type, $id, $path, $index === 0 ? 1 : 0]);
        }
        if ($type === 'found') {
            $qrPath = generateQRCode($id, $report);
            if ($qrPath) db()->execute('UPDATE found_items SET qr_code_path=? WHERE id=?', [$qrPath, $id]);
        }
        auditLog($userId, $type === 'lost' ? 'CREATE_LOST_ITEM' : 'CREATE_FOUND_ITEM', $table, $id);
        db()->commit();
    } catch (Throwable $error) {
        if (db()->getConnection()->inTransaction()) db()->rollBack();
        foreach ($paths as $path) @unlink(UPLOADS_PATH . '/' . $path);
        if ($qrPath) @unlink(QR_CODES_PATH . '/' . basename($qrPath));
        throw $error;
    }
    $matched = true;
    try {
        if ($type === 'lost') MatchingEngine::runForLostItem($id);
        else MatchingEngine::runForFoundItem($id);
    } catch (Throwable $error) {
        $matched = false;
        error_log('Matching deferred for report ' . $report . ': ' . get_class($error));
    }
    return ['id' => $id, 'report_number' => $report, 'matching_complete' => $matched, 'qr_generated' => (bool)$qrPath];
}
