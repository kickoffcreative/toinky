<?php
require_once __DIR__.'/UserFacingException.php';
require_once __DIR__.'/SessionService.php';
require_once __DIR__.'/SecurityKeyService.php';
final class TotpService
{
    private const ALPHABET='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public static function available(): bool
    {
        return function_exists('sodium_crypto_secretbox')&&SecurityKeyService::get()!==null;
    }
    private static function key(): string
    {
        if(!self::available())throw new UserFacingException('Authenticator setup is unavailable. Email sign-in codes remain available; contact the operator for help.');
        return SecurityKeyService::get();
    }
    private static function encrypt(string $secret): string
    {
        $key=self::key();$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1.'.base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,$key));
    }
    private static function decrypt(string $encoded): string
    {
        $key=self::key();$raw=str_starts_with($encoded,'v1.')?base64_decode(substr($encoded,3),true):false;
        if(!is_string($raw)||strlen($raw)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('Invalid encrypted authenticator record.');
        $secret=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$key);
        if(!is_string($secret))throw new RuntimeException('Authenticator decryption failed.');return $secret;
    }
    public static function base32(string $bytes): string
    {
        $out='';$buffer=0;$bits=0;
        for($i=0;$i<strlen($bytes);$i++){$buffer=($buffer<<8)|ord($bytes[$i]);$bits+=8;while($bits>=5){$bits-=5;$out.=self::ALPHABET[($buffer>>$bits)&31];}$buffer&=(1<<$bits)-1;}
        if($bits)$out.=self::ALPHABET[($buffer<<(5-$bits))&31];return $out;
    }
    private static function decode(string $secret): string
    {
        if(!preg_match('/^[A-Z2-7]{16,128}$/D',$secret))throw new InvalidArgumentException('Invalid authenticator encoding.');
        $out='';$buffer=0;$bits=0;
        for($i=0;$i<strlen($secret);$i++){$buffer=($buffer<<5)|strpos(self::ALPHABET,$secret[$i]);$bits+=5;if($bits>=8){$bits-=8;$out.=chr(($buffer>>$bits)&255);}$buffer&=(1<<$bits)-1;}return $out;
    }
    /** RFC 6238 / RFC 4226 SHA-1 dynamic truncation; public for vector tests. */
    public static function code(string $secret,int $timestamp,int $digits=6): string
    {
        if($timestamp<0||!in_array($digits,[6,8],true))throw new InvalidArgumentException('Invalid authenticator parameters.');
        $step=intdiv($timestamp,30);$counter=pack('N2',intdiv($step,4294967296),$step%4294967296);$mac=hash_hmac('sha1',$counter,self::decode($secret),true);$offset=ord($mac[19])&15;
        $number=((ord($mac[$offset])&127)<<24)|(ord($mac[$offset+1])<<16)|(ord($mac[$offset+2])<<8)|ord($mac[$offset+3]);
        return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
    }
    private static function matchedStep(string $secret,string $code,?int $last=null): ?int
    {
        if(!preg_match('/^[0-9]{6}$/D',$code))return null;$step=intdiv(time(),30);$matched=null;
        foreach([-1,0,1] as $offset){$candidate=$step+$offset;if($candidate<0||($last!==null&&$candidate<=$last))continue;if(hash_equals(self::code($secret,$candidate*30),$code))$matched=$candidate;}
        return $matched;
    }
    public static function enabled(int $id): bool
    {
        $q=db()->prepare('SELECT 1 FROM user_totp_credentials WHERE user_id=? AND enabled_at_utc IS NOT NULL');$q->execute([$id]);return (bool)$q->fetchColumn();
    }
    public static function status(int $id): array
    {
        $q=db()->prepare('SELECT enabled_at_utc,enrollment_expires_at_utc,recovery_hashes_json FROM user_totp_credentials WHERE user_id=?');$q->execute([$id]);$row=$q->fetch();
        return ['enabled'=>!empty($row['enabled_at_utc']),'pending'=>!empty($row['enrollment_expires_at_utc'])&&strtotime($row['enrollment_expires_at_utc'].' UTC')>time(),'recovery_remaining'=>count(json_decode((string)($row['recovery_hashes_json']??'[]'),true)?:[])];
    }
    public static function beginEnrollment(int $id): string
    {
        SessionService::requireRecentPassword($id);$secret=self::base32(random_bytes(20));$encrypted=self::encrypt($secret);$pdo=db();$pdo->beginTransaction();
        try{self::lockForChange($pdo,$id);$q=$pdo->prepare('SELECT enabled_at_utc FROM user_totp_credentials WHERE user_id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();if($row&&!empty($row['enabled_at_utc']))throw new UserFacingException('An authenticator is already enabled. Disable it before replacing it.');
            $pdo->prepare('INSERT INTO user_totp_credentials(user_id,encrypted_secret,enrollment_expires_at_utc) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE encrypted_secret=VALUES(encrypted_secret),enrollment_expires_at_utc=VALUES(enrollment_expires_at_utc),last_used_step=NULL,recovery_hashes_json=NULL')->execute([$id,$encrypted]);$pdo->commit();return $secret;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    private static function recoveryCodes(): array
    {
        $codes=[];for($i=0;$i<10;$i++)$codes[]=implode('-',str_split(strtoupper(bin2hex(random_bytes(16))),8));return $codes;
    }
    private static function recoveryHash(string $code): string{return hash('sha256',strtoupper((string)preg_replace('/[\s-]/','',$code)));}
    private static function lockForChange(PDO $pdo,int $id): void
    {
        $q=$pdo->prepare('SELECT user_id FROM users WHERE user_id=? AND status="ACTIVE" AND system_identity_key IS NULL FOR UPDATE');$q->execute([$id]);if(!$q->fetchColumn())throw new UserFacingException('Your account changed. Sign in again.');
        SessionService::requireRecentPassword($id);
    }
    /** Returns plaintext recovery codes once; the controller then signs out. */
    public static function enable(int $id,string $code): array
    {
        SessionService::requireRecentPassword($id);RateLimitService::codeAttempt($id);$pdo=db();$pdo->beginTransaction();
        try{self::lockForChange($pdo,$id);$q=$pdo->prepare('SELECT * FROM user_totp_credentials WHERE user_id=? FOR UPDATE');$q->execute([$id]);$row=$q->fetch();
            if(!$row||$row['enabled_at_utc']!==null||empty($row['enrollment_expires_at_utc'])||strtotime($row['enrollment_expires_at_utc'].' UTC')<=time())throw new UserFacingException('Authenticator setup expired. Start setup again.');
            $step=self::matchedStep(self::decrypt($row['encrypted_secret']),trim($code));if($step===null)throw new UserFacingException('That authenticator code is incorrect. Check the current code and try again.');
            $codes=self::recoveryCodes();$hashes=array_map([self::class,'recoveryHash'],$codes);
            $pdo->prepare('UPDATE user_totp_credentials SET enabled_at_utc=UTC_TIMESTAMP(),enrollment_expires_at_utc=NULL,last_used_step=?,recovery_hashes_json=? WHERE user_id=?')->execute([$step,json_encode($hashes,JSON_THROW_ON_ERROR),$id]);SessionService::revokeAll($id);$pdo->commit();return $codes;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    /** Caller has already checked the pending password-authenticated sign-in. */
    public static function verify(int $id,string $code,bool $recovery=false): bool
    {
        RateLimitService::codeAttempt($id);$pdo=db();$pdo->beginTransaction();
        try{$q=$pdo->prepare('SELECT * FROM user_totp_credentials WHERE user_id=? AND enabled_at_utc IS NOT NULL FOR UPDATE');$q->execute([$id]);$row=$q->fetch();$valid=false;
            if($row&&$recovery){$normalized=strtoupper((string)preg_replace('/[\s-]/','',$code));$hashes=json_decode((string)$row['recovery_hashes_json'],true)?:[];$index=null;if(preg_match('/^[A-F0-9]{32}$/D',$normalized))foreach($hashes as $i=>$hash)if(hash_equals($hash,self::recoveryHash($normalized)))$index=$i;
                if($index!==null){unset($hashes[$index]);$pdo->prepare('UPDATE user_totp_credentials SET recovery_hashes_json=? WHERE user_id=?')->execute([json_encode(array_values($hashes),JSON_THROW_ON_ERROR),$id]);$valid=true;}
            }elseif($row){$step=self::matchedStep(self::decrypt($row['encrypted_secret']),trim($code),$row['last_used_step']===null?null:(int)$row['last_used_step']);if($step!==null){$pdo->prepare('UPDATE user_totp_credentials SET last_used_step=? WHERE user_id=?')->execute([$step,$id]);$valid=true;}}
            $pdo->commit();return $valid;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function disable(int $id): void
    {
        SessionService::requireRecentPassword($id);$pdo=db();$pdo->beginTransaction();
        try{self::lockForChange($pdo,$id);$pdo->prepare('DELETE FROM user_totp_credentials WHERE user_id=?')->execute([$id]);SessionService::revokeAll($id);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
    public static function regenerateRecovery(int $id,string $code): array
    {
        SessionService::requireRecentPassword($id);if(!self::verify($id,$code))throw new UserFacingException('Enter a current authenticator code before replacing recovery codes.');
        $codes=self::recoveryCodes();$pdo=db();$pdo->beginTransaction();
        try{self::lockForChange($pdo,$id);$q=$pdo->prepare('UPDATE user_totp_credentials SET recovery_hashes_json=? WHERE user_id=? AND enabled_at_utc IS NOT NULL');$q->execute([json_encode(array_map([self::class,'recoveryHash'],$codes),JSON_THROW_ON_ERROR),$id]);if($q->rowCount()!==1)throw new UserFacingException('Authenticator settings changed. Sign in again.');SessionService::revokeAll($id);$pdo->commit();return $codes;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
