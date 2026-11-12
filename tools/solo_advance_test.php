<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
foreach(['BehaviorService','CausalService','DynamicOpportunityService','CharacterAutomationService','StateSyncService','SimulationService','LobbyService','StoryAuthoringService'] as $service)require_once $root.'/app/'.$service.'.php';
$pdo=db();$users=[];$sid=0;$versionId=0;$plot=null;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";};
$reject=static function(callable $action)use($check):void{$blocked=false;try{$action();}catch(RuntimeException $e){$blocked=true;}$check($blocked,'unauthorized or open-choice advance is rejected');};
try{
    $plot=$pdo->query('SELECT * FROM plots WHERE code="CONCEALED_FAILURE"')->fetch();
    if(!$plot)throw new RuntimeException('Seeded story fixture is required.');
    foreach(['host','other'] as $name){$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at_utc) VALUES(?,?,?,1,UTC_TIMESTAMP())')->execute(['solo-'.$name.'-'.bin2hex(random_bytes(6)).'@toinky.invalid','!','Solo Test '.$name]);$users[]=(int)$pdo->lastInsertId();}
    // Test-only immutable snapshot, never a live draft fallback.
    $snapshot=json_encode(['plot'=>StoryAuthoringService::story((int)$plot['plot_id']),'content'=>StoryAuthoringService::workspace((int)$plot['plot_id'])],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $v=$pdo->prepare('SELECT COALESCE(MAX(version_no),0)+1 FROM plot_versions WHERE plot_id=?');$v->execute([$plot['plot_id']]);
    $pdo->prepare('INSERT INTO plot_versions(plot_id,version_no,workflow_status,snapshot_json,submitted_by_user_id,reviewed_by_user_id,reviewed_at_utc) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP())')->execute([$plot['plot_id'],(int)$v->fetchColumn(),'PUBLISHED',$snapshot,$users[0],$users[0]]);$versionId=(int)$pdo->lastInsertId();
    $pdo->prepare('UPDATE plots SET published_plot_version_id=?,workflow_status="PUBLISHED" WHERE plot_id=?')->execute([$versionId,$plot['plot_id']]);
    $game=LobbyService::createGame($users[0],(string)$plot['code'],1800);$gid=(int)$game['game']['game_id'];$sid=(int)$game['game']['simulation_id'];
    $q=$pdo->prepare('SELECT c.character_id FROM characters c JOIN plot_roles r ON r.plot_role_id=c.plot_role_id WHERE c.simulation_id=? AND r.role_code="WREN"');$q->execute([$sid]);$cid=(int)$q->fetchColumn();
    if(!$cid)throw new RuntimeException('Wren fixture is required.');
    LobbyService::claimCharacter($gid,$users[0],$cid);
    $check(!LobbyService::canAdvanceSolo($gid,$users[0]),'lobby cannot fast-forward');
    LobbyService::startGame($gid,$users[0]);
    $check(LobbyService::canAdvanceSolo($gid,$users[0]),'one-player host can continue');
    $check(!LobbyService::canAdvanceSolo($gid,$users[1]),'another user has no solo control');
    $reject(static fn()=>LobbyService::advanceSolo($gid,$users[1]));
    $q=$pdo->prepare('SELECT started_at_utc FROM games WHERE game_id=?');$q->execute([$gid]);$started=$q->fetchColumn();
    $getOpen=static function()use($pdo,$sid,$cid):array{$q=$pdo->prepare('SELECT decision_id FROM decisions WHERE simulation_id=? AND character_id=? AND status="OPEN"');$q->execute([$sid,$cid]);return $q->fetchAll();};
    // Some seeded variants can open an opportunity immediately. Resolve it
    // through the real player service before testing an idle interval.
    for($i=0;$i<10&&$getOpen();$i++){
        $d=$getOpen()[0];$q=$pdo->prepare('SELECT option_code FROM decision_options WHERE decision_id=? ORDER BY sort_order,decision_option_id');$q->execute([$d['decision_id']]);$code=(string)$q->fetchColumn();LobbyService::choose($gid,$users[0],(int)$d['decision_id'],$code);
    }
    if($getOpen())throw new RuntimeException('Fixture has no quiet interval.');
    $elapsed=static function()use($pdo,$sid):int{$q=$pdo->prepare('SELECT elapsed_seconds FROM simulations WHERE simulation_id=?');$q->execute([$sid]);return(int)$q->fetchColumn();};
    $before=$elapsed();
    LobbyService::advanceSolo($gid,$users[0]);
    $after=$elapsed();
    $check($after>$before&&$after<1800,'quiet time advances without jumping to the ending');
    $q=$pdo->prepare('SELECT started_at_utc,solo_time_offset_seconds FROM games WHERE game_id=?');$q->execute([$gid]);$clock=$q->fetch();
    $check($clock['started_at_utc']===$started,'real start timestamp is preserved');
    $check((int)$clock['solo_time_offset_seconds']>0,'skipped time is recorded separately');
    // A two-second test-only wall-clock move proves ordinary polling keeps
    // progressing after a skip rather than freezing until wall time catches up.
    $pdo->prepare('UPDATE games SET started_at_utc=DATE_SUB(started_at_utc,INTERVAL 2 SECOND) WHERE game_id=?')->execute([$gid]);LobbyService::tick($gid);
    $check($elapsed()>$after,'normal timer continues after a skip');
    for($i=0;$i<20&&!$getOpen();$i++)LobbyService::advanceSolo($gid,$users[0]);
    $check((bool)$getOpen(),'fast-forward stops with a player choice available');
    $before=$elapsed();$reject(static fn()=>LobbyService::advanceSolo($gid,$users[0]));
    $check($elapsed()===$before,'an open choice is not advanced or expired');
    $pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gid,$users[1]]);
    $check(!LobbyService::canAdvanceSolo($gid,$users[0]),'a second participant disables solo progression');$reject(static fn()=>LobbyService::advanceSolo($gid,$users[0]));
    $check((int)$pdo->query("SELECT IS_FREE_LOCK('toinky_game_".$gid."')")->fetchColumn()===1,'game lock is released after rejection');
    $pdo->prepare('DELETE FROM game_players WHERE game_id=? AND user_id=?')->execute([$gid,$users[1]]);
    for($i=0;$i<250;$i++){
        $q=$pdo->prepare('SELECT status FROM games WHERE game_id=?');$q->execute([$gid]);if($q->fetchColumn()==='COMPLETE')break;
        $decisions=$getOpen();
        if(!$decisions){LobbyService::advanceSolo($gid,$users[0]);continue;}
        $decision=(int)$decisions[0]['decision_id'];$q=$pdo->prepare('SELECT option_code FROM decision_options WHERE decision_id=? ORDER BY sort_order,decision_option_id');$q->execute([$decision]);$chosen=false;
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $code){try{LobbyService::choose($gid,$users[0],$decision,(string)$code);$chosen=true;break;}catch(RuntimeException $e){}}
        if(!$chosen)throw new RuntimeException('No legal option in the seeded completion path.');
    }
    $q=$pdo->prepare('SELECT status FROM games WHERE game_id=?');$q->execute([$gid]);
    $check($q->fetchColumn()==='COMPLETE'&&$elapsed()===1800,'solo progression reaches the actual ending through normal engine steps');
    $check(!$getOpen(),'completion leaves no player choice stranded');
    echo 'Solo progression: '.$checks." checks passed.\n";
}finally{
    if($sid)$pdo->prepare('DELETE FROM simulations WHERE simulation_id=?')->execute([$sid]);
    if($plot&&$versionId){$pdo->prepare('UPDATE plots SET published_plot_version_id=?,workflow_status=? WHERE plot_id=?')->execute([$plot['published_plot_version_id'],$plot['workflow_status'],$plot['plot_id']]);$pdo->prepare('DELETE FROM plot_versions WHERE plot_version_id=?')->execute([$versionId]);}
    foreach($users as $id)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);
}
