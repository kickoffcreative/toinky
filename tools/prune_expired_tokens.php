<?php
declare(strict_types=1);
// Cleanup tool (audit task 18): delete expired/used auth tokens and
// invitations, and remove the three unreferenced media candidates.
// --dry-run is the default; pass --apply to actually change rows.
// Token/invitation predicates are safe by construction: an expired (or
// used/accepted) row can never validate again (AuthService validates with
// used_at_utc/accepted_at_utc IS NULL AND expires_at_utc > UTC_TIMESTAMP()).
// Media is NEVER deleted by raw SQL: each candidate is re-checked at
// runtime with MediaLibraryService::usageByMediaIds() (five reference
// sites) PLUS a direct check for inactive plot_media_assets rows (which
// usageByMediaIds() filters out), and removed only via
// MediaLibraryService::delete() when both checks are empty. A candidate
// that is still referenced is reported and skipped, never forced.
// This tool never drops a table and never touches the empty feature
// tables or the tool-consumed audit/focus tables
// (plot_event_perceptions, plot_focus_profiles, plot_focus_option_weights,
// platform_story_audit_rules).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MediaLibraryService.php';

$apply=in_array('--apply',$argv,true);
$pdo=db();

$counts=[];
$counts['password_reset_tokens']=$pdo->query("SELECT COUNT(*) FROM password_reset_tokens WHERE expires_at_utc <= UTC_TIMESTAMP() OR used_at_utc IS NOT NULL")->fetchColumn();
$counts['user_invitations']=$pdo->query("SELECT COUNT(*) FROM user_invitations WHERE expires_at_utc <= UTC_TIMESTAMP() OR accepted_at_utc IS NOT NULL")->fetchColumn();
$counts['game_invitations']=$pdo->query("SELECT COUNT(*) FROM game_invitations WHERE expires_at_utc <= UTC_TIMESTAMP() OR accepted_at_utc IS NOT NULL")->fetchColumn();

$admin=$pdo->query('SELECT * FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$mediaCandidates=[114,118,124];
$mediaPlan=[];
foreach($mediaCandidates as $mid){
    $usage=MediaLibraryService::usageByMediaIds($admin?:[],[$mid])[$mid]??[];
    $inactive=$pdo->prepare('SELECT COUNT(*) FROM plot_media_assets WHERE library_media_id=? AND active=0');$inactive->execute([$mid]);$nInactive=(int)$inactive->fetchColumn();
    $mediaPlan[$mid]=['usage'=>count($usage),'inactive_refs'=>$nInactive,'removable'=>empty($usage)&&$nInactive===0];
}

echo "Expired token / invitation cleanup (".($apply?'APPLY':'dry-run').")\n";
foreach($counts as $table=>$n)echo "  {$table}: {$n} row(s) to delete\n";
echo "Media candidates (re-checked at runtime):\n";
foreach($mediaPlan as $mid=>$plan)echo "  media {$mid}: usage_refs={$plan['usage']} inactive_refs={$plan['inactive_refs']} ".($plan['removable']?'REMOVABLE':'SKIP (still referenced)')."\n";
if(!$apply){echo "Dry run — no rows changed. Re-run with --apply to apply.\n";exit(0);}

$deleted=[];
$d=$pdo->prepare("DELETE FROM password_reset_tokens WHERE expires_at_utc <= UTC_TIMESTAMP() OR used_at_utc IS NOT NULL");$d->execute();$deleted['password_reset_tokens']=$d->rowCount();
$d=$pdo->prepare("DELETE FROM user_invitations WHERE expires_at_utc <= UTC_TIMESTAMP() OR accepted_at_utc IS NOT NULL");$d->execute();$deleted['user_invitations']=$d->rowCount();
$d=$pdo->prepare("DELETE FROM game_invitations WHERE expires_at_utc <= UTC_TIMESTAMP() OR accepted_at_utc IS NOT NULL");$d->execute();$deleted['game_invitations']=$d->rowCount();
foreach($deleted as $table=>$n)echo "  deleted {$n} row(s) from {$table}\n";
foreach($mediaPlan as $mid=>$plan){
    if(!$plan['removable']){echo "  media {$mid}: skipped (still referenced)\n";continue;}
    try{MediaLibraryService::delete($admin?:[],$mid,false);echo "  media {$mid}: removed via MediaLibraryService::delete()\n";}
    catch(Throwable $e){echo "  media {$mid}: FAILED — ".$e->getMessage()."\n";}
}
echo "prune_expired_tokens: done.\n";
