<?php
// One-time fix: Set middle card top margin to 0 for section 57 (Made for the Host)
// Run via browser: https://www.toinky.com/tools/fix_card_margin.php (admin only)
// Delete after use.
require_once __DIR__.'/../app/AuthService.php';
require_once __DIR__.'/../app/PageBuilderService.php';
AuthService::boot();
AuthService::requireAdmin();

$sectionId = 57;
$q = db()->prepare('SELECT rows_json FROM site_page_sections WHERE site_page_section_id=?');
$q->execute([$sectionId]);
$json = $q->fetchColumn();
if ($json === false) die('Section 57 not found');

$rows = json_decode((string)$json, true);
if (!is_array($rows)) die('Invalid rows_json');

$fixed = false;
// Row 0 is CONTAINER, child 2 is CARDS_ONLY with 3 columns
if (isset($rows[0]['children'][2]['columns'][1]) && is_array($rows[0]['children'][2]['columns'][1])) {
    $col = &$rows[0]['children'][2]['columns'][1];
    // Try all possible field names for top margin
    foreach (['margin_top_px', 'margin_top', 'column_margin_top_px', 'column_margin_top'] as $field) {
        if (array_key_exists($field, $col)) {
            $col[$field] = 0;
            $fixed = true;
            echo "Set $field=0\n";
        }
    }
    // Also ensure the base margin_top is 0 if no specific field found
    if (!$fixed) {
        $col['margin_top_px'] = 0;
        echo "Set margin_top_px=0 (new field)\n";
        $fixed = true;
    }
}

if ($fixed) {
    $normalized = PageBuilderService::normalizeRows($rows);
    db()->prepare('UPDATE site_page_sections SET rows_json=? WHERE site_page_section_id=?')
        ->execute([json_encode($normalized, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), $sectionId]);
    echo "Section 57 updated successfully.";
} else {
    echo "Could not find the middle card structure.";
}
