<?php
date_default_timezone_set('UTC');

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

// Task 20: one correlation ID per request, so a tick -> AI call -> failure
// can be traced end to end. Read it via correlationId(); Logger includes it
// in every structured log line.
$GLOBALS['toinky_correlation_id'] = bin2hex(random_bytes(8));
function correlationId(): string { return (string)($GLOBALS['toinky_correlation_id'] ?? ''); }

require_once __DIR__ . '/GenericStateService.php';
require_once __DIR__ . '/SimulationContent.php';
require_once __DIR__ . '/ResolutionService.php';
require_once __DIR__ . '/TriggeredTransitionService.php';
require_once __DIR__ . '/StoryReasoner.php';
require_once __DIR__ . '/AiInteractionLogger.php';
require_once __DIR__ . '/AiSettingsService.php';
require_once __DIR__ . '/CommerceService.php';
require_once __DIR__ . '/AiConnectionService.php';
require_once __DIR__ . '/DeterministicStoryReasoner.php';
require_once __DIR__ . '/OllamaStoryReasoner.php';
require_once __DIR__ . '/StoryReasonerFactory.php';
require_once __DIR__ . '/AiReasoningContextService.php';
require_once __DIR__ . '/Logger.php';

// No shutdown hook mutates simulation state. Simulation synchronization runs only
// through explicit authorized commands: LobbyService::tick() (play progression),
// StateSyncService::reconcile() (Lab), and SimulationService::create()/advance().
// A previous shutdown hook read simulation_id/simulation from any request — including
// unauthenticated reads — and applied state, transitions and resolution with no
// authorization, membership or CSRF check and silently swallowed all errors.
// It was removed (audit task 1, 2026-10-26). Do not reintroduce request-driven
// simulation writes here; route them through an authorized command instead.
