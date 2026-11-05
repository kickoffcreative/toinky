<?php
declare(strict_types=1);
// Guard test: discovery before configuration (first-impressions work,
// Nov 2026). The signed-in dashboard must lead with stories, not
// hosting mechanics: an unfinished game gets a Continue playing action
// above both, then story cards, then the host form. Copy is exact —
// welcome, free-first, occasion and choice lines are Randi-approved
// wording, not paraphrase. The free-first eligibility check must run
// BEFORE balance(), because balance() materialises the free-first grant
// and freeFirstAvailable() reads false afterwards. Cast previews mirror
// the lobby: mystery characters stay concealed, NPCs are labelled
// "Played automatically.", and only the public description is shown.
// Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$index = (string)file_get_contents($root . '/public/index.php');
$play = (string)file_get_contents($root . '/public/play.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$check(str_contains($index, '<h1>Who will you become?</h1>'), 'Welcome heading is "Who will you become?"');
$check(str_contains($index, 'Choose a story, gather your people, and step into a world of secrets and choices.'), 'Welcome subheading is the approved adventure line');
$check(!str_contains($index, 'Your stories are waiting.'), 'Old dashboard welcome is gone');
$check(str_contains($index, 'if($isPublicSite||$user){$publicStories='), 'Public story list loads for signed-in users too');
$check(str_contains($index, 'foreach($games as$g)') && str_contains($index, "['LOBBY','RUNNING']"), 'Continue playing picks the newest unfinished game');
$check(str_contains($index, '$continueUrl=$continueGame?') && str_contains($index, 'lobby.php?game=') && str_contains($index, 'play.php?game='), 'Continue playing links to the lobby or the play page by status');
$check(strpos($index, '<h2>Continue playing</h2>') !== false && strpos($index, '<h2>Continue playing</h2>') < strpos($index, '<h2>Choose a story</h2>') && strpos($index, '<h2>Choose a story</h2>') < strpos($index, 'id="host-a-game"'), 'Order is Continue playing, then story cards, then hosting');
$check(str_contains($index, "render_story_cards_row();"), 'Dashboard renders the shared story cards row');

$hostStart = strpos($index, '<div class="card" id="host-a-game">');
$hostCard = $hostStart !== false ? substr($index, $hostStart, 6000) : '';
$check($hostCard !== '' && str_contains($hostCard, 'Your first story night is free.'), 'Host card shows the free-first line when eligible');
$check(strpos($hostCard, 'freeFirstAvailable') !== false && strpos($hostCard, 'freeFirstAvailable') < strpos($hostCard, 'CommerceService::balance('), 'Host card reads free-first eligibility before balance()');
$check(str_contains($hostCard, 'One occasion covers this game.'), 'Host card explains the occasion in plain language');
$check(str_contains($hostCard, 'Create lobby') && !str_contains($hostCard, 'Start story'), 'Hosting action is "Create lobby", not "Start story"');
$check(str_contains($hostCard, 'id="host-duration-label"') && str_contains($hostCard, 'syncHostDurationLabel'), 'Selected duration is shown beside the Create lobby button');
$check(strpos($index, '$buyFreeFirst') !== false && strpos($index, '$buyFreeFirst') < strpos($index, '$buyBalance='), 'Buy page reads free-first eligibility before balance()');

$check(str_contains($index, "'FORGOTTEN_SHORE'=>'Eerie · Suspenseful'") && str_contains($index, "'A_NUT_TO_CRACK'=>'Absurd · Mischievous'"), 'Mood test labels exist for Whispers and Nut');
$check(str_contains($index, '/* Editorial mood test') && !str_contains($index, '// Editorial mood test'), 'Mood comment is a block comment, so it cannot swallow the code after it on the same line');
$check(str_contains($index, 'Your choices—and the moments you let pass—shape how the story unfolds.'), 'Detail page carries the consequential-choices line');
$detailStart = strpos($index, '<div class="type-public-intro">');
$detail = $detailStart !== false ? substr($index, $detailStart, 9000) : '';
$check(str_contains($detail, "FROM plot_roles WHERE plot_id=? AND active=1"), 'Cast preview reads the story character records');
$check(str_contains($detail, 'mystery_character') && str_contains($detail, 'Mystery Character'), 'Cast preview conceals mystery characters');
$check(str_contains($detail, 'Played automatically.'), 'Cast preview labels visible NPCs "Played automatically."');
$check(str_contains($detail, 'public_description') && !str_contains($detail, 'goal_text') && !str_contains($detail, 'fear_text') && !str_contains($detail, 'private_knowledge'), 'Cast preview shows only the public description');
$check(str_contains($detail, 'characterImagesForPlot'), 'Cast preview uses the shared character portraits');

$check(str_contains($play, 'What would happen if you chose differently?'), 'Finished game asks the return question');
$check(str_contains($play, 'Play this story again') && str_contains($play, '#host-a-game'), 'Finished game links back to hosting with the story pre-selected');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK first impressions guard\n";
