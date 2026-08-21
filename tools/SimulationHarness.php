<?php
final class SimulationHarness
{
    public static function resetDatabase(): void
    {
        $pdo=db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach($tables as $table){$pdo->exec('DROP TABLE IF EXISTS `'.str_replace('`','``',(string)$table).'`');}
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        self::runSqlFile($pdo,__DIR__.'/../database/schema.sql');
        self::runSqlFile($pdo,__DIR__.'/../database/seed.sql');
        MigrationService::run();
    }

    public static function run(string $plotCode,string $strategy='no-action',int $duration=300,int $step=30): array
    {
        $sid=SimulationService::create($plotCode,$duration);
        SimulationService::start($sid);
        while(true){
            $sim=self::simulationRow($sid);
            if($sim['status']==='COMPLETE')break;
            if($strategy!=='no-action')self::playOpenPlayerDecisions($sid,$strategy);
            SimulationService::advance($sid,$step);
        }
        if($strategy!=='no-action')self::playOpenPlayerDecisions($sid,$strategy);
        StateSyncService::reconcile($sid);
        ResolutionService::refresh($sid);
        return self::result($sid);
    }

    private static function playOpenPlayerDecisions(int $sid,string $strategy): void
    {
        $snapshot=SimulationService::snapshot($sid);
        foreach($snapshot['characters'] as $character){
            if($character['actor_type']!=='PLAYER')continue;
            foreach($character['open_decisions'] as $decision){
                $options=$decision['options']??[];if(!$options)continue;
                $pick=$strategy==='first'?$options[0]:$options[random_int(0,count($options)-1)];
                try{SimulationService::choose($sid,(int)$decision['decision_id'],(string)$pick['option_code']);}
                catch(Throwable $e){/* state may have changed while another player acted; next reconcile handles it */}
            }
        }
    }

    public static function result(int $sid): array
    {
        $pdo=db();$snapshot=SimulationService::snapshot($sid);$sim=$snapshot['simulation'];
        $state=[];
        try{$q=$pdo->prepare('SELECT state_key,state_value FROM simulation_state WHERE simulation_id=? ORDER BY state_key');$q->execute([$sid]);foreach($q->fetchAll() as$r)$state[$r['state_key']]=$r['state_value'];}catch(Throwable $e){}
        $factors=[];foreach($snapshot['factors'] as$f)$factors[$f['factor_code']]=(int)$f['current_value'];
        $characters=[];foreach($snapshot['characters'] as$c){$characters[$c['name']]=['type'=>$c['actor_type'],'goal'=>$c['goal_text'],'fear'=>$c['fear_text'],'location'=>$c['current_location'],'stats'=>array_column($c['stats'],'current_value','stat_code')];}
        $counts=['player_actions'=>0,'npc_actions'=>0,'player_no_action'=>0];
        $q=$pdo->prepare('SELECT c.actor_type,d.status,COUNT(*) n FROM decisions d JOIN characters c ON c.character_id=d.character_id WHERE d.simulation_id=? GROUP BY c.actor_type,d.status');$q->execute([$sid]);foreach($q->fetchAll()as$r){if($r['actor_type']==='PLAYER'&&$r['status']==='CHOSEN')$counts['player_actions']+=(int)$r['n'];if($r['actor_type']==='NPC'&&$r['status']==='CHOSEN')$counts['npc_actions']+=(int)$r['n'];if($r['actor_type']==='PLAYER'&&$r['status']==='NO_ACTION')$counts['player_no_action']+=(int)$r['n'];}
        $resolution='';$q=$pdo->prepare('SELECT event_text FROM simulation_events WHERE simulation_id=? AND event_code="FINAL_STATE" ORDER BY event_id DESC LIMIT 1');$q->execute([$sid]);$resolution=(string)($q->fetchColumn()?:'');
        $warnings=self::integrityWarnings($sid,$snapshot,$state,$resolution);
        $signature=hash('sha256',json_encode(['factors'=>$factors,'state'=>$state,'goals'=>array_map(fn($c)=>$c['goal'],$characters)],JSON_UNESCAPED_UNICODE));
        return ['simulation_id'=>$sid,'plot'=>$sim['title'],'status'=>$sim['status'],'factors'=>$factors,'state'=>$state,'characters'=>$characters,'counts'=>$counts,'resolution'=>$resolution,'warnings'=>$warnings,'outcome_signature'=>substr($signature,0,12)];
    }

    private static function integrityWarnings(int $sid,array $snapshot,array $state,string $resolution): array
    {
        $pdo=db();$warnings=[];
        if(($snapshot['simulation']['status']??'')!=='COMPLETE')$warnings[]='Simulation did not reach COMPLETE.';
        foreach($snapshot['factors'] as$f){$v=(int)$f['current_value'];if($v<(int)$f['min_value']||$v>(int)$f['max_value'])$warnings[]='Factor '.$f['factor_code'].' is outside its configured range.';}
        $players=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='PLAYER'));$npcs=count(array_filter($snapshot['characters'],fn($c)=>$c['actor_type']==='NPC'));$allowed=$players===1?2:1;if($npcs>$allowed)$warnings[]='Active NPC count exceeds cast budget.';
        $q=$pdo->prepare('SELECT actor_character_id,event_second,COUNT(*) n FROM simulation_events WHERE simulation_id=? AND event_type="NPC" AND actor_character_id IS NOT NULL GROUP BY actor_character_id,event_second HAVING COUNT(*)>1');$q->execute([$sid]);foreach($q->fetchAll()as$r)$warnings[]='NPC '.$r['actor_character_id'].' produced multiple world actions at second '.$r['event_second'].'.';
        if(($state['LETTER_STATUS']??null)==='DESTROYED'&&stripos($resolution,'survives')!==false)$warnings[]='Resolution says the letter survives although LETTER_STATUS=DESTROYED.';
        if($resolution==='')$warnings[]='No FINAL_STATE resolution text was generated.';
        return array_values(array_unique($warnings));
    }

    private static function simulationRow(int $sid): array {$q=db()->prepare('SELECT * FROM simulations WHERE simulation_id=?');$q->execute([$sid]);$r=$q->fetch();if(!$r)throw new RuntimeException('Simulation not found.');return$r;}

    private static function runSqlFile(PDO $pdo,string $path): void
    {
        $sql=file_get_contents($path);if($sql===false)throw new RuntimeException('Unable to read '.basename($path));
        foreach(self::splitSql($sql) as$stmt)$pdo->exec($stmt);
    }

    private static function splitSql(string $sql): array
    {
        $out=[];$buf='';$quote=null;$escaped=false;$line=false;$block=false;$len=strlen($sql);
        for($i=0;$i<$len;$i++){$c=$sql[$i];$next=$i+1<$len?$sql[$i+1]:'';
            if($line){if($c==="\n"){$line=false;$buf.=$c;}continue;}
            if($block){if($c==='*'&&$next==='/'){$block=false;$i++;}continue;}
            if($quote===null){if(($c==='-'&&$next==='-'&&($i+2>=$len||ctype_space($sql[$i+2])))||$c==='#'){$line=true;if($c==='-')$i++;continue;}if($c==='/'&&$next==='*'){$block=true;$i++;continue;}if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}if($c===';'){$s=trim($buf);if($s!=='')$out[]=$s;$buf='';continue;}$buf.=$c;continue;}
            $buf.=$c;if($escaped){$escaped=false;continue;}if($c==='\\'&&$quote!=='`'){$escaped=true;continue;}if($c===$quote){if($next===$quote&&$quote!=='`'){$buf.=$next;$i++;continue;}$quote=null;}
        }
        $s=trim($buf);if($s!=='')$out[]=$s;return$out;
    }
}
