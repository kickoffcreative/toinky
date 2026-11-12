<?php
declare(strict_types=1);
// Guard test: RecapService helper parity (Lab player-view refactor, Oct 31,
// 2026). The play page template still calls the global helper functions
// (story_moment, game_time, story_phase_label, player_visible_text,
// player_pov_narrative), so play.php keeps them; RecapService carries
// byte-identical private copies so Lab/CLI rendering never depends on
// play.php loading. This test fails if the two copies drift apart.
// It does not prove the builder move itself — that was proven once at
// commit time by a mechanical extraction-and-diff script (all 12 helpers
// identical, all 6 builder blocks contained, play.php prefix/suffix
// untouched) — but it guards the ongoing drift risk: edit one copy and
// this test forces you to edit the other.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
require_once $root . '/app/bootstrap.php';
require_once $root . '/app/RecapService.php';

$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$phpSqInterpret = static function (string $s): string {
    $out = '';
    $i = 0;
    $n = strlen($s);
    while ($i < $n) {
        $c = $s[$i];
        if ($c === '\\' && $i + 1 < $n && ($s[$i + 1] === '\\' || $s[$i + 1] === "'")) {
            $out .= $s[$i + 1];
            $i += 2;
        } else {
            $out .= $c;
            $i++;
        }
    }
    return $out;
};
$interpSq = static function (string $body) use ($phpSqInterpret): string {
    $out = '';
    $i = 0;
    $n = strlen($body);
    while ($i < $n) {
        if ($body[$i] === "'") {
            $j = $i + 1;
            $buf = '';
            while ($j < $n) {
                if ($body[$j] === '\\' && $j + 1 < $n && ($body[$j + 1] === "'" || $body[$j + 1] === "\\")) {
                    $buf .= $body[$j] . $body[$j + 1];
                    $j += 2;
                } elseif ($body[$j] === "'") {
                    break;
                } else {
                    $buf .= $body[$j];
                    $j++;
                }
            }
            $out .= "\x00" . $phpSqInterpret($buf) . "\x00";
            $i = $j + 1;
        } else {
            $out .= $body[$i];
            $i++;
        }
    }
    return $out;
};
$norm = static function (string $body) use ($interpSq): string {
    $body = preg_replace('/\/\*.*?\*\//s', '', $body) ?? $body;
    $body = $interpSq($body);
    return (string)preg_replace('/\s+/', '', $body);
};
$fnBody = static function (string $text, string $name, bool $method) {
    $pat = ($method ? '/private\s+static\s+function\s+' : '/function\s+') . preg_quote($name, '/') . '\s*\(/';
    if (!preg_match($pat, $text, $m, PREG_OFFSET_CAPTURE)) return null;
    $i = strpos($text, '{', (int)$m[0][1]);
    if ($i === false) return null;
    $depth = 0;
    $start = $i;
    $n = strlen($text);
    while ($i < $n) {
        if ($text[$i] === '{') $depth++;
        elseif ($text[$i] === '}') {
            $depth--;
            if ($depth === 0) return substr($text, $start, $i - $start + 1);
        }
        $i++;
    }
    return null;
};

$playSrc = (string)file_get_contents($root . '/public/play.php');
$svcSrc = (string)file_get_contents($root . '/app/RecapService.php');
$check($playSrc !== '' && $svcSrc !== '', 'play.php and RecapService.php are readable');

$helpers = ['game_time', 'ordinal_word', 'story_moment', 'story_phase_label',
    'story_phase_for_second', 'personal_experience_label',
    'timeline_fingerprint', 'player_visible_text',
    'missed_opportunity_text', 'character_pronouns',
    'observer_narrative', 'player_pov_narrative'];
foreach ($helpers as $h) {
    $global = $fnBody($playSrc, $h, false);
    $private = $fnBody($svcSrc, $h, true);
    $same = $global !== null && $private !== null
        && $norm($global) === str_replace('self::', '', $norm($private));
    $check($same, 'helper ' . $h . ' matches between play.php and RecapService');
}

$check(str_contains($playSrc, 'RecapService::timeline($snap,$character,$storyDuration)'),
    'play.php renders through RecapService::timeline');
$check(method_exists('RecapService', 'timeline')
    && method_exists('RecapService', 'renderText')
    && method_exists('RecapService', 'sidebar'),
    'RecapService exposes timeline, renderText, and sidebar');
$check(RecapService::LAB_TITLES === ['Simulation Lab AI Control', 'AI Options Considered', 'AI Reasoner Fallback'],
    'RecapService filters exactly the three Lab-injected INTERACTION titles');

/* Player-ready fix (Nov 6, 2026): decision outcomes key to their causing
   event's second, and public entries merge only for the same actor and action.
   timeline() with a null character needs no database: the location and
   recap-transition queries are failure-tolerant, and the missed-option
   query only runs for a character. */
$testSnap = [
    'simulation' => ['simulation_id' => 0, 'plot_id' => 0],
    'characters' => [
        ['character_id' => 2, 'name' => 'Vivian'],
    ],
    'world' => [
        ['event_id' => 100, 'event_second' => 720, 'event_code' => 'BEACH_GIVES_UP', 'actor_character_id' => 0, 'actor_name' => '', 'headline' => 'The Beach Gives Up Its Dead', 'event_text' => 'The beach gives up its dead.', 'caused_by_event_id' => null, 'phase' => 'RISING_ACTION'],
        ['event_id' => 101, 'event_second' => 1020, 'event_code' => 'PLAYER_ACTION_1020_ab', 'actor_character_id' => 2, 'actor_name' => 'Vivian', 'headline' => 'Vivian: Choose the review', 'event_text' => 'Vivian chose the review. The room went quiet.', 'caused_by_event_id' => 100, 'phase' => 'RISING_ACTION'],
        ['event_id' => 102, 'event_second' => 1080, 'event_code' => 'PLAYER_ACTION_1080_cd', 'actor_character_id' => 2, 'actor_name' => 'Vivian', 'headline' => 'Vivian: Choose the delay', 'event_text' => 'Vivian chose the delay. The room waited.', 'caused_by_event_id' => 100, 'phase' => 'RISING_ACTION'],
        ['event_id' => 103, 'event_second' => 1080, 'event_code' => 'PLAYER_ACTION_1080_effect', 'actor_character_id' => 2, 'actor_name' => 'Vivian', 'headline' => 'Vivian: Choose the delay', 'event_text' => 'The pause gave everyone time to listen.', 'caused_by_event_id' => 100, 'phase' => 'RISING_ACTION'],
    ],
    'factors' => [],
];
$testTimeline = RecapService::timeline($testSnap, null, 1800);
$check(!isset($testTimeline[1020]) && !isset($testTimeline[1080]), 'timeline keys decision outcomes to the causing event second, not the apply second');
$check(isset($testTimeline[720]), 'timeline keeps the causing event beat');
$check(count($testTimeline[720]['public'] ?? []) === 3, 'different actions by the same actor retain separate entries');
$review = $testTimeline[720]['public'][1] ?? [];
$delay = $testTimeline[720]['public'][2] ?? [];
$check(($review['lead'] ?? '') === 'Vivian' && ($delay['lead'] ?? '') === 'Vivian', 'separate actions retain their actor lead');
$check(($review['headline'] ?? '') === 'Vivian: Choose the review' && ($delay['headline'] ?? '') === 'Vivian: Choose the delay', 'each result retains the heading of its own action');
$check(str_contains((string)($review['detail'] ?? ''), 'chose the review') && !str_contains((string)($review['detail'] ?? ''), 'chose the delay'), 'later choice cannot appear under the earlier choice heading');
$check(str_contains((string)($delay['detail'] ?? ''), 'chose the delay') && str_contains((string)($delay['detail'] ?? ''), 'time to listen'), 'same-action details still combine without a duplicate heading');

/* Opportunity-split fix (Nov 6, 2026): a decision caused by a dynamic
   opportunity points at the opportunity's PRIVATE "A New Opportunity
   Opens" row, which never reaches the snapshot, so timeline() resolves
   any cause id missing from the snapshot map with one query (any
   visibility) merged into the map. The query itself needs a live
   database, which this harness does not have, so the re-key is covered
   here by source assertions plus the no-database degradation check
   below; the live behaviour is verified by a game run. */
$check(str_contains($svcSrc, "SELECT event_id,event_second FROM simulation_events WHERE simulation_id=? AND event_id IN ("),
    'timeline resolves missing cause ids with one simulation_events query');
$check(str_contains($svcSrc, '$missingCauseIds[$cid] = true;')
    && str_contains($svcSrc, '$missingCauseIds[$hid] = true;'),
    'timeline collects missing cause ids from world rows and personal decision, no-action and location rows');
$check(str_contains($svcSrc, '$eventSecondById[(int)$crow[' . chr(39) . 'event_id' . chr(39) . ']] = (int)$crow[' . chr(39) . 'event_second' . chr(39) . '];')
    && str_contains($svcSrc, 'if ($sid > 0 && $missingCauseIds) {'),
    'timeline merges resolved cause seconds into the map only for a real simulation');
$mergePos = strpos($svcSrc, '$eventSecondById[(int)$crow[' . chr(39) . 'event_id' . chr(39) . ']');
$catchPos = $mergePos === false ? false : strpos($svcSrc, '} catch (Throwable $e) {}', $mergePos);
$causePos = $catchPos === false ? false : strpos($svcSrc, '$causeSecond = static function', $catchPos);
$check($mergePos !== false && $catchPos !== false && $causePos !== false && $catchPos < $causePos && ($causePos - $catchPos) < 60,
    'missing-cause resolution is failure-tolerant and runs before the cause-second closure');

/* Knowledge/consequence re-key (Nov 6, 2026): KNOWLEDGE ("Knowledge
   Gained") and CONSEQUENCE ("What Changed for You") rows carry the
   causing event id but kept their own (click) second, so they landed
   as standalone articles minutes after their beat (game 89: a
   KNOWLEDGE row at 23:43 after its 22:30 beat; game 87: a CONSEQUENCE
   row at 23:49 after its 23:00 beat). Both type lists must include
   them: the missing-cause collection (so an opportunity-caused row
   gets its PRIVATE cause id resolved) and the re-key itself. A
   behavioural case cannot run in this harness: timeline() with a
   character runs the unguarded missed-option query, and db() is
   MySQL-only, so no character call is possible without a live
   database; the live re-key is verified by a game run. */
$check(str_contains($svcSrc, "\$htype === 'KNOWLEDGE'") && str_contains($svcSrc, "\$htype === 'CONSEQUENCE'"),
    'timeline collects missing cause ids from knowledge and consequence rows too');
$check(str_contains($svcSrc, "\$type === 'KNOWLEDGE'") && str_contains($svcSrc, "\$type === 'CONSEQUENCE'"),
    'timeline re-keys knowledge and consequence rows to the causing event second');

/* Without a database the missing-cause query cannot run, so timeline()
   must still build and fall back to the row's own second instead of
   failing: an opportunity-style outcome whose cause row is absent from
   the snapshot degrades to snapshot-only behaviour. */
$noDbSnap = [
    'simulation' => ['simulation_id' => 0, 'plot_id' => 0],
    'characters' => [
        ['character_id' => 2, 'name' => 'Vivian'],
    ],
    'world' => [
        ['event_id' => 100, 'event_second' => 720, 'event_code' => 'BEACH_GIVES_UP', 'actor_character_id' => 0, 'actor_name' => '', 'headline' => 'The Beach Gives Up Its Dead', 'event_text' => 'The beach gives up its dead.', 'caused_by_event_id' => null, 'phase' => 'RISING_ACTION'],
        ['event_id' => 101, 'event_second' => 1020, 'event_code' => 'PLAYER_ACTION_1020_ab', 'actor_character_id' => 2, 'actor_name' => 'Vivian', 'headline' => 'Vivian: Choose the review', 'event_text' => 'Vivian chose the review. The room went quiet.', 'caused_by_event_id' => 999, 'phase' => 'RISING_ACTION'],
    ],
    'factors' => [],
];
$noDbTimeline = RecapService::timeline($noDbSnap, null, 1800);
$check(isset($noDbTimeline[720]) && isset($noDbTimeline[1020]), 'timeline without a database still builds and falls back to the row second for an unresolvable cause');
$check(($noDbTimeline[1020]['public'][0]['lead'] ?? '') === 'Vivian', 'degraded opportunity outcome keeps its actor lead');

if ($failures) {
    echo 'FAILURES: ' . implode('; ', $failures) . "\n";
    exit(1);
}
echo "recap_service_guard_test: all checks passed.\n";
