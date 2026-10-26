<?php
declare(strict_types=1);
// Guard test: Page Builder Section Settings (audit tasks 8, 9, 10).
// Task 8: the legacy text/layout controls are disabled when a section has a
// row tree (they are not rendered), kept as hidden inputs.
// Task 9: content + visibility save in one transaction; a stale fingerprint
// is rejected instead of silently reverting another admin's change.
// Task 10: the old Pages editor is fully gone (dead initializers, $pageCode,
// view=pages redirect, unknown POST actions rejected, no migrations on admin).
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/PageContentService.php';

$failures=[];
$check=function(bool $ok,string $label)use(&$failures){echo ($ok?'PASS':'FAIL').' '.$label."\n";if(!$ok)$failures[]=$label;};
$pb=file_get_contents($root.'/public/page-builder.php');
$adminPhp=file_get_contents($root.'/public/admin.php');
$pcs=file_get_contents($root.'/app/PageContentService.php');

// --- Task 8 (static) ------------------------------------------------------
$check(strpos($pb,"\$__hasRows=!empty(\$section['rows_json']);")!==false,'Task 8: page-builder defines $__hasRows from rows_json');
$check(strpos($pb,'Shown from the row tree')!==false,'Task 8: Section Settings explains the disabled controls');
$check(strpos($pb,"<?=\$__hasRows?'disabled':''?>")!==false,'Task 8: dead controls carry a disabled attribute when rows exist');
$check(strpos($pb,'<input type="hidden" name="layout_type"')!==false,'Task 8: layout is kept as a hidden input when disabled');
$check(strpos($pb,'<input type="hidden" name="eyebrow_text"')!==false,'Task 8: eyebrow is kept as a hidden input when disabled');
$check(strpos($pb,'<input type="hidden" name="title_text"')!==false,'Task 8: title is kept as a hidden input when disabled');
$check(strpos($pb,'<input type="hidden" name="content_html"')!==false,'Task 8: intro is kept as a hidden input when disabled');

// --- Task 9 (static) ------------------------------------------------------
$check(strpos($pb,'section_fingerprint')!==false,'Task 9: Section Settings form carries section_fingerprint');
$check(strpos($pcs,'function sectionFingerprint')!==false,'Task 9: PageContentService::sectionFingerprint exists');
$check(strpos($pcs,'This section changed since you loaded the page')!==false,'Task 9: saveSection rejects a stale fingerprint');
$check(strpos($pb,"\$pdo=db();\$pdo->beginTransaction();")!==false,'Task 9: save_section handler opens a transaction');
// The transaction must cover both writes: saveSection and setSectionActive
// appear between the beginTransaction and the commit in the handler.
$txStart=strpos($pb,"\$pdo=db();\$pdo->beginTransaction();");
$txSlice=$txStart===false?'':substr($pb,$txStart,1200);
$check($txSlice!==''&&strpos($txSlice,'PageContentService::saveSection')!==false&&strpos($txSlice,'PageContentService::setSectionActive')!==false&&strpos($txSlice,'$pdo->commit();')!==false&&strpos($txSlice,'$pdo->rollBack();')!==false,'Task 9: save_section commits content + visibility together and rolls back on failure');

// --- Task 9 (behavioral: stale fingerprint) -------------------------------
$pdo=db();
$admin=$pdo->query('SELECT * FROM users WHERE is_admin=1 ORDER BY user_id LIMIT 1')->fetch();
$testSectionId=0;
try{
    if(!$admin){echo "SKIP: no administrator fixture for the fingerprint test.\n";}
    else{
        $admin=(array)$admin;
        // Create a scratch section on HOME so no real content is touched.
        $testSectionId=PageContentService::addSection($admin,'HOME',['label_text'=>'Guard Test Section','layout_type'=>'CONTAINER']);
        $row=$pdo->prepare('SELECT * FROM site_page_sections WHERE site_page_section_id=?');$row->execute([$testSectionId]);$section=$row->fetch(PDO::FETCH_ASSOC);
        if(!$section){echo "SKIP: scratch section not found.\n";}
        else{
            // Build a complete saveSection input from the stored row.
            $toInput=function(array $s):array{
                $cols=json_decode((string)($s['columns_json']??''),true);if(!is_array($cols))$cols=[];
                $in=['image_media_id'=>$s['image_media_id']??'','layout_type'=>$s['layout_type']??'CONTAINER','background_mode'=>$s['background_mode']??'DEFAULT','background_color'=>$s['background_color']??'#F4EEE2','background_media_id'=>$s['background_media_id']??'','background_fit'=>$s['background_fit']??'COVER','background_opacity'=>$s['background_opacity']??100,'overlay_mode'=>$s['overlay_mode']??'NONE','overlay_color_start'=>$s['overlay_color_start']??'#1C242B','overlay_color_end'=>$s['overlay_color_end']??'#6B2938','overlay_opacity'=>$s['overlay_opacity']??0,'eyebrow_text'=>$s['eyebrow_text']??'','title_text'=>$s['title_text']??'','content_html'=>$s['content_html']??''];
                for($i=1;$i<=3;$i++){$c=$cols[$i-1]??[];$in['column_'.$i.'_title']=$c['title']??'';$in['column_'.$i.'_content']=$c['content']??'';$in['column_'.$i.'_image_media_id']=$c['image_media_id']??'';}
                foreach(['padding_top_px','padding_right_px','padding_bottom_px','padding_left_px','mobile_padding_top_px','mobile_padding_right_px','mobile_padding_bottom_px','mobile_padding_left_px','label_font_size','label_color','label_transform','title_font_size','title_color','title_transform','body_font_size','body_color','body_transform'] as $k)$in[$k]=$s[$k]??'';
                return$in;
            };
            $fp1=PageContentService::sectionFingerprint($section);
            $input=$toInput($section);$input['padding_top_px']=24;$input['section_fingerprint']=$fp1;
            PageContentService::saveSection($admin,$testSectionId,$input);
            $row->execute([$testSectionId]);$after=$row->fetch(PDO::FETCH_ASSOC);
            $check((int)($after['padding_top_px']??0)===24,'Task 9: saveSection with a current fingerprint applies the change');
            // Now re-submit with the STALE fingerprint (from before the change).
            $staleInput=$toInput($after);$staleInput['padding_top_px']=48;$staleInput['section_fingerprint']=$fp1;
            $thrown=false;
            try{PageContentService::saveSection($admin,$testSectionId,$staleInput);}catch(Throwable $e){$thrown=strpos($e->getMessage(),'changed since you loaded the page')!==false;}
            $check($thrown,'Task 9: saveSection with a stale fingerprint is rejected');
            $row->execute([$testSectionId]);$unchanged=$row->fetch(PDO::FETCH_ASSOC);
            $check((int)($unchanged['padding_top_px']??0)===24,'Task 9: the rejected stale save leaves the stored value unchanged');
        }
    }
}catch(Throwable $e){
    $failures[]='unexpected exception: '.$e->getMessage();
    echo 'FAIL unexpected exception: '.$e->getMessage()."\n";
}finally{
    if($testSectionId){try{PageContentService::deleteSection($admin??[],$testSectionId);}catch(Throwable $ignored){$pdo->prepare('DELETE FROM site_page_sections WHERE site_page_section_id=?')->execute([$testSectionId]);}}
    echo "cleanup done.\n";
}

// --- Task 10 (static) -----------------------------------------------------
$check(strpos($adminPhp,'$sitePages')===false,'Task 10: dead $sitePages initializer is gone');
$check(strpos($adminPhp,'$pageSections')===false,'Task 10: dead $pageSections initializer is gone');
$check(strpos($adminPhp,'$pageMedia')===false,'Task 10: dead $pageMedia/$pageMediaUsage initializers are gone');
$check(strpos($adminPhp,'$pageCode')===false,'Task 10: dead $pageCode assignment is gone');
$check(strpos($adminPhp,"if(\$view==='pages'){header('Location: page-builder.php');exit;}")!==false,'Task 10: admin.php?view=pages redirects to the Page Builder');
$check(strpos($adminPhp,"throw new RuntimeException('Unknown admin action.');")!==false,'Task 10: unknown admin POST actions are rejected');
$check(strpos($adminPhp,'MigrationService::run()')===false,'Task 10: admin.php no longer runs migrations on page load');
$check(strpos($adminPhp,'MigrationService')===false,'Task 10: admin.php no longer references MigrationService at all');

if($failures){echo 'FAILURES: '.implode('; ',$failures)."\n";exit(1);}
echo "page_builder_section_guard_test: all checks passed.\n";
