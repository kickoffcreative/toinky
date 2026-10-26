<?php
declare(strict_types=1);

$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
require_once $root.'/app/AuthService.php';
require_once $root.'/app/PageContentService.php';
require_once $root.'/app/PageBuilderService.php';
AuthService::boot();
$admin=AuthService::requireAdmin();

$pageCode=strtoupper(trim((string)($_GET['page']??'HOME')));
$pages=PageContentService::pages();
$pageCodes=array_map(static fn(array $page): string => (string)$page['page_code'],$pages);
if(!in_array($pageCode,$pageCodes,true))$pageCode=$pageCodes[0]??'HOME';
$sections=PageContentService::adminSections($pageCode);
$sectionId=(int)($_GET['section']??0);
$section=null;
foreach($sections as $candidate)if((int)$candidate['site_page_section_id']===$sectionId){$section=$candidate;break;}
if(!$section)$section=$sections[0]??null;
$brandColors=[['name'=>'Paper','hex'=>'#F4EEE2'],['name'=>'Ink','hex'=>'#1C242B'],['name'=>'Wash','hex'=>'#668091'],['name'=>'Tension','hex'=>'#6B2938'],['name'=>'Memory','hex'=>'#66715C'],['name'=>'Choice','hex'=>'#B58E46']];try{$qc=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_COLORS"');$qc->execute();$bc=json_decode((string)$qc->fetchColumn(),true);if(is_array($bc)&&count($bc)>0)$brandColors=$bc;}catch(Throwable $e){}

$error=null;$notice=null;
$isAjaxSave=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
function builder_json_exit(array $payload,int $status=200):void{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        AuthService::requireCsrf();
        $action=(string)($_POST['action']??'');
        if($action==='create_page'){
            $newPageCode=PageContentService::addPage($admin,(string)($_POST['page_label']??''),(string)($_POST['page_slug']??''));
            header('Location: page-builder.php?page='.rawurlencode($newPageCode).'&saved=1');
            exit;
        }
        if($action==='add_section'){
            $label=trim((string)($_POST['section_label']??''));
            $layout=strtoupper(trim((string)($_POST['section_layout']??'CONTAINER')));
            if(!in_array($layout,PageContentService::LAYOUTS,true))$layout='CONTAINER';
            $newSectionId=PageContentService::addSection($admin,$pageCode,['label_text'=>$label,'layout_type'=>'CONTAINER']);
            $children=$layout==='CONTAINER'?[]:[['layout_type'=>$layout,'active'=>false,'label_text'=>'','title_text'=>'','content_html'=>'','bullets'=>[]]];
            PageBuilderService::saveRows($admin,$newSectionId,[['layout_type'=>'CONTAINER','active'=>true,'children'=>$children]]);
            if($isAjaxSave)builder_json_exit(['ok'=>true,'section_id'=>$newSectionId,'label'=>$label]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$newSectionId.'&saved=1');
            exit;
        }
        if($action==='reorder_sections'){
            $order=json_decode((string)($_POST['section_order']??'[]'),true,10,JSON_THROW_ON_ERROR);
            if(!is_array($order))throw new RuntimeException('The section order is invalid.');
            PageContentService::reorderSections($admin,$pageCode,array_map('intval',$order));
            if($isAjaxSave)builder_json_exit(['ok'=>true]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action==='rename_section'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::renameSection($admin,$sectionId,(string)($_POST['section_label']??''));
            if($isAjaxSave)builder_json_exit(['ok'=>true,'section_id'=>$sectionId,'label'=>(string)($_POST['section_label']??'')]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action==='delete_section'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::deleteSection($admin,$sectionId);
            if($isAjaxSave)builder_json_exit(['ok'=>true]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&saved=1');
            exit;
        }
        if($action==='save_hero'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::saveHero($admin,$sectionId,$_POST);
            if($isAjaxSave)builder_json_exit(['ok'=>true]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action==='save_section'){
            $sectionId=(int)($_POST['section_id']??0);
            PageContentService::saveSection($admin,$sectionId,$_POST);
            if(isset($_POST['active']))PageContentService::setSectionActive($admin,$sectionId,((string)$_POST['active']==='1'));
            if($isAjaxSave)builder_json_exit(['ok'=>true]);
            header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
            exit;
        }
        if($action!=='save_page_builder')throw new RuntimeException('Unknown page builder action.');
        $sectionId=(int)($_POST['section_id']??0);
        $sectionById=null;
        foreach($sections as $candidate)if((int)$candidate['site_page_section_id']===$sectionId){$sectionById=$candidate;break;}
        if(!$sectionById)throw new RuntimeException('Page section not found.');
        if(($sectionById['section_code']??'')==='HERO')throw new RuntimeException('Hero sections use their dedicated editor.');
        $rows=json_decode((string)($_POST['rows_payload']??'[]'),true,20,JSON_THROW_ON_ERROR);
        if(!is_array($rows))throw new RuntimeException('The builder tree is invalid.');
        PageBuilderService::saveRows($admin,$sectionId,$rows);
        if($isAjaxSave)builder_json_exit(['ok'=>true]);
        header('Location: page-builder.php?page='.rawurlencode($pageCode).'&section='.$sectionId.'&saved=1');
        exit;
    }catch(Throwable $e){
        if($isAjaxSave)builder_json_exit(['ok'=>false,'error'=>$e->getMessage()],422);
        $error=$e->getMessage();
    }
}
if(isset($_GET['saved']))$notice='Changes saved.';
$rows=$section?PageContentService::editorRows($section):[];
$heroText=['eyebrow'=>'','title'=>'','content'=>''];
if($section&&($section['section_code']??'')==='HERO'){
    $heroRow=$rows[0]??[];
    if(($heroRow['layout_type']??'')==='CONTAINER'&&!empty($heroRow['children']))$heroRow=$heroRow['children'][0]??$heroRow;
    if((string)($section['eyebrow_text']??'')!=='')$heroText['eyebrow']=(string)$section['eyebrow_text'];else $heroText['eyebrow']=(string)($heroRow['label_text']??$heroRow['eyebrow_text']??'');
    if((string)($section['title_text']??'')!=='')$heroText['title']=(string)$section['title_text'];else $heroText['title']=(string)($heroRow['title_text']??'');
    if((string)($section['content_html']??'')!=='')$heroText['content']=(string)$section['content_html'];else $heroText['content']=(string)($heroRow['content_html']??'');
}
$isFirstSection=$section&&!empty($sections)&&(int)$sections[0]['site_page_section_id']===(int)$section['site_page_section_id'];
$pageMedia=db()->query('SELECT media_id,title_text FROM writer_media_assets WHERE active=1 ORDER BY title_text,media_id')->fetchAll();
if($section&&($section['section_code']??'')!=='HERO'&&!$rows)$rows=[['layout_type'=>'CONTAINER','active'=>true,'children'=>[]]];
function builder_e(string $value): string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Page Builder</title>
<link rel="stylesheet" href="assets/toinky-admin-shell.css">
<link rel="stylesheet" href="assets/toinky-admin-workspace.css">
<link rel="stylesheet" href="assets/toinky-page-builder.css?v=20261002-ai-import">
<style>.toinky-confirm-overlay{position:fixed;inset:0;background:rgba(28,36,43,.55);display:flex;align-items:center;justify-content:center;z-index:1200;padding:18px}.toinky-confirm-overlay[hidden]{display:none}.toinky-confirm-dialog{background:#F4EEE2;color:#1C242B;border-radius:14px;padding:22px;max-width:420px;width:100%;box-shadow:0 18px 50px rgba(0,0,0,.35);font-family:Inter,system-ui,sans-serif}.toinky-confirm-dialog p{margin:0 0 18px;font-size:15px;line-height:1.5}.toinky-confirm-actions{display:flex;gap:10px;justify-content:flex-end}.toinky-confirm-actions .btn{border:0;border-radius:8px;background:#B58E46;color:#1C242B;padding:10px 14px;font-weight:800;cursor:pointer;font:inherit}.toinky-confirm-actions .btn.alt{background:#fff;color:#6B2938;border:1px solid #6B2938}.toinky-confirm-actions .btn.danger{background:#6B2938;color:#fff}.page-create-bar{background:#F4EEE2;border-bottom:1px solid #d8cfbc;padding:12px 18px}.page-create-bar .page-create-form{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;font-family:Inter,system-ui,sans-serif;color:#1C242B}.page-create-bar strong{font-size:15px;align-self:center}.page-create-bar label{display:flex;flex-direction:column;gap:4px;font-size:12px;font-weight:700}.page-create-bar input{padding:9px 11px;border:1px solid #b9ad93;border-radius:8px;font-size:14px;min-width:190px;background:#fff;color:#1C242B}.page-create-bar button{border:0;border-radius:8px;background:#B58E46;color:#1C242B;padding:10px 16px;font-weight:800;cursor:pointer;font:inherit}.page-create-bar summary{cursor:pointer}</style></head>
<body class="page-builder-app">
<header class="page-builder-topbar">
<a href="admin.php?view=overview" class="page-builder-back">Back to Dashboard</a>
<strong>Page Builder</strong>
<label>Page <select id="page-select"><?php foreach($pages as $page):?><option value="<?=builder_e((string)$page['page_code'])?>" <?=$pageCode===$page['page_code']?'selected':''?>><?=builder_e((string)$page['label_text'])?></option><?php endforeach;?></select></label>
<button type="button" id="preview-refresh">Render Draft</button>
</header>
<details class="page-create-bar">
<summary><strong>New page</strong></summary>
<form method="post" class="page-create-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="create_page">
<label>Page name<input type="text" name="page_label" maxlength="120" placeholder="Page name" required></label>
<label>Address<input type="text" name="page_slug" maxlength="60" placeholder="page-address" required></label>
<button type="submit">Add Page</button>
</form>
</details>
<?php if($error):?><p class="page-builder-alert error"><?=builder_e($error)?></p><?php elseif($notice):?><p class="page-builder-alert notice"><?=builder_e($notice)?></p><?php endif;?>
<main class="page-builder-shell">
<aside class="page-builder-sidebar" id="page-builder-sidebar">
<form method="post" id="section-add-form" class="section-add-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="add_section">
<label>New section<input type="text" name="section_label" maxlength="120" placeholder="Section name" required></label>
<label>First content<select name="section_layout"><option value="CONTAINER">Empty Container</option><option value="TEXT_TOP">Top Text Only</option><option value="TEXT_CENTER">Text Center</option><option value="IMAGE_RIGHT">Image On Right</option><option value="IMAGE_LEFT">Image On Left</option><option value="TWO_COLUMN">Two Columns</option><option value="THREE_COLUMN">Three Columns</option><option value="FOUR_COLUMN">Four Columns</option><option value="CARDS_ONLY">Cards Only</option><option value="TEXT_BULLETS">Text With Bulleted List</option><option value="STORY_CARDS">Story Cards</option><option value="SOCIALS">Social Buttons</option></select></label>
<button type="submit">Add Section</button>
</form>
<form method="post" id="section-order-form" class="section-order-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="reorder_sections"><input type="hidden" name="section_order" id="section-order">
<p class="builder-muted">Drag sections to reorder them. Hero sections stay fixed.</p><button type="submit" id="section-order-save" disabled>Save Section Order</button>
</form>
<nav class="page-builder-sections" id="page-builder-sections" aria-label="Page sections">
<?php foreach($sections as $item):$hero=($item['section_code']??'')==='HERO';?>
<a class="page-builder-section <?=$section&&$item['site_page_section_id']===$section['site_page_section_id']?'selected':''?>" href="?page=<?=rawurlencode($pageCode)?>&section=<?=(int)$item['site_page_section_id']?>" data-section-id="<?=(int)$item['site_page_section_id']?>" data-fixed="<?=$hero?'1':'0'?>" draggable="<?=$hero?'false':'true'?>">
<span><?=builder_e((string)($item['label_text']??'Section'))?></span><small><?=$hero?'Hero':'Section'?></small>
</a>
<?php endforeach;?>
</nav>
<?php if($section&&($section['section_code']??'')!=='HERO'):?>
<div class="section-tools">
<form method="post" class="section-rename-form"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="rename_section"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><label>Section name<input name="section_label" maxlength="120" value="<?=builder_e((string)($section['label_text']??''))?>" required></label><button type="submit">Rename</button></form>
<form method="post" class="section-delete-form" data-confirm="Delete this section? This cannot be undone."><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="delete_section"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><button type="submit" <?=!empty($section['is_default'])?'disabled title="Default sections can be hidden but not deleted."':'title="Delete section"'?>>Delete</button></form>
</div>
<?php
// Section Settings: the full section editor (layout, image, text, background,
// overlay, visibility). This replaces the old admin Pages editor. Fields the
// Pages view did not expose (columns, padding, typography) are carried as
// hidden inputs at their current values so saveSection never resets them.
$__cols=json_decode((string)($section['columns_json']??''),true);
if(!is_array($__cols))$__cols=[];
$__col=function(int $n,string $key)use($__cols):string{return (string)($__cols[$n][$key]??'');};
$__sec=function(string $key)use($section):string{return (string)($section[$key]??'');};
?>
<details class="section-settings" open>
<summary><strong>Section Settings</strong><span class="builder-muted">Layout, image, text, background &amp; visibility</span></summary>
<form method="post" class="section-add-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_section"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>">
<label>Layout<select name="layout_type"><?php foreach(PageContentService::LAYOUTS as $__layout):?><option value="<?=builder_e($__layout)?>" <?=(string)($section['layout_type']??'')===$__layout?'selected':''?>><?=builder_e(ucwords(strtolower(str_replace('_',' ',$__layout))))?></option><?php endforeach;?></select></label>
<label>Section image<select name="image_media_id"><option value="">No image</option><?php foreach($pageMedia as $media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['image_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=builder_e((string)($media['title_text']??''))?></option><?php endforeach;?></select></label>
<label>Eyebrow<input name="eyebrow_text" maxlength="190" value="<?=builder_e($__sec('eyebrow_text'))?>"></label>
<label>Title<input name="title_text" maxlength="500" value="<?=builder_e($__sec('title_text'))?>"></label>
<label>Intro<textarea name="content_html" rows="3"><?=builder_e($__sec('content_html'))?></textarea></label>
<label>Background<select name="background_mode"><option value="DEFAULT" <?=$__sec('background_mode')==='DEFAULT'?'selected':''?>>Default design</option><option value="COLOR" <?=$__sec('background_mode')==='COLOR'?'selected':''?>>Color</option><option value="MEDIA" <?=$__sec('background_mode')==='MEDIA'?'selected':''?>>Media image</option></select></label>
<label>Background color<input type="color" name="background_color" value="<?=builder_e($__sec('background_color')!==''?$__sec('background_color'):'#F4EEE2')?>"></label>
<label>Background image<select name="background_media_id"><option value="">No image</option><?php foreach($pageMedia as $media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['background_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=builder_e((string)($media['title_text']??''))?></option><?php endforeach;?></select></label>
<label>Background fit<select name="background_fit"><?php foreach(PageContentService::BACKGROUND_FITS as $__fit):?><option value="<?=builder_e($__fit)?>" <?=$__sec('background_fit')===$__fit?'selected':''?>><?=builder_e(ucwords(strtolower($__fit)))?></option><?php endforeach;?></select></label>
<label>Background opacity<input type="number" name="background_opacity" min="0" max="100" value="<?=builder_e($__sec('background_opacity')!==''?$__sec('background_opacity'):'100')?>"></label>
<label>Overlay<select name="overlay_mode"><option value="NONE" <?=$__sec('overlay_mode')==='NONE'?'selected':''?>>None</option><option value="COLOR" <?=$__sec('overlay_mode')==='COLOR'?'selected':''?>>Color</option><option value="GRADIENT" <?=$__sec('overlay_mode')==='GRADIENT'?'selected':''?>>Gradient</option></select></label>
<label>Overlay start color<input type="color" name="overlay_color_start" value="<?=builder_e($__sec('overlay_color_start')!==''?$__sec('overlay_color_start'):'#1C242B')?>"></label>
<label>Overlay end color<input type="color" name="overlay_color_end" value="<?=builder_e($__sec('overlay_color_end')!==''?$__sec('overlay_color_end'):'#6B2938')?>"></label>
<label>Overlay opacity<input type="number" name="overlay_opacity" min="0" max="100" value="<?=builder_e($__sec('overlay_opacity')!==''?$__sec('overlay_opacity'):'0')?>"></label>
<label>Visibility<select name="active"><option value="1" <?=!empty($section['active'])?'selected':''?>>Visible</option><option value="0" <?=empty($section['active'])?'selected':''?>>Hidden</option></select></label>
<?php for($__i=1;$__i<=3;$__i++):?>
<input type="hidden" name="column_<?=$__i?>_title" value="<?=builder_e($__col($__i-1,'title'))?>">
<input type="hidden" name="column_<?=$__i?>_content" value="<?=builder_e($__col($__i-1,'content'))?>">
<input type="hidden" name="column_<?=$__i?>_image_media_id" value="<?=builder_e($__col($__i-1,'image_media_id'))?>">
<?php endfor;?>
<?php foreach(['padding_top_px','padding_right_px','padding_bottom_px','padding_left_px','mobile_padding_top_px','mobile_padding_right_px','mobile_padding_bottom_px','mobile_padding_left_px','label_font_size','label_color','label_transform','title_font_size','title_color','title_transform','body_font_size','body_color','body_transform'] as $__k):?>
<input type="hidden" name="<?=builder_e($__k)?>" value="<?=builder_e($__sec($__k))?>">
<?php endforeach;?>
<button type="submit">Save Section Settings</button>
</form>
</details>
<?php if($isFirstSection):?>
<form method="post" class="section-add-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_hero"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>">
<label>Hero image<select name="image_media_id"><option value="">No image</option><?php foreach($pageMedia as $media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['image_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=builder_e((string)($media['title_text']??''))?></option><?php endforeach;?></select></label>
<?php if(!empty($section['image_path'])):?><p class="builder-muted">Current hero image: <?=builder_e((string)($section['image_alt']??$section['image_path']))?></p><?php endif;?>
<p class="builder-muted">This is the page's first section, shown as the hero. Set its image here; edit its text in the section rows below.</p>
<button type="submit">Save Hero Image</button>
</form>
<?php endif;?>
<form method="post" id="builder-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_page_builder"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>"><input type="hidden" name="rows_payload" id="rows-payload">
<div class="inspector-heading"><span>Editing</span><strong><?=builder_e((string)$section['label_text'])?></strong></div>
<div class="builder-inspector-tools"><button type="button" id="structure-open" data-structure-open aria-label="Open Structure" title="Structure">☷</button><button type="button" id="ai-rules-copy" aria-label="Copy AI rules" title="Copy AI rules">AI</button><button type="button" id="ai-outline-copy" aria-label="Copy outline prompt" title="Copy outline prompt">?</button><button type="button" id="ai-json-open" aria-label="Import AI JSON" title="Import AI JSON">{}</button></div>
<p class="builder-tool-status" id="builder-ai-status" aria-live="polite"></p>
<div class="inspector-tabs" role="tablist"><button type="button" class="active" data-inspector-tab="content">Content</button><button type="button" data-inspector-tab="style">Style</button><button type="button" data-inspector-tab="advanced">Advanced</button></div>
<section class="inspector-panel active" data-inspector-panel="content"><div id="content-properties"><p class="builder-muted">Select a section or row.</p></div></section>
<section class="inspector-panel" data-inspector-panel="style"><div id="style-properties"></div></section>
<section class="inspector-panel" data-inspector-panel="advanced"><div id="advanced-properties"></div></section>
<button class="builder-save" type="submit">Save Changes</button>
</form>
<div class="builder-ai-modal" id="builder-ai-modal" hidden>
<div class="builder-ai-dialog" role="dialog" aria-modal="true" aria-labelledby="builder-ai-title">
<div class="builder-ai-head"><strong id="builder-ai-title">Import AI JSON</strong><button type="button" id="builder-ai-close" aria-label="Close">x</button></div>
<p class="builder-muted">Paste JSON only. This replaces the current section draft after validation; click Save Changes when ready.</p>
<textarea id="builder-ai-json" spellcheck="false" placeholder='{"rows":[{"layout_type":"CONTAINER","active":true,"children":[]}]}'></textarea>
<p class="builder-tool-status error" id="builder-ai-error" aria-live="polite"></p>
<div class="builder-ai-actions"><button type="button" id="builder-ai-import">Import Draft</button><button type="button" id="builder-ai-clear">Clear</button></div>
</div>
</div>
<?php else:?><?php if($section):?>
<div class="inspector-heading"><span>Editing</span><strong><?=builder_e((string)($section['label_text']??'Hero'))?></strong></div>
<p class="builder-muted">Hero text and image. Saving here sets the hero's own text; the rows behind it stay untouched.</p>
<form method="post" class="section-add-form">
<input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="save_hero"><input type="hidden" name="section_id" value="<?=(int)$section['site_page_section_id']?>">
<label>Eyebrow<input name="eyebrow_text" maxlength="190" value="<?=builder_e($heroText['eyebrow'])?>"></label>
<label>Title<input name="title_text" maxlength="500" value="<?=builder_e($heroText['title'])?>"></label>
<label>Intro<textarea name="content_html" rows="4"><?=builder_e($heroText['content'])?></textarea></label>
<label>Hero image<select name="image_media_id"><option value="">No image</option><?php foreach($pageMedia as $media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['image_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=builder_e((string)($media['title_text']??''))?></option><?php endforeach;?></select></label>
<?php if(!empty($section['image_path'])):?><p class="builder-muted">Current hero image: <?=builder_e((string)($section['image_alt']??$section['image_path']))?></p><?php endif;?>
<button type="submit">Save Hero</button>
</form>
<?php else:?><p class="builder-muted">Hero sections keep their dedicated Hero editor. Select a content section to edit its Container tree.</p><?php endif;?><?php endif;?>
</aside>
<button type="button" id="sidebar-toggle" aria-controls="page-builder-sidebar" aria-expanded="true" aria-label="Hide properties sidebar" title="Hide properties sidebar"><span aria-hidden="true">&lt;</span><span class="page-builder-toggle-label">Hide properties sidebar</span></button>
<section class="page-builder-preview"><div class="preview-toolbar"><span>Live preview</span><button type="button" id="open-preview">Open page</button></div><iframe id="page-preview" name="page-preview" title="Page preview" src="index.php?view=<?=$pageCode==='HOME'?'public':strtolower($pageCode)?>"></iframe><form method="post" target="page-preview" id="preview-form" action="index.php?view=<?=$pageCode==='HOME'?'public':strtolower($pageCode)?>" hidden><input type="hidden" name="csrf_token" value="<?=htmlspecialchars(AuthService::csrfToken(),ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="action" value="builder_preview"><input type="hidden" name="section_id" value="<?=(int)($section['site_page_section_id']??0)?>"><input type="hidden" name="rows_payload" id="preview-rows-payload"></form></section>
<div class="toinky-confirm-overlay" id="toinky-confirm" hidden><div class="toinky-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="toinky-confirm-message"><p id="toinky-confirm-message"></p><div class="toinky-confirm-actions"><button type="button" class="btn alt" data-toinky-confirm-cancel>Cancel</button><button type="button" class="btn danger" data-toinky-confirm-ok>Confirm</button></div></div></div></main>
<script>
window.toinkyPageBuilder={page:<?=json_encode($pageCode)?>,brandColors:<?=json_encode($brandColors,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,rows:<?=json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,media:<?=json_encode($pageMedia,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>,hero:<?=json_encode(($section['section_code']??'')==='HERO')?>};
</script><script>const toinkyConfirm=message=>new Promise(resolve=>{const overlay=document.getElementById('toinky-confirm');if(!overlay){resolve(window.confirm(message));return;}if(!overlay.hidden){resolve(false);return;}const text=overlay.querySelector('#toinky-confirm-message'),okButton=overlay.querySelector('[data-toinky-confirm-ok]'),cancelButton=overlay.querySelector('[data-toinky-confirm-cancel]'),previous=document.activeElement;text.textContent=message;overlay.hidden=false;const close=value=>{overlay.hidden=true;okButton.removeEventListener('click',onOk);cancelButton.removeEventListener('click',onCancel);overlay.removeEventListener('click',onBackdrop);document.removeEventListener('keydown',onKey);if(previous&&previous.focus)previous.focus();resolve(value);};const onOk=()=>close(true),onCancel=()=>close(false),onBackdrop=event=>{if(event.target===overlay)close(false);},onKey=event=>{if(event.key==='Escape')close(false);};okButton.addEventListener('click',onOk);cancelButton.addEventListener('click',onCancel);overlay.addEventListener('click',onBackdrop);document.addEventListener('keydown',onKey);cancelButton.focus();});
document.addEventListener('submit',event=>{const form=event.target;if(!(form instanceof HTMLFormElement)||!form.dataset.confirm||form.dataset.toinkyConfirmed==='true')return;event.preventDefault();event.stopPropagation();event.stopImmediatePropagation();const submitter=event.submitter||null;toinkyConfirm(form.dataset.confirm).then(ok=>{if(!ok)return;form.dataset.toinkyConfirmed='true';if(submitter)form.requestSubmit(submitter);else form.requestSubmit();});},true);</script>
<script src="assets/toinky-page-builder.js?v=20261002-ai-import" defer></script>
</body>
</html>
