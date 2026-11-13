<?php
require_once __DIR__.'/PrivacyService.php';
final class PrivacyDataService
{
    /** Stream safe datasets so large histories do not require one giant JSON object. */
    public static function streamExport(int $id,string $password,callable $write): void
    {
        PrivacyService::confirmPassword($id,$password);$pdo=db();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT user_id,email,display_name,profile_image_path,status,email_verified_at_utc,last_login_at_utc,created_at_utc,terms_version,privacy_version,policy_accepted_at_utc FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL');$q->execute([$id]);$profile=$q->fetch();$q->closeCursor();if(!$profile)throw new PrivacyException('Your account is not available.');
            $json=static fn(mixed $value):string=>json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            $write('{"export_version":1,"exported_at_utc":'.$json(gmdate('c')).',"profile":'.$json($profile).',"datasets":{');$first=true;
            foreach(self::exportQueries() as $name=>$spec){
                if(!$first)$write(',');$first=false;$write($json($name).':[');$rowFirst=true;
                foreach(self::rows($spec[0],array_fill(0,$spec[1],$id)) as $row){if(!$rowFirst)$write(',');$rowFirst=false;$write($json($row));}
                $write(']');
            }
            $write(',"authored_story_content":[');$ids=$pdo->prepare('SELECT plot_id FROM plots WHERE author_user_id=? ORDER BY plot_id');$ids->execute([$id]);$plotIds=$ids->fetchAll(PDO::FETCH_COLUMN);$ids->closeCursor();$first=true;
            require_once __DIR__.'/StoryAuthoringService.php';
            foreach($plotIds as $plotId){if(!$first)$write(',');$first=false;$write($json(['plot_id'=>(int)$plotId,'workspace'=>self::safeStoryContent(StoryAuthoringService::workspace((int)$plotId))]));}
            $write(']}}');$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function exportQueries(): array
    {
        return [
            'games'=>['SELECT g.game_id,g.status,g.play_duration_seconds,g.started_at_utc,g.completed_at_utc,p.title,gp.character_id,gp.joined_at_utc,gp.last_seen_at_utc,gp.archived_at_utc FROM game_players gp JOIN games g ON g.game_id=gp.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE gp.user_id=? ORDER BY g.game_id',1],
            'hosted_games'=>['SELECT game_id,status,play_duration_seconds,started_at_utc,completed_at_utc,created_at_utc FROM games WHERE host_user_id=? ORDER BY game_id',1],
            'own_character_history'=>['SELECT h.simulation_id,h.character_id,h.event_second,h.history_type,h.title,h.detail_text,h.created_at_utc FROM character_history h JOIN game_players gp ON gp.character_id=h.character_id JOIN games g ON g.game_id=gp.game_id AND g.simulation_id=h.simulation_id WHERE gp.user_id=? ORDER BY h.history_id',1],
            'own_decisions'=>['SELECT d.decision_id,d.simulation_id,d.character_id,d.prompt_text,d.status,d.opens_at_second,d.closes_at_second,d.chosen_option_code,d.resolved_text FROM decisions d JOIN game_players gp ON gp.character_id=d.character_id JOIN games g ON g.game_id=gp.game_id AND g.simulation_id=d.simulation_id WHERE gp.user_id=? ORDER BY d.decision_id',1],
            'ratings'=>['SELECT game_id,rating,review_text,created_at_utc,updated_at_utc FROM story_ratings WHERE user_id=? ORDER BY story_rating_id',1],
            'friends'=>['SELECT f.friendship_id,f.status,f.created_at_utc,f.responded_at_utc,u.display_name,l.label_text FROM user_friendships f JOIN users u ON u.user_id=IF(f.user_low_id=?,f.user_high_id,f.user_low_id) LEFT JOIN user_friend_labels l ON l.friendship_id=f.friendship_id AND l.user_id=? WHERE f.user_low_id=? OR f.user_high_id=? ORDER BY f.friendship_id',4],
            'account_invitations'=>['SELECT invitation_id,email,expires_at_utc,accepted_at_utc,created_at_utc,delivered_at_utc FROM user_invitations WHERE invited_by_user_id=? OR EXISTS(SELECT 1 FROM user_email_history h WHERE h.user_id=? AND h.email=user_invitations.email AND user_invitations.created_at_utc>=h.valid_from_utc AND (h.valid_until_utc IS NULL OR user_invitations.created_at_utc<h.valid_until_utc)) ORDER BY invitation_id',2],
            'game_invitations'=>['SELECT game_invitation_id,game_id,email,expires_at_utc,accepted_at_utc,created_at_utc,delivered_at_utc FROM game_invitations WHERE invited_by_user_id=? OR accepted_by_user_id=? OR EXISTS(SELECT 1 FROM user_email_history h WHERE h.user_id=? AND h.email=game_invitations.email AND game_invitations.created_at_utc>=h.valid_from_utc AND (h.valid_until_utc IS NULL OR game_invitations.created_at_utc<h.valid_until_utc)) ORDER BY game_invitation_id',3],
            'purchases'=>['SELECT purchase_id,stripe_checkout_session_id,stripe_payment_intent_id,bundle_key,occasions_granted,amount_cents,currency,status,created_at_utc,paid_at_utc FROM host_purchases WHERE user_id=? ORDER BY purchase_id',1],
            'occasion_ledger'=>['SELECT ledger_id,entry_type,occasions,game_id,purchase_id,created_at_utc FROM host_occasion_ledger WHERE user_id=? ORDER BY ledger_id',1],
            'stories'=>['SELECT plot_id,code,title,archetype,genre,setting_name,scenario_text,status,workflow_status,created_at_utc,updated_at_utc FROM plots WHERE author_user_id=? ORDER BY plot_id',1],
            'guided_drafts'=>['SELECT guide_draft_id,plot_id,state_json,created_at_utc,updated_at_utc FROM inky_story_guide_drafts WHERE author_user_id=? ORDER BY guide_draft_id',1],
            'media'=>['SELECT media_id,media_type,title_text,alt_text,file_path,thumbnail_path,mime_type,width_px,height_px,file_size_bytes,created_at_utc FROM writer_media_assets WHERE uploaded_by_user_id=? ORDER BY media_id',1],
            'media_versions'=>['SELECT v.media_id,v.version_code,v.file_path,v.mime_type,v.width_px,v.height_px,v.created_at_utc FROM writer_media_versions v JOIN writer_media_assets m ON m.media_id=v.media_id WHERE m.uploaded_by_user_id=? ORDER BY v.media_version_id',1],
            'consents'=>['SELECT terms_version,privacy_version,accepted_at_utc FROM user_policy_acceptances WHERE user_id=? ORDER BY policy_acceptance_id',1],
            'email_history'=>['SELECT email,valid_from_utc,valid_until_utc FROM user_email_history WHERE user_id=? ORDER BY valid_from_utc,email',1],
            'roles'=>['SELECT r.role_code,r.label_text,u.assigned_at_utc FROM user_platform_roles u JOIN platform_roles r ON r.platform_role_id=u.platform_role_id WHERE u.user_id=? ORDER BY r.role_code',1],
            'inky_preferences'=>['SELECT enabled,companion_name_root,character_code,size_code,dock_position,anchor_position,sounds_enabled,animation_level,proactive_help,updated_at_utc FROM user_inky_preferences WHERE user_id=?',1],
            'writer_requests'=>['SELECT request_text,status,created_at_utc,reviewed_at_utc FROM writer_access_requests WHERE user_id=? ORDER BY writer_request_id',1],
            'email_delivery'=>['SELECT message_type,delivery_status,created_at_utc FROM email_delivery_log WHERE sent_by_user_id=? OR EXISTS(SELECT 1 FROM user_email_history h WHERE h.user_id=? AND h.email=email_delivery_log.recipient_email AND email_delivery_log.created_at_utc>=h.valid_from_utc AND (h.valid_until_utc IS NULL OR email_delivery_log.created_at_utc<h.valid_until_utc)) ORDER BY email_delivery_id',2],
            'security_activity'=>['SELECT event_code,outcome,created_at_utc FROM account_security_audit WHERE target_user_id=? ORDER BY audit_id',1],
            // Raw AI context can contain other characters' private information. Request
            // redacted personal text through the contact route instead of leaking it here.
            'ai_activity'=>['SELECT purpose,provider,model_name,request_started_at_utc,request_completed_at_utc,success,fallback_used,http_status,prompt_chars,response_chars,total_token_count FROM ai_reasoner_interactions WHERE actor_user_id=? ORDER BY ai_interaction_id',1],
        ];
    }
    private static function rows(string $sql,array $params): Generator
    {
        $pdo=db();$buffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);$q=null;
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
        try{$q=$pdo->prepare($sql);$q->execute($params);while($row=$q->fetch())yield $row;}
        finally{if($q)$q->closeCursor();$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);}
    }
    private static function safeStoryContent(array $content): array
    {
        foreach($content as $key=>&$value){if(is_string($key)&&(str_ends_with($key,'user_id')||in_array($key,['password_hash','token_hash','review_notes','reviewer_notes'],true))){unset($content[$key]);continue;}if(is_array($value))$value=self::safeStoryContent($value);}unset($value);return $content;
    }
    public static function eraseAccount(int $id,string $password): void
    {
        $user=PrivacyService::confirmPassword($id,$password);if((int)$user['is_super_admin']===1)throw new PrivacyException('Transfer Super Admin ownership through the privacy contact before deleting this account.');
        self::eraseIdentity($id,$user['password_hash']);
    }
    public static function eraseDisabled(int $id): void
    {
        if(PHP_SAPI!=='cli')throw new PrivacyException('Scheduled erasure is CLI only.');
        if(!PrivacyService::approved()||!self::disabledEligible($id))throw new PrivacyException('Account is not eligible for scheduled erasure.');
        self::eraseIdentity($id,null);
    }
    private static function disabledEligible(int $id): bool
    {
        $q=db()->prepare('SELECT 1 FROM users u WHERE user_id=? AND status="DISABLED" AND is_super_admin=0 AND system_identity_key IS NULL AND (privacy_erasure_hold_until_utc IS NULL OR privacy_erasure_hold_until_utc<UTC_TIMESTAMP()) AND updated_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY) AND EXISTS(SELECT 1 FROM user_policy_acceptances a WHERE a.user_id=u.user_id)');$q->execute([$id,(int)PrivacyService::config()['disabled_account_days']]);return (bool)$q->fetchColumn();
    }
    public static function withdrawConsent(int $id,string $password): void
    {
        $user=PrivacyService::confirmPassword($id,$password);if((int)$user['is_super_admin']===1)throw new PrivacyException('Transfer Super Admin ownership through the privacy contact before disabling this account.');
        $q=db()->prepare('UPDATE users SET status="DISABLED",session_version=session_version+1,policy_accepted_at_utc=NULL,updated_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND password_hash=? AND status="ACTIVE"');$q->execute([$id,$user['password_hash']]);if($q->rowCount()!==1)throw new PrivacyException('Your account changed. Sign in again.');
    }
    private static function eraseIdentity(int $id,?string $expectedHash): void
    {
        $pdo=db();$staged=[];$pdo->beginTransaction();
        try{
            $q=$pdo->prepare('SELECT * FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);$user=$q->fetch();
            if(!$user||$user['system_identity_key']!==null||(int)$user['is_super_admin']===1||($expectedHash!==null&&!hash_equals($expectedHash,(string)$user['password_hash']))||($expectedHash===null&&$user['status']!=='DISABLED'))throw new PrivacyException('Your account changed. Contact the privacy address for help.');
            if(!empty($user['privacy_erasure_hold_until_utc'])&&strtotime($user['privacy_erasure_hold_until_utc'].' UTC')>=time())throw new PrivacyException('Your account needs a privacy review before erasure. Contact accounts@toinky.com for the reason and review.');
            if($expectedHash===null&&!self::disabledEligible($id))throw new PrivacyException('Account is no longer eligible for scheduled erasure.');
            $anon=(int)$pdo->query('SELECT user_id FROM users WHERE system_identity_key="PRIVACY_ANONYMOUS" AND status="DISABLED"')->fetchColumn();if(!$anon)throw new PrivacyException('Account erasure is not configured.');
            $refs=self::identityReferences();$staged=self::stageProfileFiles($id,(string)($user['profile_image_path']??''));
            $emails=$pdo->prepare('SELECT email,valid_from_utc,valid_until_utc FROM user_email_history WHERE user_id=?');$emails->execute([$id]);$bindings=$emails->fetchAll();$emails->closeCursor();
            $currentRecorded=false;foreach($bindings as $binding)if(strcasecmp($binding['email'],(string)$user['email'])===0&&$binding['valid_until_utc']===null)$currentRecorded=true;
            if(!$currentRecorded)$bindings[]=['email'=>(string)$user['email'],'valid_from_utc'=>gmdate('Y-m-d H:i:s'),'valid_until_utc'=>null];
            foreach($bindings as $binding){
                $email=$binding['email'];$from=$binding['valid_from_utc'];$until=$binding['valid_until_utc'];
                foreach(['user_invitations'=>'email','game_invitations'=>'email','email_delivery_log'=>'recipient_email'] as $table=>$column)$pdo->prepare('DELETE FROM `'.$table.'` WHERE `'.$column.'`=? AND created_at_utc>=? AND (? IS NULL OR created_at_utc<?)')->execute([$email,$from,$until,$until]);
                $other=$pdo->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');$other->execute([$email,$id]);
                if(!$other->fetchColumn())foreach(['SIGN_IN_ACCOUNT','RESET_EMAIL','INVITE_RECIPIENT'] as $action)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([RateLimitService::key($action,strtolower(trim($email)))]);
            }
            $tokens=$pdo->prepare('SELECT token_hash FROM password_reset_tokens WHERE user_id=?');$tokens->execute([$id]);foreach($tokens->fetchAll(PDO::FETCH_COLUMN) as $hash)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([RateLimitService::key('RESET_TOKEN',$hash)]);
            foreach(['ADMIN_RESET_USER','EMAIL_CHANGE_USER','FRIEND_REQUEST_USER','VERIFY_EMAIL_USER','AI_USER','JOIN_USER','SIGNIN_CODE_USER','CODE_RESEND_USER','PRIVACY_PASSWORD_USER'] as $action)$pdo->prepare('DELETE FROM auth_throttle WHERE throttle_key=?')->execute([RateLimitService::key($action,(string)$id)]);
            $pdo->prepare('DELETE FROM user_friendships WHERE user_low_id=? OR user_high_id=? OR requested_by_user_id=?')->execute([$id,$id,$id]);
            foreach(['password_reset_tokens'=>'user_id','user_email_change_tokens'=>'user_id','user_email_verification_tokens'=>'user_id','user_two_factor_codes'=>'user_id','user_policy_acceptances'=>'user_id','user_email_history'=>'user_id','user_inky_preferences'=>'user_id','user_platform_roles'=>'user_id','user_friend_labels'=>'user_id','game_players'=>'user_id','story_ratings'=>'user_id','inky_story_guide_drafts'=>'author_user_id','writer_access_requests'=>'user_id','user_invitations'=>'invited_by_user_id','email_delivery_log'=>'sent_by_user_id'] as $table=>$column)$pdo->prepare('DELETE FROM `'.$table.'` WHERE `'.$column.'`=?')->execute([$id]);
            $pdo->prepare('DELETE FROM game_invitations WHERE invited_by_user_id=? OR accepted_by_user_id=?')->execute([$id,$id]);
            $pdo->prepare('DELETE FROM account_security_audit WHERE actor_user_id=? OR target_user_id=?')->execute([$id,$id]);
            $pdo->prepare('UPDATE host_purchases SET stripe_checkout_session_id=NULL,stripe_payment_intent_id=NULL WHERE user_id=?')->execute([$id]);
            // free_first_key is generated from entry_type and user_id. Preserve
            // the grant, while allowing many erased accounts to share one actor.
            $pdo->prepare('UPDATE host_occasion_ledger SET note="Account identity erased",entry_type=IF(entry_type="FREE_FIRST","FREE_FIRST_ERASED",entry_type) WHERE user_id=?')->execute([$id]);
            $pdo->prepare('UPDATE host_occasion_ledger SET note="Creator identity erased" WHERE created_by_user_id=? AND user_id<>?')->execute([$id,$id]);
            // Legacy AI text has no reliable actor mapping. Remove it conservatively;
            // keep non-identifying usage metrics and all other attributed users' text.
            $pdo->prepare('UPDATE ai_reasoner_interactions SET actor_user_id=NULL,actor_attribution_version=1,request_json=NULL,response_json=NULL,parsed_response_json=NULL,reason_text=NULL,error_text=NULL,selected_action_code=NULL WHERE actor_user_id=? OR actor_attribution_version=0')->execute([$id]);
            $pdo->prepare('UPDATE plots SET author_user_id=? WHERE author_user_id=?')->execute([$anon,$id]);
            $pdo->prepare('UPDATE site_parallax_layers SET updated_by_user_id=NULL WHERE updated_by_user_id=?')->execute([$id]);
            foreach(['deleted_by_user_id','deactivated_by_user_id'] as $column)$pdo->prepare('UPDATE games SET `'.$column.'`=NULL WHERE `'.$column.'`=?')->execute([$id]);
            // Scrub actor IDs in serialized snapshots without rewriting shared fiction.
            foreach(['plot_versions'=>['plot_version_id','snapshot_json'],'story_correction_imports'=>['story_correction_import_id','package_json']] as $table=>[$pk,$column]){
                $rows=$pdo->query('SELECT `'.$pk.'`,`'.$column.'` FROM `'.$table.'`')->fetchAll();foreach($rows as $row){$value=json_decode((string)$row[$column],true);if(!is_array($value))continue;$changed=false;$value=self::scrubActorIds($value,$id,$anon,$changed);if($changed)$pdo->prepare('UPDATE `'.$table.'` SET `'.$column.'`=? WHERE `'.$pk.'`=?')->execute([json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$row[$pk]]);}
            }
            foreach($refs as $ref){$table=$ref['TABLE_NAME'];$column=$ref['COLUMN_NAME'];$value=$ref['IS_NULLABLE']==='YES'?null:$anon;$pdo->prepare('UPDATE `'.$table.'` SET `'.$column.'`=? WHERE `'.$column.'`=?')->execute([$value,$id]);}
            $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$id]);$pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($staged as [$source,$temporary])if(is_file($temporary))rename($temporary,$source);throw $e;}
        foreach($staged as [$source,$temporary])if(is_file($temporary)&&!unlink($temporary))Logger::warning('Private profile-photo erasure staging requires operator cleanup.');
    }
    private static function identityReferences(): array
    {
        $allowed=['story_ratings.user_id','email_delivery_log.sent_by_user_id','email_templates.updated_by_user_id','games.host_user_id','game_invitations.accepted_by_user_id','game_invitations.invited_by_user_id','game_players.user_id','genre_themes.updated_by_user_id','host_occasion_ledger.created_by_user_id','host_occasion_ledger.user_id','host_purchases.user_id','inky_story_guide_drafts.author_user_id','password_reset_tokens.user_id','platform_settings.updated_by_user_id','plot_media_assets.uploaded_by_user_id','plot_versions.reviewed_by_user_id','plot_versions.submitted_by_user_id','simulation_review_batches.requested_by_user_id','site_page_sections.updated_by_user_id','story_audit_runs.requested_by_user_id','story_correction_imports.imported_by_user_id','user_email_change_tokens.user_id','user_email_verification_tokens.user_id','user_friendships.requested_by_user_id','user_friendships.user_high_id','user_friendships.user_low_id','user_friend_labels.user_id','user_inky_preferences.user_id','user_invitations.invited_by_user_id','user_platform_roles.assigned_by_user_id','user_platform_roles.user_id','user_two_factor_codes.user_id','writer_access_requests.reviewed_by_user_id','writer_access_requests.user_id','writer_media_assets.uploaded_by_user_id','user_policy_acceptances.user_id','user_email_history.user_id','ai_reasoner_interactions.actor_user_id'];
        $refs=db()->query('SELECT k.TABLE_NAME,k.COLUMN_NAME,c.IS_NULLABLE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=k.TABLE_SCHEMA AND c.TABLE_NAME=k.TABLE_NAME AND c.COLUMN_NAME=k.COLUMN_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME="users"')->fetchAll();
        foreach($refs as $ref)if(!in_array($ref['TABLE_NAME'].'.'.$ref['COLUMN_NAME'],$allowed,true))throw new PrivacyException('Account erasure needs a schema privacy review before it can proceed.');
        $withoutForeignKey=['users.user_id','plots.author_user_id','games.deleted_by_user_id','games.deactivated_by_user_id','site_parallax_layers.updated_by_user_id','account_security_audit.actor_user_id','account_security_audit.target_user_id','user_two_factor_codes.user_id'];
        $columns=db()->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND (COLUMN_NAME="user_id" OR RIGHT(COLUMN_NAME,8)="_user_id")')->fetchAll();
        foreach($columns as $column)if(!in_array($column['TABLE_NAME'].'.'.$column['COLUMN_NAME'],array_merge($allowed,$withoutForeignKey),true))throw new PrivacyException('Account erasure needs a schema privacy review before it can proceed.');
        return $refs;
    }
    private static function scrubActorIds(array $value,int $id,int $anon,bool &$changed): array
    {
        foreach($value as $key=>&$item){if(is_string($key)&&($key==='user_id'||str_ends_with($key,'_user_id'))&&(is_int($item)||is_string($item))&&(string)$item===(string)$id){$item=$anon;$changed=true;}elseif(is_array($item))$item=self::scrubActorIds($item,$id,$anon,$changed);}unset($item);return $value;
    }
    private static function stageProfileFiles(int $id,string $storedPath): array
    {
        $directory=dirname(__DIR__).'/public/uploads/profiles';$files=[];
        if($storedPath!==''&&!preg_match('~^public/uploads/profiles/user-'.preg_quote((string)$id,'~').'-[a-f0-9]{16}\.(?:png|jpg|webp)$~D',$storedPath))throw new PrivacyException('Your profile image needs manual privacy review before account deletion.');
        if(!is_dir($directory))return [];$root=realpath($directory);$publicRoot=realpath(dirname(__DIR__).'/public');if(!$root||!$publicRoot||$root!==$publicRoot.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'profiles'||is_link($directory))throw new PrivacyException('Profile storage needs operator review.');
        foreach(glob($directory.'/user-'.$id.'-*')?:[] as $file){$real=realpath($file);if(!$real||is_link($file)||!str_starts_with($real,$root.DIRECTORY_SEPARATOR)||!is_file($real))throw new PrivacyException('Profile storage needs operator review.');$files[]=$real;}
        $staged=[];try{foreach($files as $file){$temp=tempnam(sys_get_temp_dir(),'toinky-photo-erasure-');if($temp===false||!rename($file,$temp)){if($temp!==false&&is_file($temp))unlink($temp);throw new PrivacyException('Profile image could not be prepared for erasure.');}chmod($temp,0600);$staged[]=[$file,$temp];}return $staged;}catch(Throwable $e){foreach($staged as [$file,$temp])rename($temp,$file);throw $e;}
    }
}
