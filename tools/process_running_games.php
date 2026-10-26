<?php
declare(strict_types=1);
// Worker: advance RUNNING games whose players have all closed their browsers
// (audit task 11, 2026-10-26). play.php only calls LobbyService::tick() while
// a browser polls it every 2s, so a game with no polling browser freezes
// RUNNING indefinitely. This CLI worker is the second (and only other)
// tick() caller: it runs LobbyService::tick() for every RUNNING game, which
// already does wall-clock catch-up, AI automation and the RUNNING->COMPLETE
// flip under the per-game advisory lock — so a worker tick and a browser tick
// cannot collide. There is deliberately NO status-only UPDATE here: a game
// must be advanced through the engine, not flipped by hand.
//
// cPanel cron (add in the cPanel dashboard, every minute):
// /opt/cpanel/ea-php84/root/usr/bin/php /home/toinkyadmin/public_html/tools/process_running_games.php --max=50
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/BehaviorService.php';
require_once $root.'/app/CausalService.php';
require_once $root.'/app/DynamicOpportunityService.php';
require_once $root.'/app/CharacterAutomationService.php';
require_once $root.'/app/StateSyncService.php';
require_once $root.'/app/SimulationService.php';
require_once $root.'/app/LobbyService.php';

$max=50;
foreach(array_slice($argv,1) as $arg)if(preg_match('/^--max=(\d+)$/',$arg,$m))$max=max(1,min(100,(int)$m[1]));
$pdo=db();
$q=$pdo->prepare("SELECT game_id FROM games WHERE status='RUNNING' AND deleted_at_utc IS NULL ORDER BY game_id LIMIT ?");
$q->bindValue(1,$max,PDO::PARAM_INT);$q->execute();
$games=array_column($q->fetchAll(),'game_id');
$ticked=0;$completed=0;$failed=0;
foreach($games as $gid){
    try{
        LobbyService::tick((int)$gid);$ticked++;
        $s=$pdo->prepare('SELECT status FROM games WHERE game_id=?');$s->execute([(int)$gid]);
        if($s->fetchColumn()==='COMPLETE'){$completed++;echo 'Game '.(int)$gid.': completed.'.PHP_EOL;}
    }catch(Throwable $e){
        $failed++;
        fwrite(STDERR,'Game '.(int)$gid.' tick failed: '.$e->getMessage().PHP_EOL);
    }
}
echo 'Ticked '.$ticked.' running game(s)'.($completed?' ('.$completed.' completed)':'').($failed?' ('.$failed.' failed)':'').'.'.PHP_EOL;
exit($failed>0?1:0);
