<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';
set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$method = new ReflectionMethod(StoryAuthoringService::class, 'stateDefinitionCompatibilityProblems');
$problems = static fn(array $workspace, string $type = 'PRESET', ?string $allowed = 'READY', string $scope = 'WORLD_STATE'): array =>
    $method->invoke(null, $workspace, $scope, 'STATUS', $type, $allowed);
$count = 0;
$check = static function(bool $pass, string $label) use (&$count): void {
    if (!$pass) throw new RuntimeException($label);
    $count++;
    echo "PASS {$label}\n";
};
$effect = ['effect_scope' => 'WORLD_STATE', 'target_code' => 'STATUS',
    'delta_value' => null, 'set_value' => 'REMOVED', 'event_code' => 'BEAT',
    'role_code' => 'ROLE', 'option_code' => 'CHOICE', 'opportunity_code' => 'CHANCE'];
foreach (['effects' => 'OPTION BEAT|ROLE|CHOICE', 'dynamic_effects' => 'OPTION CHANCE|CHOICE',
    'event_effects' => 'EVENT BEAT', 'no_action_effects' => 'NO ACTION BEAT|ROLE'] as $table => $label) {
    $workspace = [$table => [$effect]];
    $check(str_contains(implode(' ', $problems($workspace)), $label.' has'), "{$table}: removed preset names affected use");
    $check($problems($workspace, 'PRESET', "READY\nREMOVED") === [], "{$table}: retained preset is safe");
    $check(str_contains(implode(' ', $problems($workspace, 'NUMERIC', null)), 'needs Change By'), "{$table}: preset-to-number change is blocked");
    $numeric = $effect; $numeric['set_value'] = null; $numeric['delta_value'] = 2;
    $check(str_contains(implode(' ', $problems([$table => [$numeric]])), 'needs Set To'), "{$table}: number-to-preset change is blocked");
    $numeric['delta_value'] = 0;
    $check($problems([$table => [$numeric]], 'NUMERIC', null) === [], "{$table}: zero delta is valid");
    $inactive = $effect; $inactive['active'] = 0;
    $check($problems([$table => [$inactive]]) === [], "{$table}: inactive effect is ignored");
    $other = $effect; $other['effect_scope'] = 'STAT';
    $check($problems([$table => [$other]]) === [], "{$table}: same code in another scope is unrelated");
}
$condition = ['state_key' => 'STATUS', 'source_type' => 'WORLD_STATE', 'source_key' => 'STATUS',
    'operator' => 'EQ', 'compare_value' => 'REMOVED', 'event_code' => 'BEAT',
    'role_code' => 'ROLE', 'option_code' => 'CHOICE', 'opportunity_code' => 'CHANCE', 'section_code' => 'RESULT'];
foreach (['dynamic_conditions' => 'CONDITION CHANCE', 'resolution_conditions' => 'ENDING RESULT',
    'option_conditions' => 'OPTION CONDITION CHOICE', 'event_effect_conditions' => 'EVENT CONDITION BEAT',
    'no_action_conditions' => 'NO ACTION CONDITION BEAT|ROLE'] as $table => $label) {
    $check(str_contains(implode(' ', $problems([$table => [$condition]])), $label), "{$table}: removed preset names affected condition");
    $check($problems([$table => [$condition]], 'PRESET', "READY\nREMOVED") === [], "{$table}: retained comparison is safe");
    $comparison = $condition; $comparison['operator'] = 'GTE'; $comparison['compare_value'] = '3';
    $check(str_contains(implode(' ', $problems([$table => [$comparison]])), 'only available for Numerical'), "{$table}: numeric comparison cannot become preset");
    $check($problems([$table => [$comparison]], 'NUMERIC', null) === [], "{$table}: valid numeric comparison stays valid");
    $inactive = $condition; $inactive['active'] = 0;
    $check($problems([$table => [$inactive]]) === [], "{$table}: inactive condition is ignored");
    $exists = $condition; $exists['operator'] = 'EXISTS'; $exists['compare_value'] = null;
    $check($problems([$table => [$exists]]) === [], "{$table}: existence condition survives type change");
}
foreach (['option_conditions', 'event_effect_conditions', 'no_action_conditions', 'resolution_conditions'] as $table) {
    $prefixed = $condition; $prefixed['state_key'] = 'FACTOR:STATUS';
    $check(count($problems([$table => [$prefixed]], 'PRESET', 'READY', 'FACTOR')) === 1, "{$table}: FACTOR-prefixed reference checked");
    $check($problems([$table => [$prefixed]]) === [], "{$table}: factor prefix does not reference world state");
}
$list = $condition; $list['operator'] = 'IN'; $list['compare_value'] = 'READY, REMOVED';
$check(count($problems(['event_effect_conditions' => [$list]])) === 1, 'every IN member must remain available');
$other = $condition; $other['source_type'] = 'STAT';
$check($problems(['dynamic_conditions' => [$other]]) === [], 'dynamic conditions respect scope');
$other = $condition; $other['state_key'] = 'OTHER';
$check($problems(['no_action_conditions' => [$other]]) === [], 'unrelated state key is ignored');
$workspace = ['event_effects' => [$effect], 'no_action_conditions' => [$condition]];
$before = $workspace;
$check(count($problems($workspace)) === 2, 'all affected uses are reported together');
$check($workspace === $before, 'dependency check never mutates story content');
$check($problems([]) === [], 'empty workspace is safe');
echo "Story value dependencies: {$count} checks passed.\n";
