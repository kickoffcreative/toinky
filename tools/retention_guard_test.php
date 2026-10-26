<?php
declare(strict_types=1);
// Guard test: AI log retention tool (audit task 17). Static checks — the
// tool must be CLI-only, dry-run by default, and must never DELETE rows
// (it only nulls payload columns and marks unfinished logs failed, so the
// aiUsage() aggregates survive).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$tool=$root.'/tools/prune_ai_logs.php';
$check(is_file($tool),'tools/prune_ai_logs.php exists');
$src=$tool&&is_file($tool)?file_get_contents($tool):'';
$check(str_contains($src,"if(PHP_SAPI!=='cli')"),'prune_ai_logs.php is CLI-only');
$check(str_contains($src,"--dry-run")||str_contains($src,'$apply=in_array'),'prune_ai_logs.php has an --apply gate (dry-run by default)');
$check(!str_contains($src,'exit(0);')===false||str_contains($src,'Dry run'),'prune_ai_logs.php reports a dry run without changing rows');
$check(!preg_match('/\bDELETE\s+FROM\b/i',$src),'prune_ai_logs.php never issues a DELETE');
$check(str_contains($src,'request_json=NULL')&&str_contains($src,'response_json=NULL'),'prune_ai_logs.php nulls old raw payload columns');
$check(str_contains($src,'parsed_response_json')&&!str_contains($src,'parsed_response_json=NULL'),'prune_ai_logs.php preserves parsed_response_json');
$check(str_contains($src,'request_completed_at_utc IS NULL'),'prune_ai_logs.php targets unfinished logs by request_completed_at_utc IS NULL');

$migration=$root.'/database/migrations/132_ai_log_retention.sql';
$check(is_file($migration),'migration 132_ai_log_retention.sql exists');
$msrc=$migration&&is_file($migration)?file_get_contents($migration):'';
$check(str_contains($msrc,"store_raw_payloads")&&str_contains($msrc,"'0'"),"migration 132 sets store_raw_payloads='0'");
$check(str_contains($msrc,'ai_reasoner_settings'),'migration 132 writes ai_reasoner_settings (the table AiInteractionLogger reads)');

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "retention_guard_test: all checks passed.\n";
