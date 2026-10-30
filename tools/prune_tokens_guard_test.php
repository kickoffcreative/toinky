<?php
declare(strict_types=1);
// Guard test: expired token/invitation cleanup tool (audit task 18).
// Static checks — the tool must be CLI-only, dry-run by default, use only
// the safe expiry predicates, and remove media only through
// MediaLibraryService::delete() (never a raw media DELETE). It must not
// touch the empty feature table plot_event_perceptions.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$tool=$root.'/tools/prune_expired_tokens.php';
$check(is_file($tool),'tools/prune_expired_tokens.php exists');
$src=$tool&&is_file($tool)?file_get_contents($tool):'';
$check(str_contains($src,"if(PHP_SAPI!=='cli')"),'prune_expired_tokens.php is CLI-only');
$check(str_contains($src,'--apply')&&str_contains($src,'Dry run'),'prune_expired_tokens.php is dry-run by default');
$check(str_contains($src,'expires_at_utc <= UTC_TIMESTAMP()'),'prune_expired_tokens.php deletes only expired rows');
$check(str_contains($src,'used_at_utc IS NOT NULL')&&str_contains($src,'accepted_at_utc IS NOT NULL'),'prune_expired_tokens.php also removes used/accepted rows');
$check(str_contains($src,'MediaLibraryService::delete('),'prune_expired_tokens.php removes media via MediaLibraryService::delete()');
$check(!preg_match('/DELETE\s+FROM\s+(writer_media_assets|plot_media_assets)\b/i',$src),'prune_expired_tokens.php never deletes media by raw SQL');
$check(str_contains($src,'usageByMediaIds'),'prune_expired_tokens.php re-checks usage at runtime');
$check(str_contains($src,'active=0'),'prune_expired_tokens.php also checks inactive plot_media_assets rows');
foreach(['plot_event_perceptions'] as $t){
    // The tool names this table in its header comment (stating it never
    // touches it), so a plain string search false-positives. Check for an
    // actual SQL statement against the table instead.
    $check(!preg_match('/\b(FROM|INTO|UPDATE|JOIN)\s+`?'.$t.'\b/i',$src),'prune_expired_tokens.php does not touch '.$t);
}

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "prune_tokens_guard_test: all checks passed.\n";
