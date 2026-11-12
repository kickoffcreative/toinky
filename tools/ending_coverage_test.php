<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__).'/app/EndingCoverageAudit.php';

$checks = 0;
$check = static function(bool $pass, string $label) use (&$checks): void {
    if (!$pass) throw new RuntimeException($label);
    $checks++;
    echo "PASS {$label}\n";
};
$fragment = static fn(int $id, int $priority, string $section = 'RESULT', int $active = 1): array => [
    'resolution_fragment_id' => $id, 'priority' => $priority, 'section_code' => $section, 'active' => $active,
];
$condition = static fn(int $id, string $operator, ?string $value, string $key = 'STATUS', int $active = 1): array => [
    'resolution_fragment_id' => $id, 'state_key' => $key, 'operator' => $operator, 'compare_value' => $value, 'active' => $active,
];
$base = [
    'resolutions' => [$fragment(2, 20), $fragment(1, 10)],
    'resolution_conditions' => [$condition(1, 'EQ', 'OPEN'), $condition(2, 'EQ', 'CLOSED')],
    'state_definitions' => [['target_code' => 'STATUS', 'effect_scope' => 'WORLD_STATE',
        'value_type' => 'PRESET', 'allowed_values_text' => "OPEN\nCLOSED", 'active' => 1]],
];
$warnings = static fn(array $w): string => implode("\n", EndingCoverageAudit::warnings($w));
$check($warnings($base) === '', 'complete preset partition is clean, regardless of input order');
$w = $base; $w['resolution_conditions'] = [$condition(1, 'EQ', 'OPEN')];
$check($warnings($w) === '', 'last unconditional fallback covers remaining values');
$w['resolutions'][] = $fragment(3, 30);
$check(str_contains($warnings($w), '#3 is hidden by earlier unconditional fragment #2'), 'early fallback identifies unreachable later fragment');
$w = $base; $w['resolution_conditions'] = [$condition(1, 'EQ', 'OPEN'), $condition(2, 'EQ', 'OPEN')];
$text = $warnings($w);
$check(str_contains($text, 'preset(s): CLOSED'), 'uncovered allowed value is reported');
$check(str_contains($text, '#2 is shadowed'), 'duplicate conditions identify shadowed fragment');
$w = $base; $w['resolutions'] = [$fragment(2, 10), $fragment(1, 10)];
$w['resolution_conditions'] = [$condition(2, 'EQ', 'OPEN')];
$check(str_contains($warnings($w), '#2 is hidden by earlier unconditional fragment #1'), 'priority ties use fragment ID like runtime');
$w = $base; $w['resolution_conditions'][] = $condition(1, 'EQ', 'CLOSED');
$check(str_contains($warnings($w), '#1 matches no configured'), 'contradictory AND conditions are detected');
$w = $base; $w['resolution_conditions'] = [$condition(1, 'IN', ' OPEN, CLOSED '), $condition(2, 'EQ', 'CLOSED')];
$check(str_contains($warnings($w), '#2 is shadowed'), 'broad IN match shadows narrow later match');
$w = $base; $w['resolution_conditions'] = [$condition(1, 'NEQ', 'CLOSED'), $condition(2, 'NOT_IN', 'OPEN')];
$check($warnings($w) === '', 'NEQ and NOT_IN partition present presets');
$w = $base; $w['resolution_conditions'] = [$condition(1, 'EXISTS', null), $condition(2, 'NOT_EXISTS', null)];
$check(str_contains($warnings($w), '#2 matches no configured'), 'existence checks cover present values only');
$w = $base; $w['resolution_conditions'][1] = $condition(2, 'EQ', 'OTHER');
$check(str_contains($warnings($w), '#2 matches no configured'), 'out-of-domain condition is not treated as coverage');
$w = $base; $w['resolutions'][0]['active'] = 0;
$check(str_contains($warnings($w), 'preset(s): CLOSED'), 'inactive fragments do not cover values');
$w = $base; $w['resolution_conditions'][0]['active'] = 0;
$check(str_contains($warnings($w), '#2 is hidden'), 'inactive conditions make a fragment unconditional');
$w = $base; $w['state_definitions'][0]['allowed_values_text'] = "OPEN\r\nCLOSED\r\nOPEN\r\n";
$check($warnings($w) === '', 'preset whitespace and duplicates are normalized');
$w = $base; $w['resolutions'][] = $fragment(3, 1, 'ANOTHER_SECTION');
$check($warnings($w) === '', 'fallback in another section cannot shadow this section');
foreach (['NUMERIC', 'TEXT'] as $type) {
    $w = $base; $w['state_definitions'][0]['value_type'] = $type;
    $w['resolution_conditions'][1] = $condition(2, 'EQ', 'OPEN');
    $check($warnings($w) === '', "finite check skips {$type} values");
}
$w = $base; $w['state_definitions'][] = $w['state_definitions'][0];
$check($warnings($w) === '', 'ambiguous codes skip finite inference');
$w = $base; $w['resolution_conditions'][1] = $condition(2, 'EQ', 'CLOSED', 'SECOND_KEY');
$check($warnings($w) === '', 'mixed-key sections are not assumed independent');
$w = $base; $w['resolution_conditions'][1] = $condition(2, 'GTE', '4');
$check($warnings($w) === '', 'unsupported operators skip finite inference');
$w = $base; $w['state_definitions'][0]['effect_scope'] = 'LOCATION';
$check($warnings($w) === '', 'character-scoped values are not treated as global state');
$w = $base; $w['state_definitions'][0]['active'] = 0;
$check($warnings($w) === '', 'inactive definitions are not used for coverage');
$w = $base; $w['state_definitions'][0]['allowed_values_text'] = implode("\n", range(1, 257));
$check($warnings($w) === '', 'large preset domains skip finite inference');
$check($warnings([]) === '', 'empty workspace is safe');

// Exercise the actual review-package integration without a database. Both
// draft audit() and pinned auditVersion() delegate to this same pure path.
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';
$workspace = $base;
foreach (['characters', 'decisions', 'locations', 'events', 'options', 'opportunities',
    'effects', 'option_conditions', 'dynamic_conditions', 'dynamic_options',
    'dynamic_effects', 'media'] as $key) $workspace[$key] = [];
$workspace['state_definitions'][0] += ['label_text' => 'Status', 'default_numeric_value' => null];
foreach ($workspace['resolutions'] as &$row) $row['fragment_text'] = 'An authored ending.';
unset($row);
$workspace['resolution_conditions'] = [$condition(1, 'EQ', 'OPEN'), $condition(2, 'EQ', 'OPEN')];
foreach ($workspace['resolution_conditions'] as &$row) $row += ['section_code' => 'RESULT', 'sort_order' => 0];
unset($row);
$before = $workspace;
$audit = new ReflectionMethod(StoryAuthoringService::class, 'auditWorkspace');
$result = $audit->invoke(null, ['scenario_text' => str_repeat('A scenario. ', 10),
    'title' => 'Fixture', 'code' => 'FIXTURE', 'workflow_status' => 'DRAFT', 'genre' => 'Drama'], $workspace);
$check(str_contains(implode("\n", $result['warnings']), 'preset(s): CLOSED'), 'authoring audit includes coverage warning');
$check(!str_contains(implode("\n", $result['errors']), 'preset(s): CLOSED'), 'coverage is advisory, not a publication error');
$check(str_contains($result['text'], 'WARNING: Ending RESULT has no matching fragment'), 'review package displays the diagnostic');
$check($workspace === $before, 'audit leaves authored content unchanged');
echo "Ending coverage: {$checks} checks passed.\n";
