<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/CommerceService.php';
require_once $root.'/app/LobbyService.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/CausalService.php';
require_once $root.'/app/CharacterAutomationService.php';
require_once $root.'/app/DynamicOpportunityService.php';
require_once $root.'/app/AiReasoningContextService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at_utc) VALUES(?,?,?,1,UTC_TIMESTAMP())')->execute(['commerce-test-admin@toinky.invalid','!','Commerce Test Admin']);$admin=['user_id'=>(int)$pdo->lastInsertId()];}
if(!$admin)throw new RuntimeException('Commerce test needs an administrator fixture.');
$host=$pdo->query('SELECT user_id FROM users WHERE is_admin=0 ORDER BY user_id LIMIT 1')->fetch();
if(!$host){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at_utc) VALUES(?,?,?,0,UTC_TIMESTAMP())')->execute(['commerce-test-host@toinky.invalid','!','Commerce Test Host']);$host=['user_id'=>(int)$pdo->lastInsertId()];}
if(!$host)throw new RuntimeException('Commerce test needs a non-admin user fixture.');
$hostId=(int)$host['user_id'];
// Task 5 Phase 1: hosted games run the PUBLISHED version, so createGame fails
// closed when a story has no published pointer. Fresh builds seed the stories
// as PUBLISHED with no version row, so publish EMPTY_THRONE here when it has
// none, with the same snapshot submit() builds. Left in place so the later
// atomicity test in this suite can host too; on a live install the pointer
// already exists and this is a no-op.
$plotQ=$pdo->prepare('SELECT plot_id,published_plot_version_id FROM plots WHERE code=? AND status="active"');$plotQ->execute(['EMPTY_THRONE']);$plotRow=$plotQ->fetch();
if($plotRow&&empty($plotRow['published_plot_version_id'])){require_once $root.'/app/StoryAuthoringService.php';$plotId=(int)$plotRow['plot_id'];$snapshot=json_encode(['plot'=>StoryAuthoringService::story($plotId),'content'=>StoryAuthoringService::workspace($plotId)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$vq=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM plot_versions WHERE plot_id=?');$vq->execute([$plotId]);$versionNo=(int)$vq->fetchColumn();$adminId=(int)$admin['user_id'];$pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,workflow_status,snapshot_json,submitted_by_user_id,reviewed_by_user_id,reviewed_at_utc) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$plotId,$versionNo,'PUBLISHED',$snapshot,$adminId,$adminId]);$pdo->prepare('UPDATE plots SET published_plot_version_id=?,workflow_status="PUBLISHED" WHERE plot_id=?')->execute([(int)$pdo->lastInsertId(),$plotId]);}

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$saved=[];
foreach(['COMMERCE_ENABLED','COMMERCE_FREE_FIRST','COMMERCE_BUNDLES'] as $k){$q=$pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');$q->execute([$k]);$v=$q->fetchColumn();$saved[$k]=$v===false?null:(string)$v;}
$set=function(string $k,string $v)use($pdo){$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$k,$v]);};
$set('COMMERCE_ENABLED','1');$set('COMMERCE_FREE_FIRST','1');

$gameIds=[];
try{
    $before=CommerceService::balance($hostId);
    $check($before['granted']>=1,'free first occasion is already visible in the first balance read');
    CommerceService::grantFreeFirst($hostId);
    CommerceService::grantFreeFirst($hostId);
    $afterGrant=CommerceService::balance($hostId);
    $check($afterGrant['granted']===$before['granted'],'free first occasion is never granted twice');

    $state=LobbyService::createGame($hostId,'EMPTY_THRONE',1800);
    $gameId=(int)$state['game']['game_id'];$gameIds[]=$gameId;
    $afterCreate=CommerceService::balance($hostId);
    $check($afterCreate['reserved']===$before['reserved']+1&&$afterCreate['available']===$afterGrant['available']-1,'lobby creation holds one occasion');

    LobbyService::startGame($gameId,$hostId);
    $afterStart=CommerceService::balance($hostId);
    $check($afterStart['consumed']===$before['consumed']+1&&$afterStart['reserved']===$before['reserved'],'starting the game spends the held occasion');

    $state=LobbyService::createGame($hostId,'EMPTY_THRONE',1800);
    $game2=(int)$state['game']['game_id'];$gameIds[]=$game2;
    LobbyService::deleteLobby($game2,$hostId);
    $afterDelete=CommerceService::balance($hostId);
    $check($afterDelete['reserved']===$before['reserved']&&$afterDelete['available']===$afterStart['available'],'deleting an unstarted lobby returns the occasion');
}finally{
    foreach($gameIds as $gid){
        $pdo->prepare('DELETE FROM host_occasion_ledger WHERE game_id=?')->execute([$gid]);
        $pdo->prepare('DELETE FROM game_players WHERE game_id=?')->execute([$gid]);
        $pdo->prepare('DELETE FROM games WHERE game_id=?')->execute([$gid]);
    }
    $pdo->prepare("DELETE FROM host_occasion_ledger WHERE user_id=? AND entry_type='FREE_FIRST'")->execute([$hostId]);
    foreach($saved as $k=>$v){if($v===null){$pdo->prepare('DELETE FROM platform_settings WHERE setting_key=?')->execute([$k]);}else{$set($k,$v);}}
}
if($failures)throw new RuntimeException('Commerce guard test failed: '.implode('; ',$failures));
echo "COMMERCE GUARD TEST PASSED\n";
