<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/CommerceService.php';
require_once $root.'/app/LobbyService.php';

$pdo=db();
$admin=$pdo->query('SELECT user_id FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
if(!$admin){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at_utc) VALUES(?,?,?,1,UTC_TIMESTAMP())')->execute(['commerce-test-admin@toinky.invalid','!','Commerce Test Admin']);$admin=['user_id'=>(int)$pdo->lastInsertId()];}
if(!$admin)throw new RuntimeException('Commerce test needs an administrator fixture.');
$host=$pdo->query('SELECT user_id FROM users WHERE is_admin=0 ORDER BY user_id LIMIT 1')->fetch();
if(!$host){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at_utc) VALUES(?,?,?,0,UTC_TIMESTAMP())')->execute(['commerce-test-host@toinky.invalid','!','Commerce Test Host']);$host=['user_id'=>(int)$pdo->lastInsertId()];}
if(!$host)throw new RuntimeException('Commerce test needs a non-admin user fixture.');
$hostId=(int)$host['user_id'];

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
