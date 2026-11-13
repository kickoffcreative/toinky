<?php
declare(strict_types=1);
// Guard test: Adventure theme must offer plain Alegreya Sans (not only the
// small-caps SC variant) in the Theme Manager, and play.php must load it
// from the local font catalogue. Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$admin = (string)file_get_contents($root . '/public/admin.php');
$play = (string)file_get_contents($root . '/public/play.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$check(str_contains($admin, "'Adventure — Alegreya Sans'=>'Alegreya Sans, Arial, sans-serif'"), 'Theme Manager offers Adventure Alegreya Sans (plain, not SC)');
$check(str_contains($admin, "'Adventure — Alegreya Sans SC'=>'Alegreya Sans SC, Arial, sans-serif'"), 'Theme Manager keeps the SC option');
$check(str_contains($admin, 'assets/fonts/fonts.css'), 'Admin loads local font catalogue');
$check(str_contains($play, 'assets/fonts/fonts.css'), 'Play loads local font catalogue');
$manifest=json_decode((string)file_get_contents($root.'/public/assets/fonts/manifest.json'),true);
$check(in_array('Alegreya Sans',$manifest['families']??[],true),'Local catalogue includes plain Alegreya Sans');

if ($failures) {
    fwrite(STDERR, 'FAILED: ' . implode('; ', $failures) . "\n");
    exit(1);
}
echo "OK theme font guard\n";
