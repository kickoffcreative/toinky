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
    $pat = ($method ? '/private\s+static\s+function\s+' : '/function\s+/') . preg_quote($name, '/') . '\s*\(/';
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

if ($failures) {
    echo 'FAILURES: ' . implode('; ', $failures) . "\n";
    exit(1);
}
echo "recap_service_guard_test: all checks passed.\n";
