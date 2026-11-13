<?php
require_once __DIR__.'/Logger.php';
require_once __DIR__.'/UserFacingException.php';
final class RateLimitException extends UserFacingException
{
    public function __construct(){parent::__construct('Too many attempts. Try again later.');}
}

final class RateLimitService
{
    /** No raw account, recipient or IP is stored, including in log context. */
    public static function key(string $action,string $subject): string
    {
        return hash('sha256',$action."\0".$subject);
    }

    public static function clientIp(): string
    {
        // Forwarded headers are deliberately ignored. Proxy trust needs A-19 evidence.
        $ip=(string)($_SERVER['REMOTE_ADDR']??'');
        return filter_var($ip,FILTER_VALIDATE_IP)?(string)inet_ntop(inet_pton($ip)):'unknown';
    }

    public static function assertAllowed(string $action,string $subject): void
    {
        $key=self::key($action,$subject);$q=db()->prepare('SELECT 1 FROM auth_throttle WHERE throttle_key=? AND locked_until_utc>UTC_TIMESTAMP()');$q->execute([$key]);
        if($q->fetchColumn()){self::logLockout($action,$key);throw new RateLimitException();}
    }

    /** The max-th hit is allowed, but locks subsequent calls until cooldown ends. */
    public static function hit(string $action,string $subject,int $max,int $windowSeconds,int $lockSeconds): void
    {
        if($max<1||$windowSeconds<1||$lockSeconds<1||!preg_match('/^[A-Z0-9_]{1,64}$/D',$action))throw new InvalidArgumentException('Invalid throttle policy.');
        $pdo=db();if($pdo->inTransaction())throw new LogicException('Apply throttles before starting the operation transaction.');
        $key=self::key($action,$subject);$pdo->beginTransaction();$over=false;
        try{
            // The unique key and upsert serialize concurrent workers. Assignment order matters:
            // attempts resets first; only then is its new window start selected.
            $q=$pdo->prepare('INSERT INTO auth_throttle(throttle_key,action,window_start_utc,attempts) VALUES(?,?,UTC_TIMESTAMP(),1) ON DUPLICATE KEY UPDATE attempts=IF(window_start_utc<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? SECOND) AND (locked_until_utc IS NULL OR locked_until_utc<=UTC_TIMESTAMP()),1,LEAST(attempts+1,2147483647)),window_start_utc=IF(attempts=1,UTC_TIMESTAMP(),window_start_utc)');
            $q->execute([$key,$action,$windowSeconds]);
            $q=$pdo->prepare('SELECT attempts,locked_until_utc>UTC_TIMESTAMP() locked,penalty_level,last_lockout_utc>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 24 HOUR) recent_lockout FROM auth_throttle WHERE throttle_key=? FOR UPDATE');$q->execute([$key]);$row=$q->fetch();
            $over=!empty($row['locked'])||(int)$row['attempts']>$max;
            if(empty($row['locked'])&&(int)$row['attempts']>=$max){
                $growing=str_starts_with($action,'SIGN_IN_');$level=$growing&&!empty($row['recent_lockout'])?min(4,(int)$row['penalty_level']+1):1;
                $duration=$lockSeconds*($growing?(2**($level-1)):1);
                $pdo->prepare('UPDATE auth_throttle SET locked_until_utc=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),penalty_level=?,last_lockout_utc=UTC_TIMESTAMP() WHERE throttle_key=?')->execute([$duration,$level,$key]);
                self::logLockout($action,$key);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        if($over){self::logLockout($action,$key);throw new RateLimitException();}
    }

    public static function checkSignIn(string $email): void
    {
        self::assertAllowed('SIGN_IN_ACCOUNT',self::email($email));self::assertAllowed('SIGN_IN_IP',self::clientIp());
    }
    public static function failedSignIn(string $email): void
    {
        self::both([
            ['SIGN_IN_ACCOUNT',self::email($email),5,900,900],
            ['SIGN_IN_IP',self::clientIp(),20,900,900]
        ]);
    }
    public static function adminReset(int $userId): void {self::userAndIp('ADMIN_RESET',$userId,20,3600);}
    public static function resetAttempt(string $token): void
    {
        self::both([['RESET_TOKEN',hash('sha256',$token),5,900,900],['RESET_ATTEMPT_IP',self::clientIp(),20,900,900]]);
    }
    public static function registration(): void {self::hit('REGISTER_IP',self::clientIp(),5,3600,3600);}
    public static function resetRequest(string $email): void
    {
        self::both([['RESET_EMAIL',self::email($email),3,3600,3600],['RESET_IP',self::clientIp(),10,3600,3600]]);
    }
    public static function invitation(int $senderId,string $email): void
    {
        self::both([['INVITE_SENDER',(string)$senderId,20,86400,86400],['INVITE_RECIPIENT',self::email($email),5,86400,86400],['INVITE_IP',self::clientIp(),100,86400,86400]]);
    }
    public static function emailChange(int $userId): void {self::userAndIp('EMAIL_CHANGE',$userId,10,3600);}
    public static function friendRequest(int $userId): void {self::userAndIp('FRIEND_REQUEST',$userId,10,3600);}
    public static function verification(int $userId): void {self::userAndIp('VERIFY_EMAIL',$userId,3,3600);}
    public static function ai(int $userId): void {self::userAndIp('AI',$userId,30,600);}
    public static function checkJoin(int $userId): void
    {
        self::assertAllowed('JOIN_USER',(string)$userId);self::assertAllowed('JOIN_IP',self::clientIp());
    }
    public static function failedJoin(int $userId): void {self::userAndIp('JOIN',$userId,10,600);}
    public static function codeAttempt(int $userId): void {self::userAndIp('SIGNIN_CODE',$userId,5,600);}
    public static function codeResend(int $userId): void {self::userAndIp('CODE_RESEND',$userId,5,3600);}

    private static function userAndIp(string $action,int $userId,int $max,int $window): void
    {
        self::both([[$action.'_USER',(string)$userId,$max,$window,$window],[$action.'_IP',self::clientIp(),$max*4,$window,$window]]);
    }
    private static function both(array $policies): void
    {
        // Hit every key even when one is exhausted: rotating subjects must still
        // consume the IP budget, and rotating IPs must still consume the subject budget.
        $blocked=false;foreach($policies as $policy){try{self::hit(...$policy);}catch(RateLimitException $e){$blocked=true;}}
        if($blocked)throw new RateLimitException();
    }
    private static function email(string $email): string{return strtolower(trim($email));}
    private static function logLockout(string $action,string $key): void
    {
        Logger::warning('Account throttle lockout',['action'=>$action,'throttle_key'=>$key]);
    }
}
