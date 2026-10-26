<?php
// TEMPORARY test helper — simulate a 14+ day gap since last sign-in so a Super
// Admin can exercise the two-factor email flow on demand. Remove this file
// (and its commit) once the flow has been tested end-to-end.
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/AuthService.php';
AuthService::boot();
$user=AuthService::requireLogin();
if(!AuthService::isSuperAdmin((int)$user['user_id'])){
    http_response_code(403);
    echo 'This page is only for Super Admin accounts.';
    exit;
}
$notice=null;$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        AuthService::requireCsrf();
        if(($_POST['action']??'')==='simulate'){
            db()->prepare("UPDATE users SET last_login_at_utc=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 DAY) WHERE user_id=?")->execute([(int)$user['user_id']]);
            $notice='Done. Your last sign-in is now recorded as 15 days ago. Sign out, then sign in again — you should be asked for a 6-digit code sent to '.e($user['email']).'.';
        }
    }catch(Throwable $e){$error=$e->getMessage();}
}
$u=AuthService::currentUser()??$user;
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Simulate 2FA gap — Toinky</title></head>
<body style="font-family:sans-serif;max-width:640px;margin:40px auto;padding:0 16px;">
<h1>Simulate the 14-day gap</h1>
<p>Signed in as <?=e($u['email'])?>. Your last sign-in is recorded as: <strong><?=e((string)($u['last_login_at_utc']??'—'))?></strong> (UTC).</p>
<?php if($notice): ?><p style="background:#e8f5e9;padding:12px;border-radius:6px;"><?=e($notice)?></p><?php endif; ?>
<?php if($error): ?><p style="background:#fdecea;padding:12px;border-radius:6px;"><?=e($error)?></p><?php endif; ?>
<p>This sets your last sign-in back 15 days, so your next sign-in triggers the two-factor code email. Your real sign-in is not affected until you sign out and sign in again. A successful sign-in (with the code) records a fresh sign-in time, so the gap clears itself.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="simulate">
<button type="submit" style="padding:10px 18px;font-size:16px;cursor:pointer;">Set my last sign-in to 15 days ago</button>
</form>
<p><a href="./">Back to dashboard</a></p>
</body></html>
