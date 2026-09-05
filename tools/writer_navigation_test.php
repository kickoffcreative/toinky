<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

require_once dirname(__DIR__) . '/public/_writer_navigation.php';

$root = dirname(__DIR__);
$writer = file_get_contents($root . '/public/writer.php');
if ($writer === false) {
    throw new RuntimeException('Could not read public/writer.php.');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$tabs = writer_tabs(true);
$assert($tabs['how_it_works'] === 'How It Works', 'How It Works tab is missing.');
$assert($tabs['timeline'] === 'Events', 'Timeline must be labeled Events.');
$assert($tabs['map'] === 'Map, Dependencies, Opportunities', 'Story Map label is missing.');
$assert($tabs['media'] === 'Media' && !isset($tabs['artwork']), 'Artwork must be a Media sub-tab.');
$assert(writer_subtab_items('how_it_works') === ['guide' => 'How Story Writer Works', 'identifiers' => 'Identifier Codes'], 'How It Works sub-tabs are incorrect.');
$assert(writer_subtab_items('overview')['answers'] === 'Guided Answers', 'Guided Answers sub-tab is missing.');
$assert(writer_subtab_items('overview')['values'] === 'Story Values', 'Story Values sub-tab is missing.');
$assert(writer_subtab_items('media') === ['library' => 'Media Library', 'artwork' => 'Artwork'], 'Media sub-tabs are incorrect.');
$assert(writer_route('artwork', '', false) === ['media', 'artwork'], 'Legacy Artwork links must route to Media > Artwork.');
$assert(writer_route('overview', 'sections', false) === ['endings', 'sections'], 'Legacy Ending Sections links must route to Endings.');

$assert(str_contains($writer, "<?php if(\$subtab==='answers')"), 'Guided Answers content is not rendered by sub-tab.');
$assert(str_contains($writer, 'name="guided_answers[<?=$index?>]"'), 'Guided Answers fields are missing.');
$assert(str_contains($writer, '<?=writer_filter()?>'), 'Shared filter control is missing.');
$assert(str_contains($writer, '<?=writer_quick_values($plotId,$tab,$w,$codeUsage)?><?=writer_filter()?>'), 'Quick values and filter controls are not adjacent.');
$assert(str_contains($writer, '<h2>Events</h2>'), 'Events heading is missing.');
$assert(str_contains($writer, '<h2>Endings</h2>'), 'Endings heading is missing.');
$assert(str_contains($writer, '<h2>Media</h2>'), 'Media heading is missing.');
$assert(!str_contains($writer, "'sections'=>'Ending Sections'"), 'Ending Sections must not remain under Story Framework.');

$controlOrder = static function (string $source, string $scope, string $quick, string $filter) use ($assert): void {
    $start = strpos($source, $scope);
    $assert($start !== false, 'Missing panel: ' . $scope);
    $quickPosition = strpos($source, $quick, $start);
    $filterPosition = strpos($source, $filter, $start);
    $assert($quickPosition !== false && $filterPosition !== false && $quickPosition < $filterPosition, 'Quick values must precede filter: ' . $scope);
};

$quick = '<?=writer_quick_values($plotId,$tab,$w,$codeUsage)?>';
$filter = '<?=writer_filter()?>';
foreach (['<h2>Characters</h2>', '<h2>Events</h2>', '<h2>Decisions, Options, and Effects</h2>', '<h3>Story Map and Dependencies</h3>', '<h3>Conditional Opportunities</h3>', '<p class="help">Write immediate consequences', '<h2>Media</h2>'] as $scope) {
    $controlOrder($writer, $scope, $quick, $filter);
}

echo "PASS: Story Writer tabs, moved sections, empty Guided Answers, and shared controls are covered.\n";
