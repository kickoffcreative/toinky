<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/CommerceService.php';
require_once $root.'/app/StripeService.php';

$pdo=db();
$host=$pdo->query('SELECT user_id FROM users WHERE is_admin=0 ORDER BY user_id LIMIT 1')->fetch();
if(!$host)throw new RuntimeException('Commerce webhook test needs a non-admin user fixture.');
$hostId=(int)$host['user_id'];

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$secret='whsec_guard_test_secret';
$payload=(string)json_encode(['id'=>'evt_guard','type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_guard_nope','metadata'=>['purchase_id'=>'0']]]]);
$ts=time();
$goodHeader='t='.$ts.',v1='.hash_hmac('sha256',$ts.'.'.$payload,$secret);
$check(StripeService::verifySignature($payload,$goodHeader,$secret),'valid webhook signature is accepted');
$check(!StripeService::verifySignature($payload.' ',$goodHeader,$secret),'tampered payload is rejected');
$oldTs=$ts-3600;
$check(!StripeService::verifySignature($payload,'t='.$oldTs.',v1='.hash_hmac('sha256',$oldTs.'.'.$payload,$secret),$secret),'stale timestamp is rejected');
$check(!StripeService::verifySignature($payload,$goodHeader,'whsec_wrong_secret'),'wrong secret is rejected');

CommerceService::handleWebhookEvent((array)json_decode($payload,true));
$check(true,'event for an unknown purchase is a harmless no-op');

$purchaseIds=[];
try{
    $pdo->prepare("INSERT INTO host_purchases(user_id,bundle_key,occasions_granted,amount_cents,currency,status,stripe_payment_intent_id,paid_at_utc) VALUES(?,'trio',3,1200,'USD','PAID','pi_guard_test',UTC_TIMESTAMP())")->execute([$hostId]);
    $pid=(int)$pdo->lastInsertId();$purchaseIds[]=$pid;
    $pdo->prepare("INSERT INTO host_occasion_ledger(user_id,entry_type,occasions,purchase_id,note) VALUES(?,'PURCHASE',3,?,'Guard test purchase.')")->execute([$hostId,$pid]);
    $before=CommerceService::balance($hostId);
    CommerceService::revokePurchase($pid,'REFUNDED');
    $after=CommerceService::balance($hostId);
    $q=$pdo->prepare('SELECT status FROM host_purchases WHERE purchase_id=?');$q->execute([$pid]);
    $check($q->fetchColumn()==='REFUNDED','revoked purchase is marked REFUNDED');
    $check($after['available']===$before['available']-3,'refund takes back the unspent occasions');
    CommerceService::revokePurchase($pid,'REFUNDED');
    $q=$pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger WHERE purchase_id=? AND entry_type='REFUND_REVOKE'");$q->execute([$pid]);
    $check((int)$q->fetchColumn()===1,'a second revoke is a no-op');

    $pdo->prepare("INSERT INTO host_purchases(user_id,bundle_key,occasions_granted,amount_cents,currency,status,stripe_checkout_session_id) VALUES(?,'single',1,500,'USD','PENDING','cs_guard_expire')")->execute([$hostId]);
    $pid2=(int)$pdo->lastInsertId();$purchaseIds[]=$pid2;
    CommerceService::handleWebhookEvent(['type'=>'checkout.session.expired','data'=>['object'=>['id'=>'cs_guard_expire','metadata'=>['purchase_id'=>(string)$pid2]]]]);
    $q=$pdo->prepare('SELECT status FROM host_purchases WHERE purchase_id=?');$q->execute([$pid2]);
    $check($q->fetchColumn()==='EXPIRED','expired checkout session marks the purchase EXPIRED');
}finally{
    foreach($purchaseIds as $pid){
        $pdo->prepare('DELETE FROM host_occasion_ledger WHERE purchase_id=?')->execute([$pid]);
        $pdo->prepare('DELETE FROM host_purchases WHERE purchase_id=?')->execute([$pid]);
    }
}
if($failures)throw new RuntimeException('Commerce webhook guard test failed: '.implode('; ',$failures));
echo "COMMERCE WEBHOOK GUARD TEST PASSED\n";
