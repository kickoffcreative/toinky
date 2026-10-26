<?php
declare(strict_types=1);
// Retention tool (audit task 17): reclaim AI-log payload bulk without
// losing review/usage evidence. --dry-run is the default; pass --apply to
// actually change rows. It NEVER deletes rows and NEVER touches
// parsed_response_json or any metric column — aiUsage() per-simulation
// counts are derived live from this table, so the aggregates must survive.
//   (1) Completed logs older than 90 days: null out request_json and
//       response_json (the bulk). parsed_response_json + metrics stay.
//   (2) Unfinished logs (request_completed_at_utc IS NULL, started >24h
//       ago): mark failed (success=0) with a [retention] error note. They
//       are NOT deleted until triaged. Idempotent: re-running changes
//       nothing already pruned or already marked.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';

$apply=in_array('--apply',$argv,true);
$pdo=db();

$oldPayload=$pdo->prepare("SELECT COUNT(*) FROM ai_reasoner_interactions WHERE request_completed_at_utc IS NOT NULL AND request_completed_at_utc < UTC_TIMESTAMP() - INTERVAL 90 DAY AND (request_json IS NOT NULL OR response_json IS NOT NULL)");
$oldPayload->execute();$nOld=(int)$oldPayload->fetchColumn();
$unfinished=$pdo->prepare("SELECT COUNT(*) FROM ai_reasoner_interactions WHERE request_completed_at_utc IS NULL AND request_started_at_utc < UTC_TIMESTAMP() - INTERVAL 24 HOUR AND (error_text IS NULL OR error_text NOT LIKE '%[retention]%')");
$unfinished->execute();$nUnf=(int)$unfinished->fetchColumn();

echo "AI log retention (".($apply?'APPLY':'dry-run').")\n";
echo "completed logs >90 days with raw payloads to null: {$nOld}\n";
echo "unfinished logs >24h old to mark failed: {$nUnf}\n";
if(!$apply){echo "Dry run — no rows changed. Re-run with --apply to apply.\n";exit(0);}

$u=$pdo->prepare("UPDATE ai_reasoner_interactions SET request_json=NULL,response_json=NULL WHERE request_completed_at_utc IS NOT NULL AND request_completed_at_utc < UTC_TIMESTAMP() - INTERVAL 90 DAY AND (request_json IS NOT NULL OR response_json IS NOT NULL)");
$u->execute();$nulled=$u->rowCount();
$m=$pdo->prepare("UPDATE ai_reasoner_interactions SET success=0,error_text=CONCAT(COALESCE(error_text,''),' [retention] Marked failed: request never completed (started >24h ago).') WHERE request_completed_at_utc IS NULL AND request_started_at_utc < UTC_TIMESTAMP() - INTERVAL 24 HOUR AND (error_text IS NULL OR error_text NOT LIKE '%[retention]%')");
$m->execute();$marked=$m->rowCount();
echo "payloads nulled: {$nulled}\n";
echo "unfinished logs marked failed: {$marked}\n";
echo "prune_ai_logs: done. No rows deleted; parsed_response_json and metrics preserved.\n";
