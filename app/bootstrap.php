<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $path = __DIR__ . '/../config/database.php';
    if (!is_file($path)) throw new RuntimeException('Missing config/database.php');
    $cfg = require $path;
    $pdo = new PDO($cfg['dsn'], $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/TriggeredTransitionService.php';

// Evaluate plot-defined causal transitions after each request. This keeps objective-state
// rules independent from any specific page or storyline. The next request therefore sees
// the authoritative state produced by the previous decisions.
register_shutdown_function(static function (): void {
    $sid=(int)($_POST['simulation_id']??$_GET['simulation']??0);
    if($sid<=0 || !class_exists('TriggeredTransitionService'))return;
    try{TriggeredTransitionService::evaluate($sid);}catch(Throwable $e){/* normal page error handling remains authoritative */}
});
