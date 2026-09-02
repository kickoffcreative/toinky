<?php
final class UserProfileService
{
    public static function profile(int $userId): array
    {
        $q=db()->prepare('SELECT user_id,email,display_name,profile_image_path,email_verified_at_utc,created_at_utc FROM users WHERE user_id=? AND status="ACTIVE"');$q->execute([$userId]);$profile=$q->fetch();if(!$profile)throw new RuntimeException('Profile not found.');return$profile;
    }

    public static function uploadPhoto(array $user,array $file): void
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a profile image.');
        $size=(int)($file['size']??0);if($size<1||$size>5*1024*1024)throw new RuntimeException('Profile images must be 5 MB or smaller.');
        $tmp=(string)($file['tmp_name']??'');$info=@getimagesize($tmp);$mime=(string)($info['mime']??'');$width=(int)($info[0]??0);$height=(int)($info[1]??0);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime])||$width<1||$height<1)throw new RuntimeException('Upload a valid JPG, PNG, or WebP image.');
        $directory=dirname(__DIR__).'/public/uploads/profiles';if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('The profile-image directory could not be created.');$filename='user-'.(int)$user['user_id'].'-'.bin2hex(random_bytes(8)).'.'.$extensions[$mime];$target=$directory.'/'.$filename;
        self::storeSquare($tmp,$target,$mime,$width,$height);$path='public/uploads/profiles/'.$filename;$old=self::profile((int)$user['user_id'])['profile_image_path']??null;
        db()->prepare('UPDATE users SET profile_image_path=? WHERE user_id=?')->execute([$path,$user['user_id']]);self::deleteProfileFile((string)$old);
    }

    public static function requestEmailChange(array $user,string $newEmail): array
    {
        $newEmail=strtolower(trim($newEmail));if(!filter_var($newEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');if($newEmail===strtolower((string)$user['email']))throw new RuntimeException('That is already your account email.');$q=db()->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');$q->execute([$newEmail,$user['user_id']]);if($q->fetchColumn())throw new RuntimeException('That email address is already in use.');
        $token=bin2hex(random_bytes(24));db()->prepare('UPDATE user_email_change_tokens SET expires_at_utc=UTC_TIMESTAMP() WHERE user_id=? AND verified_at_utc IS NULL')->execute([$user['user_id']]);db()->prepare('INSERT INTO user_email_change_tokens(user_id,new_email,token_hash,expires_at_utc) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 HOUR))')->execute([$user['user_id'],$newEmail,hash('sha256',$token)]);
        $url=AuthService::baseUrl().'/profile.php?verify_email='.rawurlencode($token);$sent=AuthService::sendAccountMessage($newEmail,'Verify your new Toinky email',"Confirm this email for your Toinky account:\n\n".$url."\n\nThis link expires in 2 hours.",'EMAIL_CHANGE',(int)$user['user_id']);return['url'=>$url,'sent'=>$sent];
    }

    public static function confirmEmailChange(int $userId,string $token): void
    {
        $pdo=db();$q=$pdo->prepare('SELECT * FROM user_email_change_tokens WHERE user_id=? AND token_hash=? AND verified_at_utc IS NULL AND expires_at_utc>UTC_TIMESTAMP()');$q->execute([$userId,hash('sha256',$token)]);$change=$q->fetch();if(!$change)throw new RuntimeException('This email verification link is invalid or expired.');$q=$pdo->prepare('SELECT 1 FROM users WHERE email=? AND user_id<>?');$q->execute([$change['new_email'],$userId]);if($q->fetchColumn())throw new RuntimeException('That email address is now in use by another account.');
        $pdo->beginTransaction();try{$pdo->prepare('UPDATE users SET email=?,email_verified_at_utc=UTC_TIMESTAMP() WHERE user_id=?')->execute([$change['new_email'],$userId]);$pdo->prepare('UPDATE user_email_change_tokens SET verified_at_utc=UTC_TIMESTAMP() WHERE email_change_token_id=?')->execute([$change['email_change_token_id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public static function requestFriend(int $userId,string $email): void
    {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');$q=db()->prepare('SELECT user_id FROM users WHERE email=? AND status="ACTIVE"');$q->execute([$email]);$friendId=(int)$q->fetchColumn();if($friendId<1)throw new RuntimeException('That person does not have an active Toinky account yet. Invite them first.');if($friendId===$userId)throw new RuntimeException('You cannot add yourself as a friend.');[$low,$high]=self::pair($userId,$friendId);
        $q=db()->prepare('SELECT status FROM user_friendships WHERE user_low_id=? AND user_high_id=?');$q->execute([$low,$high]);$status=$q->fetchColumn();if($status==='ACCEPTED')throw new RuntimeException('That player is already your friend.');db()->prepare('INSERT INTO user_friendships(user_low_id,user_high_id,requested_by_user_id,status) VALUES(?,?,?,"PENDING") ON DUPLICATE KEY UPDATE requested_by_user_id=VALUES(requested_by_user_id),status="PENDING",responded_at_utc=NULL')->execute([$low,$high,$userId]);
    }

    public static function respondToFriend(int $userId,int $friendshipId,bool $accept): void
    {
        $q=db()->prepare('UPDATE user_friendships SET status=?,responded_at_utc=UTC_TIMESTAMP() WHERE friendship_id=? AND status="PENDING" AND requested_by_user_id<>? AND (user_low_id=? OR user_high_id=?)');$q->execute([$accept?'ACCEPTED':'DECLINED',$friendshipId,$userId,$userId,$userId]);if($q->rowCount()!==1)throw new RuntimeException('That friend request is unavailable.');
    }

    public static function removeFriend(int $userId,int $friendshipId): void
    {
        $q=db()->prepare('DELETE FROM user_friendships WHERE friendship_id=? AND (user_low_id=? OR user_high_id=?)');$q->execute([$friendshipId,$userId,$userId]);if($q->rowCount()!==1)throw new RuntimeException('That friendship is unavailable.');
    }

    public static function saveFriendLabel(int $userId,int $friendshipId,string $label): void
    {
        $q=db()->prepare('SELECT 1 FROM user_friendships WHERE friendship_id=? AND status="ACCEPTED" AND (user_low_id=? OR user_high_id=?)');$q->execute([$friendshipId,$userId,$userId]);if(!$q->fetchColumn())throw new RuntimeException('That friendship is unavailable.');$label=trim($label);if(mb_strlen($label)>80)throw new RuntimeException('Group labels may not exceed 80 characters.');db()->prepare('INSERT INTO user_friend_labels(friendship_id,user_id,label_text) VALUES(?,?,?) ON DUPLICATE KEY UPDATE label_text=VALUES(label_text)')->execute([$friendshipId,$userId,$label?:null]);
    }

    public static function friends(int $userId): array
    {
        $q=db()->prepare('SELECT f.friendship_id,f.status,f.requested_by_user_id,u.user_id friend_user_id,u.display_name,u.email,u.profile_image_path,l.label_text FROM user_friendships f JOIN users u ON u.user_id=IF(f.user_low_id=?,f.user_high_id,f.user_low_id) LEFT JOIN user_friend_labels l ON l.friendship_id=f.friendship_id AND l.user_id=? WHERE (f.user_low_id=? OR f.user_high_id=?) ORDER BY FIELD(f.status,"PENDING","ACCEPTED","DECLINED"),l.label_text,u.display_name');$q->execute([$userId,$userId,$userId,$userId]);return$q->fetchAll();
    }

    public static function acceptedFriends(int $userId): array
    {
        return array_values(array_filter(self::friends($userId),fn($friend)=>$friend['status']==='ACCEPTED'));
    }

    public static function writerInsights(int $userId): array
    {
        $q=db()->prepare('SELECT p.plot_id,p.title,p.workflow_status,COUNT(DISTINCT sr.story_rating_id) rating_count,ROUND(AVG(sr.rating),1) average_rating,MAX(sr.created_at_utc) latest_rating_at FROM plots p LEFT JOIN simulations s ON s.plot_id=p.plot_id LEFT JOIN games g ON g.simulation_id=s.simulation_id LEFT JOIN story_ratings sr ON sr.game_id=g.game_id WHERE p.author_user_id=? GROUP BY p.plot_id ORDER BY p.plot_id DESC');$q->execute([$userId]);$stories=$q->fetchAll();
        $q=db()->prepare('SELECT v.plot_version_id,v.version_no,v.workflow_status,v.review_notes,v.created_at_utc,v.reviewed_at_utc,p.title FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id WHERE p.author_user_id=? ORDER BY v.created_at_utc DESC LIMIT 100');$q->execute([$userId]);return['stories'=>$stories,'reviews'=>$q->fetchAll()];
    }

    public static function inkyPreferences(int $userId): array
    {
        $defaults=['enabled'=>1,'companion_name_root'=>null,'character_code'=>'INKY_OCTOPUS','size_code'=>'STANDARD','dock_position'=>'AUTO','anchor_position'=>'LOWER_LEFT','sounds_enabled'=>1,'animation_level'=>'FULL','proactive_help'=>'CLICK_ONLY'];
        $q=db()->prepare('SELECT enabled,companion_name_root,character_code,size_code,dock_position,anchor_position,sounds_enabled,animation_level,proactive_help FROM user_inky_preferences WHERE user_id=?');$q->execute([$userId]);$saved=$q->fetch();return$saved?array_merge($defaults,$saved):$defaults;
    }

    public static function inkyCharacters(): array
    {
        return db()->query('SELECT character_code,label_text,description_text,artwork_path,selectable FROM inky_characters WHERE active=1 ORDER BY sort_order,label_text')->fetchAll();
    }

    public static function inkyDisplayName(array $preferences): string
    {
        $root=self::inkyNameRoot((string)($preferences['companion_name_root']??''));if($root==='')return'Inky';$root=mb_convert_case($root,MB_CASE_TITLE,'UTF-8');return preg_match('/inky$/iu',$root)?$root:$root.'inky';
    }

    public static function saveInkyPreferences(int $userId,array $input): void
    {
        $size=self::allowed((string)($input['size_code']??''),['SMALL','STANDARD','LARGE'],'STANDARD');$dock=self::allowed((string)($input['dock_position']??''),['AUTO','LEFT','RIGHT'],'AUTO');$animation=self::allowed((string)($input['animation_level']??''),['FULL','REDUCED','OFF'],'FULL');$proactive=self::allowed((string)($input['proactive_help']??''),['SUGGESTIONS','CLICK_ONLY'],'CLICK_ONLY');$current=self::inkyPreferences($userId);$name=self::inkyNameRoot((string)($input['companion_name_root']??''));$character=self::selectableInkyCharacter((string)($input['character_code']??'INKY_OCTOPUS'));
        db()->prepare('INSERT INTO user_inky_preferences(user_id,enabled,companion_name_root,character_code,size_code,dock_position,anchor_position,sounds_enabled,animation_level,proactive_help) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled),companion_name_root=VALUES(companion_name_root),character_code=VALUES(character_code),size_code=VALUES(size_code),dock_position=VALUES(dock_position),sounds_enabled=VALUES(sounds_enabled),animation_level=VALUES(animation_level),proactive_help=VALUES(proactive_help)')->execute([$userId,!empty($input['enabled'])?1:0,$name!==''?$name:null,$character,$size,$dock,$current['anchor_position'],!empty($input['sounds_enabled'])?1:0,$animation,$proactive]);
    }

    public static function saveInkyPosition(int $userId,string $anchor): void
    {
        $anchor=self::allowed($anchor,['LOWER_LEFT','MIDDLE_LEFT','LOWER_RIGHT','MIDDLE_RIGHT'],'');if($anchor==='')throw new RuntimeException('Choose a valid Inky position.');
        db()->prepare('INSERT INTO user_inky_preferences(user_id,anchor_position) VALUES(?,?) ON DUPLICATE KEY UPDATE anchor_position=VALUES(anchor_position)')->execute([$userId,$anchor]);
    }

    private static function pair(int $a,int $b): array{return$a<$b?[$a,$b]:[$b,$a];}
    private static function allowed(string $value,array $allowed,string $default): string{$value=strtoupper(trim($value));return in_array($value,$allowed,true)?$value:$default;}
    private static function inkyNameRoot(string $value): string{$value=trim((string)preg_replace('/[^\p{L}\p{N}\s\x{2019}\x{0027}-]+/u','',$value));$value=(string)preg_replace('/\s+/u',' ',$value);return mb_substr($value,0,32,'UTF-8');}
    private static function selectableInkyCharacter(string $code): string{$code=self::allowed($code,array_column(self::inkyCharacters(),'character_code'),'INKY_OCTOPUS');$q=db()->prepare('SELECT 1 FROM inky_characters WHERE character_code=? AND active=1 AND selectable=1');$q->execute([$code]);return$q->fetchColumn()?$code:'INKY_OCTOPUS';}
    private static function storeSquare(string $source,string $target,string $mime,int $width,int $height): void
    {
        if(!function_exists('imagecreatetruecolor')){if(max($width,$height)>512)throw new RuntimeException('This server needs PHP GD to resize profile images larger than 512 pixels.');if(!move_uploaded_file($source,$target)&&!rename($source,$target))throw new RuntimeException('The profile image could not be saved.');return;}
        $load=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'][$mime];$save=['image/jpeg'=>'imagejpeg','image/png'=>'imagepng','image/webp'=>'imagewebp'][$mime];$src=@$load($source);if(!$src)throw new RuntimeException('The profile image could not be decoded.');$side=min($width,$height);$sx=(int)(($width-$side)/2);$sy=(int)(($height-$side)/2);$out=min(512,$side);$dst=imagecreatetruecolor($out,$out);if($mime!=='image/jpeg'){imagealphablending($dst,false);imagesavealpha($dst,true);$transparent=imagecolorallocatealpha($dst,0,0,0,127);imagefilledrectangle($dst,0,0,$out,$out,$transparent);}imagecopyresampled($dst,$src,0,0,$sx,$sy,$out,$out,$side,$side);$ok=$mime==='image/png'?$save($dst,$target,7):$save($dst,$target,86);imagedestroy($src);imagedestroy($dst);if(!$ok)throw new RuntimeException('The profile image could not be saved.');
    }
    private static function deleteProfileFile(string $path): void{if(!str_starts_with($path,'public/uploads/profiles/'))return;$target=dirname(__DIR__).'/public/uploads/profiles/'.basename($path);if(is_file($target))@unlink($target);}
}
