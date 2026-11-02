<?php
declare(strict_types=1);
// Guard test: Task 5 Phase 4 state-machine metadata pinning.
// The metadata tables (plot_entities, plot_entity_properties,
// plot_property_transitions, plot_state_aliases) are pinned in a
// separate snapshot section, 'state_metadata', NOT in the workspace
// 'content' array — so already-published versions keep their exact
// content checksum, drift behaviour, and auditVersion completeness
// check. A pinned game whose snapshot carries the section reads its
// metadata only from the snapshot; a pre-Phase 4 snapshot keeps the
// live-table behaviour it always had (the stateMetadata accessor is
// deliberately not fail-closed, unlike content()).
// Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$authoring = (string)file_get_contents($root . '/app/StoryAuthoringService.php');
$content = (string)file_get_contents($root . '/app/SimulationContent.php');
$state = (string)file_get_contents($root . '/app/GenericStateService.php');
$service = (string)file_get_contents($root . '/app/SimulationService.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

// Snapshot writer: submit() carries the new section, built by a
// dedicated method over the state-machine metadata tables: entity
// state, triggered transitions, consequence rules, motive rules.
$check(str_contains($authoring, "'state_metadata'=>self::stateMetadata(\$plotId)"), 'submit() writes the state_metadata snapshot section');
$check(str_contains($authoring, 'public static function stateMetadata(int $plotId)'), 'StoryAuthoringService::stateMetadata exists');
foreach (['plot_entities', 'plot_entity_properties', 'plot_property_transitions', 'plot_state_aliases', 'plot_triggered_transitions', 'plot_triggered_transition_conditions', 'plot_triggered_transition_effects', 'plot_consequence_rules', 'plot_consequence_conditions', 'plot_consequence_effects', 'plot_motive_lifecycle_rules', 'plot_motive_lifecycle_conditions'] as $table) {
    $check(str_contains($authoring, $table), 'stateMetadata reads ' . $table);
}
// Preservation: workspace() itself must not gain the new sections, or
// every old snapshot would fail drift and auditVersion completeness.
$wsStart = strpos($authoring, 'public static function workspace');
$wsEnd = $wsStart !== false ? strpos($authoring, 'public static function stableCodeUsageMap', $wsStart) : false;
$ws = ($wsStart !== false && $wsEnd !== false) ? substr($authoring, $wsStart, $wsEnd - $wsStart) : '';
$check($ws !== '' && !str_contains($ws, 'state_entities') && !str_contains($ws, 'state_aliases'), 'workspace() gains no state metadata sections');
// Preservation: the stored checksum still hashes content only.
$check(str_contains($service, "hash('sha256',json_encode(\$snapArr['content']"), 'content checksum still hashes content only');

// Snapshot reader: optional (not fail-closed) accessor with the four keys.
$check(str_contains($content, 'public static function stateMetadata(int $sid)'), 'SimulationContent::stateMetadata exists');
$check(str_contains($content, "is_array(\$snap['state_metadata']??null)?\$snap['state_metadata']:null"), 'stateMetadata returns null for pre-Phase 4 snapshots, not a throw');

// Runtime readers: snapshot first, live tables only on null.
$check(str_contains($state, 'private static function snapshotMetadata(int $sid)'), 'GenericStateService::snapshotMetadata exists');
$check(str_contains($state, "self::snapshotMetadata(\$sid)['state_property_transitions']??null"), 'setByKey checks snapshot transitions');
$check(str_contains($state, 'function transitionAllowed(int $propertyId,?string $old,string $new,?array $snapshotTransitions=null)'), 'transitionAllowed accepts snapshot transitions');
$check(str_contains($state, "if(\$snap!==null)"), 'initializePlotState prefers snapshot metadata');
$check(str_contains($state, "array_column(\$snap['state_aliases']??[],'state_key')"), 'initializePlotState takes alias keys from the snapshot');

// Runtime readers: the rule services prefer the snapshot section.
$triggered = (string)file_get_contents($root . '/app/TriggeredTransitionService.php');
$consequence = (string)file_get_contents($root . '/app/ConsequencePropagationService.php');
$sync = (string)file_get_contents($root . '/app/StateSyncService.php');
$check(str_contains($triggered, "SimulationContent::stateMetadata(\$sid)"), 'triggered transitions read snapshot metadata');
$check(str_contains($triggered, "\$snap['triggered_transition_conditions']??null"), 'triggered conditions prefer snapshot');
$check(str_contains($consequence, "SimulationContent::stateMetadata(\$sid)"), 'consequence rules read snapshot metadata');
$check(str_contains($consequence, "\$snap['consequence_conditions']??null"), 'consequence conditions prefer snapshot');
$check(str_contains($sync, "SimulationContent::stateMetadata(\$sid)"), 'motive rules read snapshot metadata');
$check(str_contains($sync, "\$snap['motive_lifecycle_conditions']??null"), 'motive conditions prefer snapshot');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK state metadata guard\n";
