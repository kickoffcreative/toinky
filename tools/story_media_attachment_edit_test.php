<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/AuthService.php';
require_once dirname(__DIR__).'/app/StoryMediaService.php';

$pdo=db();
$user=$pdo->query('SELECT user_id FROM users ORDER BY is_admin DESC,user_id LIMIT 1')->fetch();
$plot=$pdo->query('SELECT plot_id FROM plots ORDER BY plot_id LIMIT 1')->fetch();
if(!$plot||!$user)throw new RuntimeException('Artwork edit test needs a seeded story and user.');

$assetCode='ARTWORK_EDIT_TEST_'.strtoupper(bin2hex(random_bytes(4)));
$q=$pdo->prepare('INSERT INTO plot_media_assets(plot_id,uploaded_by_user_id,asset_code,placement,title_text,alt_text,file_path,mime_type,file_size_bytes,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)');
$q->execute([(int)$plot['plot_id'],(int)$user['user_id'],$assetCode,'OTHER','Original artwork','Artwork edit test','public/uploads/story-media/artwork-edit-test.png','image/png',100,100]);
$assetId=(int)$pdo->lastInsertId();
$q=$pdo->prepare('SELECT * FROM plot_media_assets WHERE media_asset_id=?');$q->execute([$assetId]);$asset=$q->fetch();
if(!$asset)throw new RuntimeException('Artwork edit test could not create its fixture.');

$writer=['user_id'=>(int)$user['user_id'],'is_admin'=>1];
$updatedTitle='Artwork assignment test '.strtoupper(bin2hex(random_bytes(3)));
StoryMediaService::updateAttachment($writer,(int)$asset['plot_id'],(int)$asset['media_asset_id'],[
    'library_media_id'=>0,
    'placement'=>$asset['placement'],
    'role_code'=>$asset['role_code'],
    'event_code'=>$asset['event_code'],
    'scene_second'=>$asset['scene_second'],
    'condition_state_key'=>'',
    'condition_operator'=>'',
    'condition_value'=>'',
    'title_text'=>$updatedTitle,
    'caption_text'=>$asset['caption_text'],
    'sort_order'=>(int)$asset['sort_order'],
]);

$q=$pdo->prepare('SELECT * FROM plot_media_assets WHERE media_asset_id=?');
$q->execute([(int)$asset['media_asset_id']]);
$updated=$q->fetch();
if(!$updated||$updated['title_text']!==$updatedTitle)throw new RuntimeException('Artwork assignment title was not updated.');
if((string)$updated['file_path']!==(string)$asset['file_path'])throw new RuntimeException('Keeping the current image changed its file path.');
if((int)$updated['active']!==1)throw new RuntimeException('Edited artwork assignment was not active.');

echo "Story artwork assignment editing passed.\n";
