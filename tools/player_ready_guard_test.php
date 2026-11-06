<?php
declare(strict_types=1);
// Guard test: player-ready fixes (Nov 6, 2026) — stat-delta story moment,
// tick-before-choice, live-refresh resilience, standings stat label, and
// timeline event-second keying (the public half is also covered
// behaviourally by recap_service_guard_test.php).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);

$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$playSrc = (string)file_get_contents($root . '/public/play.php');
$svcSrc = (string)file_get_contents($root . '/app/RecapService.php');
$check($playSrc !== '' && $svcSrc !== '', 'play.php and RecapService.php are readable');

/* Fix 1: the Story State card shows when each factor last changed. */
$check(str_contains($playSrc, 'SELECT factor_code,old_value,new_value,event_second FROM simulation_factor_history'),
    'factor query selects event_second');
$check(str_contains($playSrc, 'ORDER BY event_second DESC,factor_history_id DESC'),
    'factor query breaks ties by factor_history_id');
$check(str_contains($playSrc, "story_moment(\$displaySecond((int)(\$fc['event_second']??0)),\$playDuration)"),
    'factor delta renders its story moment');

/* Fix 3: tick before choose; refresh retries and refreshes on focus. */
$check(str_contains($playSrc, "if(\$action==='decide'){try{LobbyService::tick(\$gid);}catch(Throwable \$e){}"),
    'decide handler ticks the game before applying the choice');
$check(str_contains($playSrc, 'liveFailures=0') && str_contains($playSrc, 'setTimeout(refreshLiveGame,1000)'),
    'live refresh retries after a failed fetch instead of silently skipping');
$check(str_contains($playSrc, "window.addEventListener('focus'"),
    'live refresh runs on window focus as well as visibility change');
$check(!str_contains($playSrc, 'catch(error){}'),
    'no silent empty catch remains in the live refresh');

/* Fix 4: the standings card names what it counts. */
$check(str_contains($playSrc, 'SELECT c.name,cs.current_value,cs.label FROM character_stats'),
    'standings query selects the stat label');
$check(str_contains($playSrc, '$standingsStatLabel') && str_contains($playSrc, '(<?=e($standingsStatLabel)?>)'),
    'standings title renders the stat label');

/* Fix 2 (source half): world and personal rows key to the causing event
   second; the public half is covered behaviourally by
   recap_service_guard_test.php. */
$check(str_contains($svcSrc, '$second = $causeSecond((int)$w[\'event_second\'], (int)($w[\'caused_by_event_id\'] ?? 0));'),
    'timeline keys world rows to the causing event second');
$check(str_contains($svcSrc, '$causeSecond((int)($w[\'event_second\'] ?? 0), (int)($w[\'caused_by_event_id\'] ?? 0))'),
    'timeline keys the decision-outcome index to the causing event second');
$check(str_contains($svcSrc, "if ((\$type === 'DECISION' || \$type === 'NO_ACTION' || \$type === 'LOCATION') && (int)(\$h['event_id'] ?? 0) > 0) \$second = \$causeSecond(\$second, (int)\$h['event_id']);"),
    'timeline keys personal decision, no-action and location rows to the causing event second');

if ($failures) {
    echo 'FAILURES: ' . implode('; ', $failures) . "\n";
    exit(1);
}
echo "player_ready_guard_test: all checks passed.\n";
