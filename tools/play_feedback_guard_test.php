<?php
declare(strict_types=1);
// Guard test: Game 2 playtest feedback fixes (public/play.php, public/lobby.php).
// Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$play = (string)file_get_contents($root . '/public/play.php');
$lobby = (string)file_get_contents($root . '/public/lobby.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$nowStart = strpos($play, '<section class="card now-card">');
$storyStart = $nowStart !== false ? strpos($play, '<section class="card story-card">', $nowStart) : false;
$nowSeg = ($nowStart !== false && $storyStart !== false) ? substr($play, $nowStart, $storyStart - $nowStart) : '';
$dpos = strpos($nowSeg, 'open_decisions');
$ppos = strpos($nowSeg, 'now-event');
$ipos = strpos($nowSeg, "$nowImage['file_path']");
$check($dpos !== false && $ppos !== false && $ipos !== false && $dpos < $ppos && $ppos < $ipos, 'Now card orders decisions before prose before image');
$check(str_contains($play, '#game-live .now-card p{text-transform:none!important}'), 'Now card prose is never all-caps');
$check(substr_count($play, 'played automatically') >= 2, 'People lists label automatic characters');
$check(str_contains($play, 'Choose a rating'), 'rating no longer defaults to 5/5');
$check(str_contains($lobby, 'Played automatically, so not selectable here'), 'lobby names its automatic characters');
$check(str_contains($lobby, 'Claiming') && str_contains($lobby, 'location.reload()'), 'claim clicks give feedback and reload on refresh failure');

if ($failures) {
    echo 'FAILURES: ' . implode('; ', $failures) . "\n";
    exit(1);
}
echo "play_feedback_guard_test: all checks passed.\n";
