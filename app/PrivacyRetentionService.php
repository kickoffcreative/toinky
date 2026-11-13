<?php
require_once __DIR__.'/PrivacyDataService.php';
final class PrivacyRetentionService
{
    public static function run(bool $apply=false): array
    {
        if(PHP_SAPI!=='cli')throw new PrivacyException('Privacy retention is CLI only.');$pdo=db();$counts=[];
        $rules=[
            'user_sessions'=>'absolute_expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR revoked_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'user_totp_credentials'=>'enabled_at_utc IS NULL AND enrollment_expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)',
            'email_delivery_log'=>'created_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY)',
            'password_reset_tokens'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR used_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'user_invitations'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR accepted_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'game_invitations'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR accepted_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'user_email_change_tokens'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR verified_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'user_email_verification_tokens'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR used_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'user_two_factor_codes'=>'expires_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) OR used_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)',
            'auth_throttle'=>'window_start_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) AND (locked_until_utc IS NULL OR locked_until_utc<=UTC_TIMESTAMP())',
        ];
        foreach($rules as $table=>$predicate){$counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'` WHERE '.$predicate)->fetchColumn();if($apply)$pdo->exec('DELETE FROM `'.$table.'` WHERE '.$predicate);}
        $ai='request_started_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 90 DAY) AND (request_json IS NOT NULL OR response_json IS NOT NULL OR parsed_response_json IS NOT NULL OR reason_text IS NOT NULL OR error_text IS NOT NULL OR selected_action_code IS NOT NULL OR actor_user_id IS NOT NULL OR simulation_id IS NOT NULL OR character_id IS NOT NULL OR decision_id IS NOT NULL)';
        $counts['ai_text_and_identity_links']=(int)$pdo->query('SELECT COUNT(*) FROM ai_reasoner_interactions WHERE '.$ai)->fetchColumn();
        if($apply)$pdo->exec('UPDATE ai_reasoner_interactions SET request_json=NULL,response_json=NULL,parsed_response_json=NULL,reason_text=NULL,error_text=NULL,selected_action_code=NULL,actor_user_id=NULL,simulation_id=NULL,character_id=NULL,decision_id=NULL,actor_attribution_version=1 WHERE '.$ai);
        $q=$pdo->prepare('SELECT user_id FROM users u WHERE status="DISABLED" AND is_super_admin=0 AND system_identity_key IS NULL AND (privacy_erasure_hold_until_utc IS NULL OR privacy_erasure_hold_until_utc<UTC_TIMESTAMP()) AND updated_at_utc<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY) AND EXISTS(SELECT 1 FROM user_policy_acceptances a WHERE a.user_id=u.user_id)');$q->execute([(int)PrivacyService::config()['disabled_account_days']]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);$q->closeCursor();
        $counts['disabled_accounts_eligible']=count($ids);$counts['disabled_erasure_enabled']=PrivacyService::approved();$counts['disabled_accounts_erased']=0;
        if($apply&&PrivacyService::approved())foreach($ids as $id){PrivacyDataService::eraseDisabled((int)$id);$counts['disabled_accounts_erased']++;}
        return ['apply'=>$apply,'counts'=>$counts];
    }
}
