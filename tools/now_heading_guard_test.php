<?php
declare(strict_types=1);
// Guard test: the Now card chapter heading AND prose must come from the
// latest logged timeline beat, not from the current clock, and not from
// two different sources. The clock crosses a phase threshold (18/42/72/92%
// of the run) before the next event logs, so a clock-derived heading runs
// ahead of the timeline's latest chapter (Games 4-7). And the old prose
// source (the last plot event) lags the heading whenever a non-plot
// timeline beat crosses a threshold first, so the heading read a later
// chapter than the prose underneath it (Games 8, 9, 11). Both now derive
// from the same last timeline row. Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$play = (string)file_get_contents($root . '/public/play.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$nowStart = strpos($play, '<section class="card now-card">');
$nowHead = $nowStart !== false ? substr($play, $nowStart, 600) : '';
$check($nowHead !== '' && str_contains($nowHead, 'story_phase_label($nowPhase)'), 'Now heading uses the latest-timeline-beat phase');
$check(str_contains($play, "array_key_last(\$timeline)") && str_contains($play, "'EXPOSITION'"), 'Now phase derives from the last timeline row, Exposition fallback');
$check(!str_contains($nowHead, "story_phase_for_second((int)\$sim['elapsed_seconds']"), 'Now heading is not clock-derived');
$nowSection = $nowStart !== false ? substr($play, $nowStart, 2000) : '';
$check(str_contains($nowSection, "\$nowItem=\$nowRow['public'][0]??\$nowRow['personal'][0]??null"), 'Now prose derives from the last timeline row, not the last plot event');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK now heading guard\n";
