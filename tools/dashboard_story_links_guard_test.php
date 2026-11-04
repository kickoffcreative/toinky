<?php
declare(strict_types=1);
// Guard test: the signed-in dashboard must connect the host picker to
// the existing story pages. The dashboard story dropdown shows title
// and genre only, so a player choosing a story needs a path to its
// cover art and premise before hosting: an Explore link for the
// selected story (kept in step with the dropdown by script) and a
// Browse-all link to the Stories list. The detail page's Host action
// already returns a signed-in user to the dashboard with that story
// pre-selected, so the loop runs both ways. Static checks only; no
// database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$index = (string)file_get_contents($root . '/public/index.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$hostStart = strpos($index, '<div class="card" id="host-a-game">');
$hostCard = $hostStart !== false ? substr($index, $hostStart, 4000) : '';
$check($hostCard !== '' && str_contains($hostCard, 'id="host-plot"'), 'Host card still has the story dropdown');
$check(str_contains($hostCard, 'id="host-story-link"') && str_contains($hostCard, '?view=story'), 'Host card has an Explore link to the selected story detail page');
$check(str_contains($hostCard, 'href="?view=stories"') && str_contains($hostCard, 'Browse all stories'), 'Host card links to the full Stories list');
$check(str_contains($index, "hostStoryLink.href='?view=story&code='+encodeURIComponent(opt.value)"), 'Story dropdown change updates the Explore link to the selected story');
$check(str_contains($index, "./?host=") && str_contains($index, '#host-a-game'), 'Detail page Host action returns to the dashboard with the story pre-selected');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK dashboard story links guard\n";
