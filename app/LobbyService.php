<?php
final class LobbyService
{
    public static function createGame(int $hostUserId,string $plotCode,int $duration=300): array
    {
        $pdo=db();$q=$pdo->prepare('SELECT duration_seconds FROM platform_game_durations WHERE duration_seconds=? AND active=1');$q->execute([$duration]);if(!(int)$q->fetchColumn())throw new RuntimeException('That story duration is not available.');
        $q=$pdo->prepare('SELECT timeline_duration_seconds FROM plots WHERE code=? AND status="active"');$q->execute([$plotCode]);$timelineDuration=(int)$q->fetchColumn();if($timelineDuration<1)throw new RuntimeException('The story timeline duration is not configured.');
        $roles=$pdo->prepare('SELECT pr.role_code FROM plot_roles pr JOIN plots p ON p.plot_id=pr.plot_id WHERE p.code=? AND p.status="active" AND pr.active=1 AND pr.simulation_active=1');$roles->execute([$plotCode]);$controllers=[];foreach($roles->fetchAll()as$r)$controllers[$r['role_code']]='AI';
        $sid=SimulationService::create($plotCode,$timelineDuration,$controllers);$code=self::newCode();
        $pdo->beginTransaction();try{$pdo->prepare('INSERT INTO games(simulation_id,host_user_id,join_code,play_duration_seconds) VALUES(?,?,?,?)')->execute([$sid,$hostUserId,$code,$duration]);$gid=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gid,$hostUserId]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        return self::lobbyState($gid,$hostUserId);
    }
    public static function joinByCode(int $userId,string $code): array
    {
        $code=strtoupper(preg_replace('/[^A-Z0-9]/','',trim($code)));$pdo=db();$pdo->beginTransaction();try{
            $q=$pdo->prepare('SELECT g.game_id,p.max_players FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE g.join_code=? AND g.status="LOBBY" AND g.deleted_at_utc IS NULL FOR UPDATE');$q->execute([$code]);$game=$q->fetch();if(!$game)throw new RuntimeException('That game code is invalid or the game has already started.');$gid=(int)$game['game_id'];
            $q=$pdo->prepare('SELECT 1 FROM game_players WHERE game_id=? AND user_id=?');$q->execute([$gid,$userId]);if(!$q->fetchColumn()){$q=$pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id=?');$q->execute([$gid]);if((int)$q->fetchColumn()>=(int)$game['max_players'])throw new RuntimeException('That game lobby is full.');$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gid,$userId]);}
            $pdo->commit();
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}return self::lobbyState($gid,$userId);
    }
    public static function lobbyState(int $gameId,int $userId): array
    {
        $q=db()->prepare('SELECT g.*,s.status simulation_status,s.elapsed_seconds,s.duration_seconds,s.current_phase,p.plot_id,p.code plot_code,p.title,p.genre,p.setting_name,p.scenario_text,u.display_name host_name FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id JOIN game_players mine ON mine.game_id=g.game_id AND mine.user_id=? WHERE g.game_id=? AND g.deleted_at_utc IS NULL');$q->execute([$userId,$gameId]);$game=$q->fetch();if(!$game)throw new RuntimeException('You are not a member of this game.');
        $c=db()->prepare('SELECT c.character_id,c.name,c.description,c.private_knowledge,c.goal_text,c.fear_text,c.current_location,c.controller_type,c.actor_type,c.slot_no,pr.role_code,pr.mystery_character,gp.user_id,pu.display_name player_name FROM characters c LEFT JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id LEFT JOIN game_players gp ON gp.game_id=? AND gp.character_id=c.character_id LEFT JOIN users pu ON pu.user_id=gp.user_id WHERE c.simulation_id=? ORDER BY CASE WHEN c.actor_type="PLAYER" THEN 0 ELSE 1 END,c.slot_no');$c->execute([$gameId,$game['simulation_id']]);
        $p=db()->prepare('SELECT gp.user_id,gp.character_id,gp.joined_at_utc,gp.last_seen_at_utc,u.display_name,u.profile_image_path FROM game_players gp JOIN users u ON u.user_id=gp.user_id WHERE gp.game_id=? ORDER BY gp.joined_at_utc,gp.game_player_id');$p->execute([$gameId]);
        db()->prepare('UPDATE game_players SET last_seen_at_utc=UTC_TIMESTAMP() WHERE game_id=? AND user_id=?')->execute([$gameId,$userId]);
        $characters=$c->fetchAll();$players=$p->fetchAll();$claimed=0;foreach($players as$player)if(!empty($player['character_id']))$claimed++;
        return['game'=>$game,'characters'=>$characters,'players'=>$players,'is_host'=>(int)$game['host_user_id']===$userId,'can_delete'=>(int)$game['host_user_id']===$userId&&$game['status']==='LOBBY'&&$claimed===0];
    }
    public static function claimCharacter(int $gameId,int $userId,int $characterId): array
    {
        $pdo=db();$pdo->beginTransaction();try{
            $q=$pdo->prepare('SELECT g.status,g.simulation_id FROM games g JOIN game_players gp ON gp.game_id=g.game_id AND gp.user_id=? WHERE g.game_id=? AND g.deleted_at_utc IS NULL FOR UPDATE');$q->execute([$userId,$gameId]);$game=$q->fetch();if(!$game||$game['status']!=='LOBBY')throw new RuntimeException('Characters can only be changed while the game is in the lobby.');
            $q=$pdo->prepare('SELECT character_id FROM characters WHERE character_id=? AND simulation_id=? FOR UPDATE');$q->execute([$characterId,$game['simulation_id']]);if(!$q->fetch())throw new RuntimeException('That character is not part of this game.');
            $q=$pdo->prepare('SELECT user_id FROM game_players WHERE game_id=? AND character_id=? FOR UPDATE');$q->execute([$gameId,$characterId]);$owner=(int)$q->fetchColumn();if($owner&&$owner!==$userId)throw new RuntimeException('Another player already chose that character.');
            $old=$pdo->prepare('SELECT character_id FROM game_players WHERE game_id=? AND user_id=? FOR UPDATE');$old->execute([$gameId,$userId]);$oldId=(int)$old->fetchColumn();
            if($oldId&&$oldId!==$characterId)$pdo->prepare('UPDATE characters SET controller_type="AI" WHERE character_id=?')->execute([$oldId]);
            $pdo->prepare('UPDATE game_players SET character_id=?,last_seen_at_utc=UTC_TIMESTAMP() WHERE game_id=? AND user_id=?')->execute([$characterId,$gameId,$userId]);
            $pdo->prepare('UPDATE characters SET controller_type="HUMAN" WHERE character_id=?')->execute([$characterId]);$pdo->commit();
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        return self::lobbyState($gameId,$userId);
    }
    public static function releaseCharacter(int $gameId,int $userId): array
    {
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT gp.character_id,g.status FROM game_players gp JOIN games g ON g.game_id=gp.game_id WHERE gp.game_id=? AND gp.user_id=? AND g.deleted_at_utc IS NULL FOR UPDATE');$q->execute([$gameId,$userId]);$r=$q->fetch();if(!$r||$r['status']!=='LOBBY')throw new RuntimeException('Characters can only be changed in the lobby.');if($r['character_id'])$pdo->prepare('UPDATE characters SET controller_type="AI" WHERE character_id=?')->execute([$r['character_id']]);$pdo->prepare('UPDATE game_players SET character_id=NULL WHERE game_id=? AND user_id=?')->execute([$gameId,$userId]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}return self::lobbyState($gameId,$userId);
    }
    public static function startGame(int $gameId,int $userId): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM games WHERE game_id=? AND deleted_at_utc IS NULL');$q->execute([$gameId]);$g=$q->fetch();if(!$g||(int)$g['host_user_id']!==$userId)throw new RuntimeException('Only the host can start this game.');if($g['status']!=='LOBBY')throw new RuntimeException('This game has already started.');
        $q=$pdo->prepare('SELECT COUNT(*) total_players,SUM(gp.character_id IS NOT NULL) ready_players FROM game_players gp WHERE gp.game_id=? AND gp.user_id<>?');$q->execute([$gameId,(int)$g['host_user_id']]);$ready=$q->fetch();if((int)$ready['total_players']<1||(int)$ready['ready_players']!==(int)$ready['total_players'])throw new RuntimeException('Every player in the lobby must choose a character before the game starts.');
        $pdo->beginTransaction();try{$pdo->prepare('UPDATE games SET status="RUNNING" WHERE game_id=? AND status="LOBBY"')->execute([$gameId]);SimulationService::start((int)$g['simulation_id']);CharacterAutomationService::process((int)$g['simulation_id']);$pdo->prepare('UPDATE games SET started_at_utc=UTC_TIMESTAMP() WHERE game_id=?')->execute([$gameId]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function tick(int $gameId): void
    {
        $pdo=db();$lock='toinky_game_'.$gameId;$q=$pdo->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((int)$q->fetchColumn()!==1)return;
        try{$q=$pdo->prepare('SELECT g.status,g.started_at_utc,g.play_duration_seconds,g.simulation_id,s.elapsed_seconds,s.duration_seconds FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id WHERE g.game_id=?');$q->execute([$gameId]);$g=$q->fetch();if(!$g||$g['status']!=='RUNNING')return;
            $playDuration=max(1,(int)($g['play_duration_seconds']??$g['duration_seconds']));$storyDuration=max(1,(int)$g['duration_seconds']);$wallElapsed=min($playDuration,max(0,time()-strtotime((string)$g['started_at_utc'].' UTC')));$target=min($storyDuration,(int)floor($wallElapsed*$storyDuration/$playDuration));$elapsed=(int)$g['elapsed_seconds'];
            while($elapsed<$target){$step=min(30,$target-$elapsed);SimulationService::advance((int)$g['simulation_id'],$step);CharacterAutomationService::process((int)$g['simulation_id']);$elapsed+=$step;}
            if($wallElapsed>=$playDuration||$elapsed>=$storyDuration)$pdo->prepare('UPDATE games SET status="COMPLETE",completed_at_utc=UTC_TIMESTAMP() WHERE game_id=?')->execute([$gameId]);
        }finally{$pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);}
    }
    public static function choose(int $gameId,int $userId,int $decisionId,string $optionCode): void
    {
        $q=db()->prepare('SELECT g.simulation_id,g.status,gp.character_id AS player_character_id,d.character_id AS decision_character_id FROM games g JOIN game_players gp ON gp.game_id=g.game_id AND gp.user_id=? JOIN decisions d ON d.decision_id=? AND d.simulation_id=g.simulation_id WHERE g.game_id=?');$q->execute([$userId,$decisionId,$gameId]);$r=$q->fetch();
        if(!$r||$r['status']!=='RUNNING'||(int)$r['player_character_id']!==(int)$r['decision_character_id'])throw new RuntimeException('This decision does not belong to your selected character.');
        BehaviorService::chooseControlled((int)$r['simulation_id'],$decisionId,$optionCode);CharacterAutomationService::process((int)$r['simulation_id']);
    }
    public static function playerCharacter(int $gameId,int $userId): ?array
    {
        $q=db()->prepare('SELECT c.* FROM game_players gp JOIN characters c ON c.character_id=gp.character_id WHERE gp.game_id=? AND gp.user_id=?');$q->execute([$gameId,$userId]);$r=$q->fetch();return$r?:null;
    }
    public static function rate(int $gameId,int $userId,int $rating,string $review=''): void
    {
        if($rating<1||$rating>5)throw new RuntimeException('Rating must be between 1 and 5.');$q=db()->prepare('SELECT g.status FROM games g JOIN game_players gp ON gp.game_id=g.game_id AND gp.user_id=? WHERE g.game_id=?');$q->execute([$userId,$gameId]);if($q->fetchColumn()!=='COMPLETE')throw new RuntimeException('Complete the game before rating it.');
        db()->prepare('INSERT INTO story_ratings(game_id,user_id,rating,review_text) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE rating=VALUES(rating),review_text=VALUES(review_text),updated_at_utc=UTC_TIMESTAMP()')->execute([$gameId,$userId,$rating,trim($review)?:null]);
    }
    public static function userGames(int $userId): array
    {
        $q=db()->prepare('SELECT g.game_id,g.join_code,g.status,g.host_user_id,g.created_at_utc,p.title,p.genre,u.display_name host_name,gp.character_id,c.name character_name,sr.rating,(SELECT COALESCE(wma.thumbnail_path,pma.file_path) FROM plot_media_assets pma LEFT JOIN writer_media_assets wma ON wma.media_id=pma.library_media_id AND wma.active=1 WHERE pma.plot_id=p.plot_id AND pma.role_code=pr.role_code AND pma.placement="CHARACTER" AND pma.active=1 ORDER BY pma.sort_order,pma.media_asset_id LIMIT 1) character_image_path,(SELECT COUNT(*) FROM game_players claimed WHERE claimed.game_id=g.game_id AND claimed.character_id IS NOT NULL) claimed_count FROM game_players gp JOIN games g ON g.game_id=gp.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id LEFT JOIN characters c ON c.character_id=gp.character_id LEFT JOIN plot_roles pr ON pr.plot_role_id=c.plot_role_id LEFT JOIN story_ratings sr ON sr.game_id=g.game_id AND sr.user_id=gp.user_id WHERE gp.user_id=? AND g.deleted_at_utc IS NULL AND g.deactivated_at_utc IS NULL ORDER BY g.created_at_utc DESC');$q->execute([$userId]);$games=$q->fetchAll();foreach($games as&$game)$game['can_delete']=(int)$game['host_user_id']===$userId&&$game['status']==='LOBBY'&&(int)$game['claimed_count']===0;unset($game);return$games;
    }
    public static function deleteLobby(int $gameId,int $userId): void
    {
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT host_user_id,status FROM games WHERE game_id=? AND deleted_at_utc IS NULL FOR UPDATE');$q->execute([$gameId]);$game=$q->fetch();if(!$game||(int)$game['host_user_id']!==$userId)throw new RuntimeException('Only the host can delete this lobby.');if($game['status']!=='LOBBY')throw new RuntimeException('Only games still in the lobby can be deleted.');$q=$pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id=? AND character_id IS NOT NULL');$q->execute([$gameId]);if((int)$q->fetchColumn()>0)throw new RuntimeException('This lobby cannot be deleted after a character has been selected.');$pdo->prepare('UPDATE games SET deleted_at_utc=UTC_TIMESTAMP(),deleted_by_user_id=? WHERE game_id=? AND deleted_at_utc IS NULL')->execute([$userId,$gameId]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function adminDeleteLobby(int $gameId,int $adminUserId): void
    {
        self::requireAdminUser($adminUserId);
        $pdo=db();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT status FROM games WHERE game_id=? AND deleted_at_utc IS NULL FOR UPDATE');$q->execute([$gameId]);$game=$q->fetch();if(!$game)throw new RuntimeException('That game was not found.');if($game['status']!=='LOBBY')throw new RuntimeException('Only games still in the lobby can be deleted.');$q=$pdo->prepare('SELECT COUNT(*) FROM game_players WHERE game_id=? AND character_id IS NOT NULL');$q->execute([$gameId]);if((int)$q->fetchColumn()>0)throw new RuntimeException('This lobby cannot be deleted after a character has been selected. Take it over instead.');$pdo->prepare('UPDATE games SET deleted_at_utc=UTC_TIMESTAMP(),deleted_by_user_id=? WHERE game_id=? AND deleted_at_utc IS NULL')->execute([$adminUserId,$gameId]);$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    public static function takeoverGame(int $gameId,int $adminUserId): array
    {
        self::requireAdminUser($adminUserId);
        $pdo=db();$pdo->beginTransaction();try{
            $q=$pdo->prepare('SELECT g.host_user_id,g.status,g.join_code,p.title,u.display_name host_name,u.email host_email FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id WHERE g.game_id=? AND g.deleted_at_utc IS NULL AND g.deactivated_at_utc IS NULL FOR UPDATE');$q->execute([$gameId]);$game=$q->fetch();
            if(!$game)throw new RuntimeException('That game was not found.');
            if(!in_array($game['status'],['LOBBY','RUNNING'],true))throw new RuntimeException('Only an unstarted or unfinished game can be taken over.');
            if((int)$game['host_user_id']===$adminUserId)throw new RuntimeException('You already host this game.');
            $pdo->prepare('UPDATE games SET host_user_id=? WHERE game_id=?')->execute([$adminUserId,$gameId]);
            $q=$pdo->prepare('SELECT 1 FROM game_players WHERE game_id=? AND user_id=?');$q->execute([$gameId,$adminUserId]);if(!$q->fetchColumn())$pdo->prepare('INSERT INTO game_players(game_id,user_id) VALUES(?,?)')->execute([$gameId,$adminUserId]);
            $pdo->commit();
        }catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
        return['title'=>(string)$game['title'],'join_code'=>(string)$game['join_code'],'host_name'=>(string)$game['host_name'],'host_email'=>(string)$game['host_email']];
    }
    public static function deactivateGame(int $gameId,int $adminUserId): void
    {
        self::requireAdminUser($adminUserId);
        $q=db()->prepare('SELECT status,deactivated_at_utc FROM games WHERE game_id=? AND deleted_at_utc IS NULL');$q->execute([$gameId]);$game=$q->fetch();if(!$game)throw new RuntimeException('That game was not found.');if($game['status']!=='COMPLETE')throw new RuntimeException('Only completed games can be deactivated.');if($game['deactivated_at_utc'])throw new RuntimeException('That game is already archived.');
        db()->prepare('UPDATE games SET deactivated_at_utc=UTC_TIMESTAMP(),deactivated_by_user_id=? WHERE game_id=?')->execute([$adminUserId,$gameId]);
    }
    public static function reactivateGame(int $gameId,int $adminUserId): void
    {
        self::requireAdminUser($adminUserId);
        db()->prepare('UPDATE games SET deactivated_at_utc=NULL,deactivated_by_user_id=NULL WHERE game_id=? AND deactivated_at_utc IS NOT NULL')->execute([$gameId]);
    }
    private static function requireAdminUser(int $userId): void
    {
        $q=db()->prepare('SELECT is_admin FROM users WHERE user_id=?');$q->execute([$userId]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Administrator access is required.');
    }
    private static function newCode(): string
    {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';do{$code='';for($i=0;$i<6;$i++)$code.=$alphabet[random_int(0,strlen($alphabet)-1)];$q=db()->prepare('SELECT 1 FROM games WHERE join_code=?');$q->execute([$code]);}while($q->fetchColumn());return$code;
    }
}
