<?php
// One-time OPcache invalidation for the Lab runner deploy (Oct 31, 2026).
// TEMPORARY: remove this file (and its .cpanel.yml entries) once the Lab
// page serves the new code. Admin-only, POST + CSRF, hardcoded paths only:
// it invalidates public/lab.php (and the lab.php stub) and reports status.
// It never runs shell commands, writes files, or deletes anything.
$root = __DIR__;
require $root . '/app/runtime_check.php';
require $root . '/app/bootstrap.php';
require $root . '/app/AuthService.php';
AuthService::boot();
$adminUser = AuthService::requireAdmin();
$message = '';
$targets = [$root . '/public/lab.php', $root . '/lab.php'];
$cached = [];
foreach ($targets as $t) {
    $cached[$t] = function_exists('opcache_is_script_cached') ? @opcache_is_script_cached($t) : null;
}
$opcacheOn = false;
if (function_exists('opcache_get_status')) {
    $opcacheOn = @opcache_get_status(false) !== false;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        AuthService::requireCsrf();
        $results = [];
        foreach ($targets as $t) {
            $results[$t] = function_exists('opcache_invalidate') ? @opcache_invalidate($t, true) : false;
        }
        $after = [];
        foreach ($targets as $t) {
            $after[$t] = function_exists('opcache_is_script_cached') ? @opcache_is_script_cached($t) : null;
        }
        $message = 'Invalidation requested. Results: ' . json_encode($results) . '. Cached after: ' . json_encode($after) . '. Reload lab.php to check for the Batch Run form.';
    } catch (Throwable $e) {
        $message = 'Error: ' . $e->getMessage();
    }
}
?><!doctype html><html><head><meta charset="utf-8"><title>OPcache reset (temporary)</title></head><body><h1>OPcache reset (temporary tool)</h1><p>Signed in as <?= e($adminUser['display_name']) ?></p><p>OPcache enabled: <?= $opcacheOn ? 'yes' : 'no' ?></p><ul><?php foreach ($targets as $t): ?><li><?= e($t) ?> — cached: <?= var_export($cached[$t], true) ?></li><?php endforeach; ?></ul><?php if ($message !== ''): ?><p><?= e($message) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(AuthService::csrfToken()) ?>"><button>Invalidate Lab page cache</button></form><p>This file is temporary and will be removed once the Lab page serves the new code.</p></body></html>
