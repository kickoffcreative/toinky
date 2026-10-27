<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/MigrationService.php';
MigrationService::run();
require_once $root.'/app/UserProfileService.php';

$pdo=db();$userId=(int)$pdo->query('SELECT user_id FROM users WHERE status="ACTIVE" ORDER BY user_id LIMIT 1')->fetchColumn();
if($userId<1)throw new RuntimeException('Inky preference test needs an active user.');
$pdo->beginTransaction();
try{
    UserProfileService::saveInkyPreferences($userId,['enabled'=>1,'companion_name_root'=>'bo','character_code'=>'INKY_OCTOPUS','size_code'=>'LARGE','dock_position'=>'RIGHT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS']);
    UserProfileService::saveInkyPosition($userId,'MIDDLE_RIGHT');
    $saved=UserProfileService::inkyPreferences($userId);
    $expected=['enabled'=>1,'companion_name_root'=>'bo','character_code'=>'INKY_OCTOPUS','size_code'=>'LARGE','dock_position'=>'RIGHT','anchor_position'=>'MIDDLE_RIGHT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS'];
    foreach($expected as$key=>$value)if((string)$saved[$key]!== (string)$value)throw new RuntimeException('Inky preference '.$key.' did not persist.');
    if(UserProfileService::inkyDisplayName($saved)!=='Boinky')throw new RuntimeException('The Inky name suffix was not applied.');
    UserProfileService::saveInkyPreferences($userId,['enabled'=>1,'companion_name_root'=>'bo','character_code'=>'INKY_OCTOPUS','size_code'=>'LARGE','dock_position'=>'LEFT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS']);
    $saved=UserProfileService::inkyPreferences($userId);if($saved['anchor_position']!=='MIDDLE_LEFT')throw new RuntimeException('Left side preference did not move Inky to the left.');
    UserProfileService::saveInkyPreferences($userId,['enabled'=>1,'companion_name_root'=>'bo','character_code'=>'INKY_OCTOPUS','size_code'=>'LARGE','dock_position'=>'RIGHT','sounds_enabled'=>0,'animation_level'=>'REDUCED','proactive_help'=>'SUGGESTIONS']);
    $saved=UserProfileService::inkyPreferences($userId);if($saved['anchor_position']!=='MIDDLE_RIGHT')throw new RuntimeException('Right side preference did not move Inky to the right.');
    UserProfileService::saveInkyPreferences($userId,['enabled'=>0,'companion_name_root'=>'Boinky','character_code'=>'INKY_OCTOPUS','size_code'=>'STANDARD','dock_position'=>'AUTO','sounds_enabled'=>1,'animation_level'=>'OFF','proactive_help'=>'CLICK_ONLY']);
    $saved=UserProfileService::inkyPreferences($userId);if((int)$saved['enabled']!==0)throw new RuntimeException('Disable Inky did not persist.');if($saved['anchor_position']!=='MIDDLE_RIGHT')throw new RuntimeException('Saving profile settings erased the dragged Inky position.');
    if(UserProfileService::inkyDisplayName($saved)!=='Boinky')throw new RuntimeException('A complete Inky name received a duplicate suffix.');
    UserProfileService::saveInkyPreferences($userId,['enabled'=>1,'companion_name_root'=>'bo','character_code'=>'INKY_DIRECTION_D','size_code'=>'STANDARD','dock_position'=>'RIGHT','sounds_enabled'=>0,'animation_level'=>'FULL','proactive_help'=>'CLICK_ONLY']);
    $saved=UserProfileService::inkyPreferences($userId);if($saved['character_code']!=='INKY_DIRECTION_D')throw new RuntimeException('Selectable Lantern character did not persist.');
    $pdo->rollBack();
}catch(Throwable $error){if($pdo->inTransaction())$pdo->rollBack();throw$error;}
echo "PASS: Inky preferences persist per user and preserve the saved anchor.\n";
