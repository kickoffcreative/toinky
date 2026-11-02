<?php
declare(strict_types=1);
// Guard test: the Now card chapter heading must come from the latest
// logged timeline beat, not from the current clock. The clock crosses a
// phase threshold (18/42/72/92% of the run) before the next event logs,
// so a clock-derived heading runs ahead of the timeline's latest chapter
// (Games 4-7). Static checks only; no database needed.
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

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK now heading guard\n";
