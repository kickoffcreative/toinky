<?php
declare(strict_types=1);
// Guard test: Lab batch runner (public/lab.php) safety contract. The runner
// must stay a Lab-only form: admin page, CSRF-protected POST, validated
// inputs, capped runs, in-process harness calls only — never a shell, never
// a reset path. Static checks only; no database needed.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$root = dirname(__DIR__);
$src = (string)file_get_contents($root . '/public/lab.php');
$failures = [];
$check = function (bool $ok, string $label) use (&$failures) {
    echo ($ok ? 'PASS' : 'FAIL') . ' ' . $label . "\n";
    if (!$ok) $failures[] = $label;
};

$check(str_contains($src, "if(\$action==='batch')"), 'lab.php handles a batch action');
$check(str_contains($src, "['no-action','random','weighted','first','anti-rowan','pro-rowan']"), 'batch strategy allowlist matches the CLI strategies');
$check(str_contains($src, 'min(5,max(1'), 'batch runs are capped at 5');
foreach (['shell_exec', 'passthru', 'proc_open', 'popen'] as $fn) {
    $check(!preg_match('/\b' . $fn . '\s*\(/', $src), 'lab.php never calls ' . $fn);
}
$check(!preg_match('/\bexec\s*\(/', $src), 'lab.php never calls exec');
$check(!preg_match('/\bsystem\s*\(/', $src), 'lab.php never calls system');
$check(!str_contains($src, 'resetDatabase'), 'lab.php has no reset path');
$csrfPos = strpos($src, 'requireCsrf()');
$batchPos = strpos($src, "if(\$action==='batch')");
$check($csrfPos !== false && $batchPos !== false && $batchPos > $csrfPos, 'batch action runs under the CSRF check');
$h = strpos($src, '<h1>Batch Run</h1>');
$formEnd = $h !== false ? strpos($src, '</form>', $h) : false;
$check($h !== false && $formEnd !== false && str_contains(substr($src, $h, $formEnd - $h), 'csrf_token'), 'batch form carries a CSRF token');

if ($failures) {
    echo 'FAILURES: ' . implode('; ', $failures) . "\n";
    exit(1);
}
echo "lab_batch_runner_guard_test: all checks passed.\n";
