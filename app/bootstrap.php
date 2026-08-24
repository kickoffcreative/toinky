<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $override = trim((string)getenv('GAME2_DATABASE_CONFIG'));
    $path = $override !== '' ? $override : (__DIR__ . '/../config/database.php');
    if (!is_file($path)) throw new RuntimeException('Missing database config: '.$path);
    $cfg = require $path;
    $pdo = new PDO($cfg['dsn'], $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $pdo;
}

function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/GenericStateService.php';
require_once __DIR__ . '/ResolutionService.php';
require_once __DIR__ . '/TriggeredTransitionService.php';
require_once __DIR__ . '/StoryReasoner.php';
require_once __DIR__ . '/AiInteractionLogger.php';
require_once __DIR__ . '/DeterministicStoryReasoner.php';
require_once __DIR__ . '/OllamaStoryReasoner.php';
require_once __DIR__ . '/StoryReasonerFactory.php';
require_once __DIR__ . '/AiReasoningContextService.php';

register_shutdown_function(static function (): void {
    $sid=(int)($_POST['simulation_id']??$_GET['simulation']??0);
    if($sid<=0)return;
    try{
        GenericStateService::initializePlotState($sid);
        if(class_exists('TriggeredTransitionService'))TriggeredTransitionService::evaluate($sid);
        if(class_exists('ResolutionService'))ResolutionService::refresh($sid);
    }catch(Throwable $e){/* page-level error handling remains authoritative */}
});
