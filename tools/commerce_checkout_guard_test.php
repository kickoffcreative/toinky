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
if(!$host)throw new RuntimeException('Commerce checkout test needs a non-admin user fixture.');
$hostId=(int)$host['user_id'];

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};

$keys=['COMMERCE_ENABLED','COMMERCE_FREE_FIRST','COMMERCE_BUNDLES','STRIPE_PUBLISHABLE_KEY','STRIPE_SECRET_KEY','STRIPE_WEBHOOK_SECRET'];
$saved=[];
foreach($keys as $k){$q=$pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');$q->execute([$k]);$v=$q->fetchColumn();$saved[$k]=$v===false?null:(string)$v;}
$set=function(string $k,?string $v)use($pdo){if($v===null){$pdo->prepare('DELETE FROM platform_settings WHERE setting_key=?')->execute([$k]);}else{$pdo->prepare('INSERT INTO platform_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$k,$v]);}};
$countPurchases=function()use($pdo,$hostId){$q=$pdo->prepare('SELECT COUNT(*) FROM host_purchases WHERE user_id=?');$q->execute([$hostId]);return (int)$q->fetchColumn();};
$purchaseIds=[];
try{
    $set('COMMERCE_ENABLED','1');
    $trio=CommerceService::bundle('trio');
    $check($trio!==null&&(int)$trio['occasions']===3&&(int)$trio['price_cents']===1200,'trio bundle resolves to 3 occasions at $12.00');

    $set('STRIPE_SECRET_KEY',null);
    $check(!StripeService::configured(),'stripe reports unconfigured without a secret key');
    $before=$countPurchases();
    $threw=false;
    try{CommerceService::startPurchase($hostId,'trio');}catch(RuntimeException $e){$threw=true;}
    $check($threw&&$countPurchases()===$before,'startPurchase refuses cleanly when Stripe is not configured');

    $set('STRIPE_SECRET_KEY','sk_test_invalid_key_for_guard_test');
    $check(StripeService::configured()&&StripeService::mode()==='TEST','stripe detects test mode from the key prefix');
    $threw=false;
    try{CommerceService::startPurchase($hostId,'trio');}catch(RuntimeException $e){$threw=true;}
    $check($threw,'startPurchase surfaces a Stripe rejection as an error');
    $q=$pdo->prepare('SELECT purchase_id,status FROM host_purchases WHERE user_id=? ORDER BY purchase_id DESC LIMIT 1');$q->execute([$hostId]);$row=$q->fetch();
    $check($row&&$row['status']==='FAILED','rejected checkout leaves a FAILED purchase record');
    if($row)$purchaseIds[]=(int)$row['purchase_id'];
    $q=$pdo->prepare("SELECT COUNT(*) FROM host_occasion_ledger WHERE user_id=? AND entry_type='PURCHASE'");$q->execute([$hostId]);
    $check((int)$q->fetchColumn()===0,'no occasions were granted by the rejected checkout');
    if($purchaseIds){CommerceService::settlePurchase($purchaseIds[0]);$q=$pdo->prepare('SELECT status FROM host_purchases WHERE purchase_id=?');$q->execute([$purchaseIds[0]]);$check($q->fetchColumn()==='FAILED','settlePurchase ignores a purchase that never reached Stripe');}
}finally{
    foreach($purchaseIds as $pid)$pdo->prepare('DELETE FROM host_purchases WHERE purchase_id=?')->execute([$pid]);
    foreach($saved as $k=>$v)$set($k,$v);
}
if($failures)throw new RuntimeException('Commerce checkout guard test failed: '.implode('; ',$failures));
echo "COMMERCE CHECKOUT GUARD TEST PASSED\n";
