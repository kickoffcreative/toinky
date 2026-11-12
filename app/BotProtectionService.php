<?php
/** A quiet form check, backed by RateLimitService; it is not a bot-proof challenge. */
final class BotProtectionService
{
    private const ACTIONS=['login','register','forgot'];
    private const LIFETIME_MS=1800000;

    public static function token(string $action): string
    {
        self::validateAction($action);AuthService::boot();
        if(empty($_SESSION['auth_form_secret']))$_SESSION['auth_form_secret']=bin2hex(random_bytes(32));
        $token=(string)($_SESSION['auth_form_tokens'][$action]??'');
        if(self::validSignature($action,$token)){
            $issued=(int)explode('.',$token)[0];$age=self::now()-$issued;
            if($age>=0&&$age<self::LIFETIME_MS)return $token;
        }
        $body=self::now().'.'.bin2hex(random_bytes(16));
        $token=$body.'.'.self::signature($action,$body);
        $_SESSION['auth_form_tokens'][$action]=$token;return $token;
    }

    public static function fields(string $action): string
    {
        return '<input type="hidden" name="auth_form_token" value="'.e(self::token($action)).'">'
            .'<div hidden aria-hidden="true"><label>Leave This Field Empty<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>';
    }

    public static function verify(string $action,array $post): void
    {
        self::validateAction($action);AuthService::boot();
        $token=$post['auth_form_token']??'';$trap=$post['company_website']??'';
        if(!is_string($token)||!is_string($trap)||trim($trap)!==''||!self::validSignature($action,$token))throw new RuntimeException('This form expired. Refresh the page and try again.');
        $age=self::now()-(int)explode('.',$token)[0];
        if($age<0||$age>=self::LIFETIME_MS)throw new RuntimeException('This form expired. Refresh the page and try again.');
        if($age<2000)throw new RuntimeException('Please wait a moment, then submit this form again.');
        // The token may be reused during this form's lifetime. Rate limits remain
        // mandatory; this is not a substitute for them or the separate CSRF check.
    }

    private static function validSignature(string $action,string $token): bool
    {
        if(empty($_SESSION['auth_form_secret'])||!preg_match('/^([0-9]{13})\.([a-f0-9]{32})\.([a-f0-9]{64})$/D',$token,$matches))return false;
        return hash_equals(self::signature($action,$matches[1].'.'.$matches[2]),$matches[3]);
    }
    private static function signature(string $action,string $body): string
    {
        return hash_hmac('sha256',$action."\0".session_id()."\0".$body,(string)$_SESSION['auth_form_secret']);
    }
    private static function now(): int{return (int)floor(microtime(true)*1000);}
    private static function validateAction(string $action): void
    {
        if(!in_array($action,self::ACTIONS,true))throw new InvalidArgumentException('Invalid authentication form.');
    }
}
