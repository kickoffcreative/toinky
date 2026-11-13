<?php
require_once __DIR__.'/UserFacingException.php';
final class PrivacyException extends UserFacingException {}
final class PrivacyService
{
    public const AGE_BLOCK_COOKIE='__Host-toinky-age-block';
    public static function config(): array{return require dirname(__DIR__).'/config/privacy.php';}
    public static function approved(): bool{return getenv('GAME2_PRIVACY_APPROVED')==='1';}
    public static function ageEligible(): bool
    {
        AuthService::boot();return empty($_COOKIE[self::AGE_BLOCK_COOKIE])&&(int)($_SESSION['privacy_age_eligible_until']??0)>=time();
    }
    public static function checkAge(string $birthDate,?DateTimeImmutable $today=null): void
    {
        AuthService::boot();if(!empty($_COOKIE[self::AGE_BLOCK_COOKIE]))throw new PrivacyException('Registration is unavailable in this browser for now.');
        $today??=new DateTimeImmutable('today',new DateTimeZone('UTC'));
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$birthDate,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$birthDate||$date>$today)throw new PrivacyException('Enter a valid birth date.');
        if($date->diff($today)->y<(int)self::config()['minimum_age']){
            unset($_SESSION['privacy_age_eligible_until']);
            if(PHP_SAPI!=='cli')setcookie(self::AGE_BLOCK_COOKIE,'1',['expires'=>time()+1800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
            $_COOKIE[self::AGE_BLOCK_COOKIE]='1';throw new PrivacyException('You are not eligible to register. The date you entered was not saved.');
        }
        // Retain only a short eligibility decision, never date of birth or exact age.
        $_SESSION['privacy_age_eligible_until']=time()+1800;
    }
    public static function requireRegistrationConsent(array $input): void
    {
        if(!self::approved())throw new PrivacyException('Registration is temporarily unavailable while our privacy and terms review is completed.');
        if(!self::ageEligible())throw new PrivacyException('Complete the age check before creating an account.');
        self::validateConsent($input);
    }
    public static function validateConsent(array $input): void
    {
        $cfg=self::config();
        if(($input['accept_policies']??null)!=='1')throw new PrivacyException('Review and accept the Terms and Privacy Policy to continue.');
        if(($input['terms_version']??null)!==$cfg['terms_version']||($input['privacy_version']??null)!==$cfg['privacy_version'])throw new PrivacyException('Our terms or privacy notice changed. Refresh and review the current versions.');
    }
    public static function recordConsent(int $id,array $input): void
    {
        if(!self::approved())throw new PrivacyException('The current policy documents are awaiting review.');self::validateConsent($input);$cfg=self::config();
        $pdo=db();$ownTransaction=!$pdo->inTransaction();if($ownTransaction)$pdo->beginTransaction();try{
        $q=$pdo->prepare('UPDATE users SET terms_version=?,privacy_version=?,policy_accepted_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL');$q->execute([$cfg['terms_version'],$cfg['privacy_version'],$id]);
        if(!$q->rowCount()){ $q=db()->prepare('SELECT 1 FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL');$q->execute([$id]);if(!$q->fetchColumn())throw new PrivacyException('Your account is not available.'); }
        db()->prepare('INSERT INTO user_policy_acceptances(user_id,terms_version,privacy_version) VALUES(?,?,?)')->execute([$id,$cfg['terms_version'],$cfg['privacy_version']]);
        if($ownTransaction)$pdo->commit();}catch(Throwable $e){if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function needsConsent(array $user): bool
    {
        if(!self::approved())return false;$cfg=self::config();return ($user['terms_version']??null)!==$cfg['terms_version']||($user['privacy_version']??null)!==$cfg['privacy_version']||empty($user['policy_accepted_at_utc']);
    }
    public static function consentFields(): string
    {
        $cfg=self::config();return '<input type="hidden" name="terms_version" value="'.e($cfg['terms_version']).'"><input type="hidden" name="privacy_version" value="'.e($cfg['privacy_version']).'"><label class="field"><span><input type="checkbox" name="accept_policies" value="1" required> I have read and accept the <a href="./?view=terms">Terms</a> and <a href="./?view=privacy">Privacy Policy</a>.</span></label>';
    }
    public static function confirmPassword(int $id,string $password): array
    {
        RateLimitService::hit('PRIVACY_PASSWORD_USER',(string)$id,5,900,900);
        $q=db()->prepare('SELECT user_id,email,password_hash,display_name,profile_image_path,is_super_admin,status,system_identity_key FROM users WHERE user_id=?');$q->execute([$id]);$user=$q->fetch();
        if(!$user||$user['status']!=='ACTIVE'||$user['system_identity_key']!==null||!password_verify($password,(string)$user['password_hash']))throw new PrivacyException('Your current password is incorrect.');
        return $user;
    }
    public static function rememberEmail(int $id,string $email): void
    {
        $email=strtolower(trim($email));$q=db()->prepare('SELECT 1 FROM user_email_history WHERE user_id=? AND email=? AND valid_until_utc IS NULL');$q->execute([$id,$email]);
        if(!$q->fetchColumn())db()->prepare('INSERT INTO user_email_history(user_id,email) VALUES(?,?)')->execute([$id,$email]);
    }
    public static function updateDisplayName(int $id,string $name): void
    {
        $name=trim($name);if(mb_strlen($name)<2||mb_strlen($name)>120)throw new PrivacyException('Display name must be between 2 and 120 characters.');
        db()->prepare('UPDATE users SET display_name=? WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL')->execute([$name,$id]);
    }
}
