<?php
declare(strict_types=1);
// Guard test: effect_scope ENUMs must cover every scope the save
// handlers and the writer UI offer. plot_world_event_effects was
// created with ENUM('WORLD_STATE','FACTOR') only, while
// saveWorldEventEffect and the studio form accept seven scopes; on a
// non-strict server an out-of-range ENUM value truncates silently to
// '' with no error, so a LOCATION world-event effect saved with
// "Event effect saved." yet reloaded as WORLD_STATE (Whispers
// FINAL_STATE -> At Sea, Nov 2026). The same gap existed in
// plot_option_effects (missing WORLD_STATE, GOAL, FEAR) and
// plot_no_action_effects (missing GOAL, FEAR). Migration 139 expands
// all three ENUMs; this test keeps the column, handler, UI and docs
// in agreement. Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/database/migrations/139_expand_effect_scope_enums.sql');
$authoring = (string)file_get_contents($root . '/app/StoryAuthoringService.php');
$writer = (string)file_get_contents($root . '/public/writer.php');
$schema = (string)file_get_contents($root . '/docs/SCHEMA.md');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

// Expected scope sets, matching the handler whitelists exactly.
$worldEvent = ['WORLD_STATE', 'FACTOR', 'LOCATION', 'STAT', 'KNOWLEDGE', 'GOAL', 'FEAR'];
$option = ['FACTOR', 'STAT', 'LOCATION', 'KNOWLEDGE', 'RELATIONSHIP', 'WORLD_STATE', 'GOAL', 'FEAR'];
$noAction = ['FACTOR', 'STAT', 'LOCATION', 'KNOWLEDGE', 'WORLD_STATE', 'GOAL', 'FEAR'];

// The migration expands all three ENUMs, appending values so existing
// ENUM indexes are unchanged.
$check(str_contains($migration, "ALTER TABLE plot_world_event_effects MODIFY effect_scope ENUM('WORLD_STATE','FACTOR','LOCATION','STAT','KNOWLEDGE','GOAL','FEAR')"), 'migration 139 expands plot_world_event_effects ENUM');
$check(str_contains($migration, "ALTER TABLE plot_option_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR')"), 'migration 139 expands plot_option_effects ENUM');
$check(str_contains($migration, "ALTER TABLE plot_no_action_effects MODIFY effect_scope ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR')"), 'migration 139 expands plot_no_action_effects ENUM');
$check(str_contains($migration, "INSERT IGNORE INTO schema_migrations(migration) VALUES('139_expand_effect_scope_enums.sql')"), 'migration 139 registers in schema_migrations');

// Handler whitelists must offer exactly the scopes the columns now store.
$check(str_contains($authoring, "['WORLD_STATE','FACTOR','LOCATION','STAT','KNOWLEDGE','GOAL','FEAR']"), 'saveWorldEventEffect whitelist covers all seven scopes');
$check(str_contains($authoring, "['FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR']"), 'saveOptionEffect whitelist covers all eight scopes');
$check(str_contains($authoring, "['FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR']"), 'saveNoActionEffect whitelist covers all seven scopes');

// Writer UI scope lists must match the columns too.
$check(str_contains($writer, "['WORLD_STATE','FACTOR','LOCATION','STAT','KNOWLEDGE','GOAL','FEAR']"), 'writer event-effect form offers all seven scopes');
$check(str_contains($writer, "['WORLD_STATE','FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','GOAL','FEAR']"), 'writer option-effect form offers all eight scopes');

// SCHEMA.md must record the expanded ENUMs.
$check(str_contains($schema, "effect_scope` ENUM('WORLD_STATE','FACTOR','LOCATION','STAT','KNOWLEDGE','GOAL','FEAR')"), 'SCHEMA.md records expanded plot_world_event_effects ENUM');
$check(str_contains($schema, "effect_scope` ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','RELATIONSHIP','WORLD_STATE','GOAL','FEAR')"), 'SCHEMA.md records expanded plot_option_effects ENUM');
$check(str_contains($schema, "effect_scope` ENUM('FACTOR','STAT','LOCATION','KNOWLEDGE','WORLD_STATE','GOAL','FEAR')"), 'SCHEMA.md records expanded plot_no_action_effects ENUM');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK effect scope guard\n";
