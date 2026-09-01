<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/UserProfileService.php';

$pdo=db();$userId=(int)$pdo->query('SELECT user_id FROM users WHERE status="ACTIVE" ORDER BY user_id LIMIT 1')->fetchColumn();
if($userId<1)throw new RuntimeException('Inky preference test needs an active user.');
$pdo->beginTransaction();
try{
    UserProfileService::saveInkyPreferences($userId,['enabled'=>1,'size_code'=>'LARGE','dock_position'=>'RIGHT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS']);
    UserProfileService::saveInkyPosition($userId,'MIDDLE_RIGHT');
    $saved=UserProfileService::inkyPreferences($userId);
    $expected=['enabled'=>1,'size_code'=>'LARGE','dock_position'=>'RIGHT','anchor_position'=>'MIDDLE_RIGHT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS'];
    foreach($expected as$key=>$value)if((string)$saved[$key]!== (string)$value)throw new RuntimeException('Inky preference '.$key.' did not persist.');
    UserProfileService::saveInkyPreferences($userId,['enabled'=>0,'size_code'=>'STANDARD','dock_position'=>'AUTO','sounds_enabled'=>1,'animation_level'=>'OFF','proactive_help'=>'CLICK_ONLY']);
    $saved=UserProfileService::inkyPreferences($userId);if((int)$saved['enabled']!==0)throw new RuntimeException('Disable Inky did not persist.');if($saved['anchor_position']!=='MIDDLE_RIGHT')throw new RuntimeException('Saving profile settings erased the dragged Inky position.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
echo "PASS: Inky preferences persist per user and preserve the saved anchor.\n";

