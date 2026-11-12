<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once dirname(__DIR__).'/app/StoryAuthoringService.php';
set_error_handler(static function(int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$audit = new ReflectionMethod(StoryAuthoringService::class, 'auditManagedReferences');
$errors = static function(array $w) use ($audit): array {
    [$errors, $warnings] = $audit->invoke(null, $w, []);
    return array_merge($errors, $warnings);
};
$count = 0;
$check = static function(bool $pass, string $label) use (&$count): void {
    if (!$pass) throw new RuntimeException($label);
    $count++; echo "PASS {$label}\n";
};
$base = ['state_definitions' => [
    ['effect_scope' => 'WORLD_STATE', 'target_code' => 'STATUS', 'value_type' => 'PRESET', 'allowed_values_text' => "READY\nDONE", 'active' => 1],
    ['effect_scope' => 'FACTOR', 'target_code' => 'TRUST', 'value_type' => 'NUMERIC', 'active' => 1],
], 'locations' => [['label_text' => 'Hall', 'location_code' => 'HALL', 'active' => 1]]];
$effect = ['effect_scope' => 'WORLD_STATE', 'target_code' => 'STATUS', 'set_value' => 'DONE', 'delta_value' => null, 'event_code' => 'BEAT', 'role_code' => 'ROLE'];
foreach (['event_effects', 'no_action_effects'] as $table) {
    $w = $base; $w[$table] = [$effect];
    $check($errors($w) === [], "{$table}: valid preset accepted");
    $w[$table][0]['set_value'] = 'UNKNOWN';
    $check(str_contains(implode(' ', $errors($w)), 'unavailable Preset Value'), "{$table}: invalid preset found");
    $w[$table][0]['target_code'] = 'MISSING';
    $check(str_contains(implode(' ', $errors($w)), 'undefined Story Value'), "{$table}: missing definition found");
    $w[$table][0]['active'] = 0;
    $check($errors($w) === [], "{$table}: inactive effect skipped");
    $w[$table] = [array_merge($effect, ['effect_scope' => 'FACTOR', 'target_code' => 'TRUST', 'set_value' => null, 'delta_value' => 0])];
    $check($errors($w) === [], "{$table}: zero numerical delta accepted");
    $w[$table][0]['delta_value'] = 'wrong';
    $check(str_contains(implode(' ', $errors($w)), 'numeric delta'), "{$table}: malformed delta found");
}
$condition = ['state_key' => 'STATUS', 'operator' => 'EQ', 'compare_value' => 'DONE',
    'event_code' => 'BEAT', 'role_code' => 'ROLE', 'option_code' => 'CHOICE', 'section_code' => 'RESULT'];
foreach (['option_conditions', 'event_effect_conditions', 'no_action_conditions', 'resolution_conditions'] as $table) {
    $w = $base; $w[$table] = [$condition];
    $check($errors($w) === [], "{$table}: valid preset condition accepted");
    $w[$table][0]['compare_value'] = 'UNKNOWN';
    $check(str_contains(implode(' ', $errors($w)), 'not an allowed Preset Value'), "{$table}: bad preset condition found");
    $w[$table][0] = array_merge($condition, ['state_key' => 'FACTOR:TRUST', 'operator' => 'GTE', 'compare_value' => '50']);
    $check($errors($w) === [], "{$table}: factor prefix resolves numerical definition");
    $w[$table][0]['compare_value'] = 'bad';
    $check(str_contains(implode(' ', $errors($w)), 'numerical values'), "{$table}: malformed factor comparison found");
    $w[$table][0]['state_key'] = 'FACTOR:STATUS';
    $check(str_contains(implode(' ', $errors($w)), 'undefined Story Value FACTOR|STATUS'), "{$table}: prefix never falls back to world state");
    $w[$table][0]['active'] = 0;
    $check($errors($w) === [], "{$table}: inactive condition skipped");
    $w[$table] = [array_merge($condition, ['state_key' => 'MISSING'])];
    $check(str_contains(implode(' ', $errors($w)), 'undefined Story Value'), "{$table}: missing condition definition found");
}
$w = $base; $w['event_effects'] = [array_merge($effect, ['effect_scope' => 'LOCATION', 'target_code' => 'ROLE', 'set_value' => 'Hall'])];
$check($errors($w) === [], 'event location targets character without requiring a value named ROLE');
$w['event_effects'][0]['set_value'] = 'Unknown place';
$check(str_contains(implode(' ', $errors($w)), 'unavailable story location'), 'unknown event destination found');
$w['event_effects'][0]['set_value'] = '';
$check(count($errors($w)) === 1, 'empty event destination found');
$w = $base; $w['no_action_conditions'] = [array_merge($condition, ['compare_value' => 'UNKNOWN'])];
$check(str_contains(implode(' ', $errors($w)), 'BEAT|ROLE'), 'missed-choice diagnostic identifies affected character and event');
$before = $w; $errors($w);
$check($w === $before, 'audit never mutates authored rows');
$check($errors([]) === [], 'empty workspace is safe');
$legacy = ['state_entity_properties' => [['entity_code' => 'PERSON', 'property_code' => 'knowledge',
    'value_type' => 'TEXT', 'allowed_values' => 'UNKNOWN,KNOWN']],
    'state_aliases' => [['entity_code' => 'PERSON', 'property_code' => 'knowledge', 'state_key' => 'LEGACY_KNOWLEDGE']]];
$w = ['option_conditions' => [array_merge($condition, ['state_key' => 'LEGACY_KNOWLEDGE', 'compare_value' => 'KNOWN'])]];
$check($audit->invoke(null, $w, $legacy)[0] === [], 'legacy entity alias is a defined condition source');
$w['option_conditions'][0]['compare_value'] = 'INVALID';
$check(count($audit->invoke(null, $w, $legacy)[1]) === 1, 'legacy alias still validates preset comparisons');
$w['option_conditions'][0] = array_merge($condition, ['state_key' => 'PERSON.knowledge', 'compare_value' => 'KNOWN']);
$check($audit->invoke(null, $w, $legacy)[0] === [], 'direct entity property reference is supported');
$w['option_conditions'][0]['state_key'] = 'LEGACY_KNOWLEDGE';
$result = $audit->invoke(null, $w, null);
$check($result[0] === [] && count($result[1]) === 1, 'pre-metadata snapshots report uncertainty without live fallback');
$check(count($audit->invoke(null, $w, [])[1]) === 1, 'complete snapshot with missing alias reports undefined reference');
$w['state_definitions'] = [['effect_scope' => 'WORLD_STATE', 'target_code' => 'LEGACY_KNOWLEDGE',
    'value_type' => 'PRESET', 'allowed_values_text' => "UNKNOWN\nKNOWN\nEXTENDED", 'active' => 1]];
$w['option_conditions'][0]['compare_value'] = 'EXTENDED';
$result = $audit->invoke(null, $w, $legacy);
$check($result[0] === [], 'managed definition retains existing authoring precedence');
$check(count($result[1]) === 1 && str_contains($result[1][0], 'differs from its entity-state metadata'), 'different managed and legacy domains produce a compatibility warning');
$w = $base; $w['option_conditions'] = [array_merge($condition, ['compare_value' => 'BAD'])];
$result = $audit->invoke(null, $w, []);
$check($result[0] === [] && count($result[1]) === 1, 'newly covered condition defects are advisory for legacy compatibility');
$w['resolution_conditions'] = $w['option_conditions']; unset($w['option_conditions']);
$check(count($audit->invoke(null, $w, [])[0]) === 1, 'existing ending validation remains a hard error');
echo "Story value audit: {$count} checks passed.\n";
