<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();require_once $root.'/app/AuthService.php';require_once $root.'/app/MediaLibraryService.php';require_once $root.'/app/StoryAuthoringService.php';require_once $root.'/app/StoryCorrectionService.php';AuthService::boot();$admin=AuthService::requireAdmin();$themeFonts=[
'Brand — Cormorant Garamond'=>'Cormorant Garamond, Georgia, serif',
'Brand — Instrument Sans'=>'Instrument Sans, Arial, sans-serif',
'Brand — IBM Plex Mono'=>'IBM Plex Mono, monospace',
'Adventure — Cinzel'=>'Cinzel, Georgia, serif',
'Adventure — Alegreya Sans SC'=>'Alegreya Sans SC, Arial, sans-serif',
'Comedy — Fredoka'=>'Fredoka, Arial, sans-serif',
'Comedy — Baloo 2'=>'Baloo 2, Arial, sans-serif',
'Contemporary — DM Sans'=>'DM Sans, Arial, sans-serif',
'Contemporary — Manrope'=>'Manrope, Arial, sans-serif',
'Crime — Oswald'=>'Oswald, Arial, sans-serif',
'Crime — Roboto Slab'=>'Roboto Slab, Georgia, serif',
'Drama — Playfair Display'=>'Playfair Display, Georgia, serif',
'Drama — Cormorant Garamond'=>'Cormorant Garamond, Georgia, serif',
'Fantasy — Uncial Antiqua'=>'Uncial Antiqua, Georgia, serif',
'Fantasy — MedievalSharp'=>'MedievalSharp, Georgia, serif',
'Historical — Libre Baskerville'=>'Libre Baskerville, Georgia, serif',
'Historical — EB Garamond'=>'EB Garamond, Georgia, serif',
'Horror — Creepster'=>'Creepster, Georgia, serif',
'Horror — Nosifer'=>'Nosifer, Georgia, serif',
'Mystery — Special Elite'=>'Special Elite, Georgia, serif',
'Mystery — IM Fell English'=>'IM Fell English, Georgia, serif',
'Political Intrigue — Spectral'=>'Spectral, Georgia, serif',
'Political Intrigue — Crimson Pro'=>'Crimson Pro, Georgia, serif',
'Romance — Lora'=>'Lora, Georgia, serif',
'Romance — Parisienne'=>'Parisienne, cursive',
'Science Fiction — Orbitron'=>'Orbitron, Arial, sans-serif',
'Science Fiction — Space Grotesk'=>'Space Grotesk, Arial, sans-serif',
'Steampunk — Rye'=>'Rye, Georgia, serif',
'Steampunk — Grenze Gotisch'=>'Grenze Gotisch, Georgia, serif',
'Survival — Barlow Condensed'=>'Barlow Condensed, Arial, sans-serif',
'Survival — Archivo Narrow'=>'Archivo Narrow, Arial, sans-serif',
'Thriller — Bebas Neue'=>'Bebas Neue, Arial, sans-serif',
'Thriller — Saira Condensed'=>'Saira Condensed, Arial, sans-serif',
'Body — Inter'=>'Inter, Arial, sans-serif',
'Body — Merriweather'=>'Merriweather, Georgia, serif',
'Legacy — Inter System'=>'Inter, system-ui, sans-serif',
'Legacy — Trebuchet System'=>'Trebuchet MS, system-ui, sans-serif',
'System — Georgia'=>'Georgia, Times New Roman, serif'
];
$fontWeights=['300'=>'Light','400'=>'Regular','500'=>'Medium','600'=>'Semi Bold','700'=>'Bold','800'=>'Extra Bold','900'=>'Black'];
$fontDecorations=['none'=>'None','underline'=>'Underline','overline'=>'Overline','line-through'=>'Line Through'];
$themeFontRoles=[
 'title'=>['label'=>'Titles','font'=>'title_font','color'=>'title_color','size'=>'title_size','weight'=>'title_weight','decoration'=>'title_decoration'],
 'heading'=>['label'=>'Headings','font'=>'heading_font','color'=>'heading_color','size'=>'heading_size','weight'=>'heading_weight','decoration'=>'heading_decoration'],
 'body'=>['label'=>'Main Content','font'=>'body_font','color'=>'text_color','size'=>'body_size','weight'=>'body_weight','decoration'=>'body_decoration'],
 'meta'=>['label'=>'Supporting Text','font'=>'meta_font','color'=>'meta_color','size'=>'meta_size','weight'=>'meta_weight','decoration'=>'meta_decoration'],
 'button'=>['label'=>'Buttons & Options','font'=>'button_font','color'=>'button_color','size'=>'button_size','weight'=>'button_weight','decoration'=>'button_decoration']
];
$brandFontRoles=[
 'body'=>['label'=>'Body & Main Content','selector'=>'body,input,select,textarea','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#1C242B','size'=>'16px','weight'=>'400','decoration'=>'none'],
 'headings'=>['label'=>'Headings & Titles','selector'=>'h1,h2,h3,.brand,.title','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#1C242B','size'=>'32px','weight'=>'700','decoration'=>'none'],
 'navigation'=>['label'=>'Navigation','selector'=>'.side a,.nav,.bar','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#F4EEE2','size'=>'14px','weight'=>'600','decoration'=>'none'],
 'labels'=>['label'=>'Labels & Metadata','selector'=>'label,.eyebrow,.group,.badge,.pill,.code,.slot,.deadline,.muted,small','family'=>'IBM Plex Mono, monospace','color'=>'#52606A','size'=>'12px','weight'=>'600','decoration'=>'none'],
 'buttons'=>['label'=>'Buttons','selector'=>'.btn,button,input[type="submit"]','family'=>'IBM Plex Mono, monospace','color'=>'#1C242B','size'=>'13px','weight'=>'600','decoration'=>'none'],
 'brand_context'=>['label'=>'Brand Context','selector'=>'.brand-context','family'=>'IBM Plex Mono, monospace','color'=>'#F4EEE2','size'=>'12px','weight'=>'600','decoration'=>'none'],
 'table_headings'=>['label'=>'Table Headings','selector'=>'th','family'=>'IBM Plex Mono, monospace','color'=>'#F4EEE2','size'=>'12px','weight'=>'700','decoration'=>'none']
];
$view=(string)($_GET['view']??'overview');$activeTheme=(int)($_GET['theme']??$_POST['genre_id']??0);$error=null;$notice=null;$generatedUrl=null;$auditText=null;$auditContext=null;
if($_SERVER['REQUEST_METHOD']==='POST'){try{AuthService::requireCsrf();$action=(string)($_POST['action']??'');if($action==='logout'){AuthService::logout();header('Location: ./?view=login');exit;}$target=(int)($_POST['user_id']??0);
 if($action==='invite'){$r=AuthService::createInvitation((string)$_POST['email'],(int)$admin['user_id']);$notice=$r['sent']?'Invitation email sent.':'Mail was unavailable; copy the invitation link below.';$generatedUrl=$r['url'];$view='invitations';}
 if($action==='reset_user'){$q=db()->prepare('SELECT email FROM users WHERE user_id=?');$q->execute([$target]);$email=$q->fetchColumn();if(!$email)throw new RuntimeException('User not found.');$r=AuthService::createPasswordResetForEmail((string)$email);$notice=$r&&$r['sent']?'Password-reset email sent.':'Mail was unavailable; copy the reset link below.';$generatedUrl=$r['url']??null;$view='accounts';}
 if($action==='toggle_status'){if($target===(int)$admin['user_id'])throw new RuntimeException('You cannot disable your own account.');db()->prepare('UPDATE users SET status=IF(status="ACTIVE","DISABLED","ACTIVE") WHERE user_id=?')->execute([$target]);$notice='Account status updated.';$view='accounts';}
 if($action==='toggle_admin'){if($target===(int)$admin['user_id'])throw new RuntimeException('You cannot remove your own administrator access.');db()->prepare('UPDATE users SET is_admin=IF(is_admin=1,0,1) WHERE user_id=?')->execute([$target]);$notice='Administrator access updated.';$view='accounts';}
 if($action==='toggle_writer'){$enabled=!AuthService::hasRole($target,'WRITER');AuthService::setRole($target,'WRITER',$enabled,(int)$admin['user_id']);$notice='Story Writer access updated.';$view='accounts';}
 if($action==='review_writer'){AuthService::reviewWriterRequest((int)$_POST['writer_request_id'],(string)$_POST['decision']==='approve',(int)$admin['user_id'],(string)($_POST['reviewer_notes']??''));$notice='Story Writer request reviewed.';$view='writer_requests';}
 if($action==='review_story'){$versionId=(int)$_POST['plot_version_id'];$status=in_array((string)$_POST['decision'],['APPROVED','CHANGES_REQUESTED','PUBLISHED','ARCHIVED'],true)?(string)$_POST['decision']:'CHANGES_REQUESTED';$q=db()->prepare('SELECT plot_id FROM plot_versions WHERE plot_version_id=?');$q->execute([$versionId]);$reviewPlot=(int)$q->fetchColumn();if(!$reviewPlot)throw new RuntimeException('Submitted revision not found.');db()->prepare('UPDATE plot_versions SET workflow_status=?,reviewed_by_user_id=?,review_notes=?,reviewed_at_utc=UTC_TIMESTAMP() WHERE plot_version_id=?')->execute([$status,$admin['user_id'],trim((string)($_POST['review_notes']??''))?:null,$versionId]);db()->prepare('UPDATE plots SET workflow_status=?,status=IF(?="PUBLISHED","active",status) WHERE plot_id=?')->execute([$status,$status,$reviewPlot]);$notice='Story review status updated.';$view='story_review';$_GET['version']=$versionId;}
 if($action==='audit_story'){$auditVersionId=(int)($_POST['plot_version_id']??0);$audit=StoryAuthoringService::saveAudit((int)$_POST['plot_id'],(int)$admin['user_id'],$auditVersionId?:null);$auditText=$audit['text'];$q=db()->prepare('SELECT p.code,p.title,v.version_no FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id WHERE v.plot_version_id=?');$q->execute([$auditVersionId]);$auditContext=$q->fetch();$notice='Story review package generated below.';$view='story_review';$_GET['version']=$auditVersionId;}
 if($action==='import_story_corrections'){$versionId=(int)$_POST['plot_version_id'];$result=StoryCorrectionService::import($admin,$versionId,$_FILES['correction_file']??[]);$notice='Applied '.$result['change_count'].' correction(s) and created revision '.$result['version_no'].'.';$view='story_review';$_GET['version']=$versionId;}
 if($action==='save_theme'){
  $genreId=(int)$_POST['genre_id'];
  foreach(['primary_color','secondary_color','accent_color','background_color','surface_color','text_color','heading_color','title_color','meta_color','button_color']as$color){$_POST[$color]=strtoupper(trim((string)($_POST[$color]??'')));if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$_POST[$color]))throw new RuntimeException('Theme colors must use #RRGGBB or #RRGGBBAA hexadecimal values.');}
  $typeValues=[];foreach($themeFontRoles as$role=>$fields){$family=trim((string)($_POST[$fields['font']]??''));$size=trim((string)($_POST[$fields['size']]??''));$weight=(string)($_POST[$fields['weight']]??'400');$decoration=(string)($_POST[$fields['decoration']]??'none');if(!in_array($family,$themeFonts,true))throw new RuntimeException('Choose fonts from the supported font list.');if(!preg_match('/^(?:0\.[1-9][0-9]?|[1-9][0-9]{0,2})(?:px|rem|em|%)$/',$size))throw new RuntimeException('Font sizes must use px, rem, em, or %.');if(!isset($fontWeights[$weight])||!isset($fontDecorations[$decoration]))throw new RuntimeException('Choose a supported font weight and decoration.');$typeValues[$role]=[$family,$size,$weight,$decoration];}
  $ids=[];$paths=[];foreach(['BACKGROUND','DIVIDER','CARD']as$type){$field=strtolower($type).'_media_id';$id=(int)($_POST[$field]??0);$ids[$type]=$id?:null;$paths[$type]=$id?MediaLibraryService::requireVisible($admin,$id,$type)['file_path']:null;}
  db()->prepare('UPDATE genre_themes SET primary_color=?,secondary_color=?,accent_color=?,background_color=?,surface_color=?,text_color=?,heading_color=?,title_color=?,meta_color=?,button_color=?,title_font=?,title_size=?,title_weight=?,title_decoration=?,heading_font=?,heading_size=?,heading_weight=?,heading_decoration=?,body_font=?,body_size=?,body_weight=?,body_decoration=?,meta_font=?,meta_size=?,meta_weight=?,meta_decoration=?,button_font=?,button_size=?,button_weight=?,button_decoration=?,background_media_id=?,divider_media_id=?,card_media_id=?,background_image_path=?,divider_image_path=?,card_image_path=?,updated_by_user_id=? WHERE genre_id=?')->execute([$_POST['primary_color'],$_POST['secondary_color'],$_POST['accent_color'],$_POST['background_color'],$_POST['surface_color'],$_POST['text_color'],$_POST['heading_color'],$_POST['title_color'],$_POST['meta_color'],$_POST['button_color'],...$typeValues['title'],...$typeValues['heading'],...$typeValues['body'],...$typeValues['meta'],...$typeValues['button'],$ids['BACKGROUND'],$ids['DIVIDER'],$ids['CARD'],$paths['BACKGROUND'],$paths['DIVIDER'],$paths['CARD'],$admin['user_id'],$genreId]);
  $notice='Genre theme saved.';$view='themes';
 }
 if($action==='create_theme'){$label=trim((string)($_POST['label_text']??''));$code=strtoupper(preg_replace('/[^A-Za-z0-9]+/','_',$label));$code=trim($code,'_');$description=trim((string)($_POST['description_text']??''));if($label===''||$code==='')throw new RuntimeException('Enter a theme name.');if(mb_strlen($label)>120)throw new RuntimeException('Theme names may not exceed 120 characters.');db()->beginTransaction();try{$sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM story_genres')->fetchColumn();$q=db()->prepare('INSERT INTO story_genres(genre_code,label_text,description_text,sort_order,active) VALUES(?,?,?,?,1)');$q->execute([$code,$label,$description,$sort]);$genreId=(int)db()->lastInsertId();db()->prepare('INSERT INTO genre_themes(genre_id,updated_by_user_id) VALUES(?,?)')->execute([$genreId,$admin['user_id']]);db()->commit();}catch(Throwable$e){if(db()->inTransaction())db()->rollBack();throw $e;}$activeTheme=$genreId;$notice='New theme created.';$view='themes';}
 if($action==='save_brand_fonts'){$styles=[];foreach($brandFontRoles as$key=>$defaults){$family=trim((string)($_POST['brand_'.$key.'_family']??''));$color=strtoupper(trim((string)($_POST['brand_'.$key.'_color']??'')));$size=trim((string)($_POST['brand_'.$key.'_size']??''));$weight=(string)($_POST['brand_'.$key.'_weight']??'400');$decoration=(string)($_POST['brand_'.$key.'_decoration']??'none');if(!in_array($family,$themeFonts,true))throw new RuntimeException('Choose fonts from the supported font list.');if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$color))throw new RuntimeException('Brand font colors must use #RRGGBB or #RRGGBBAA.');if(!preg_match('/^(?:0\.[1-9][0-9]?|[1-9][0-9]{0,2})(?:px|rem|em|%)$/',$size))throw new RuntimeException('Font sizes must use px, rem, em, or %.');if(!isset($fontWeights[$weight])||!isset($fontDecorations[$decoration]))throw new RuntimeException('Choose a supported font weight and decoration.');$styles[$key]=['family'=>$family,'color'=>$color,'size'=>$size,'weight'=>$weight,'decoration'=>$decoration];}$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES("BRAND_FONT_STYLES",?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');$q->execute([json_encode($styles,JSON_UNESCAPED_SLASHES),$admin['user_id']]);$notice='Brand fonts saved.';$view='brand_fonts';}
 if($action==='save_security'){$mode=in_array((string)($_POST['captcha_mode']??''),['BUILTIN','VISUAL','GOOGLE','OFF'],true)?(string)$_POST['captcha_mode']:'BUILTIN';$settings=['SECURITY_CAPTCHA_MODE'=>$mode,'GOOGLE_RECAPTCHA_SITE_KEY'=>trim((string)($_POST['recaptcha_site_key']??''))];if(trim((string)($_POST['recaptcha_secret_key']??''))!=='')$settings['GOOGLE_RECAPTCHA_SECRET_KEY']=trim((string)$_POST['recaptcha_secret_key']);$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');foreach($settings as$key=>$value)$q->execute([$key,$value,$admin['user_id']]);$notice='Login security settings saved.';$view='security';}
}catch(Throwable$e){$error=$e->getMessage();}}
$counts=['users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),'human'=>(int)db()->query('SELECT COUNT(*) FROM games')->fetchColumn(),'simulations'=>(int)db()->query('SELECT COUNT(*) FROM simulations')->fetchColumn(),'ratings'=>(int)db()->query('SELECT COUNT(*) FROM story_ratings')->fetchColumn()];
$users=$human=$sims=$invites=[];$detailUser=null;$hosted=$joined=$ratings=[];if($view==='accounts'||$view==='overview'){$q=db()->query('SELECT u.*,COUNT(DISTINCT gh.game_id) hosted_count,COUNT(DISTINCT gp.game_id) joined_count,COUNT(DISTINCT sr.story_rating_id) rating_count,ROUND(AVG(sr.rating),1) average_rating,EXISTS(SELECT 1 FROM user_platform_roles xur JOIN platform_roles xr ON xr.platform_role_id=xur.platform_role_id WHERE xur.user_id=u.user_id AND xr.role_code="WRITER") is_writer FROM users u LEFT JOIN games gh ON gh.host_user_id=u.user_id LEFT JOIN game_players gp ON gp.user_id=u.user_id LEFT JOIN story_ratings sr ON sr.user_id=u.user_id GROUP BY u.user_id ORDER BY u.created_at_utc DESC');$users=$q->fetchAll();}
if($view==='human'){$q=db()->query('SELECT g.*,s.status simulation_status,p.title,p.genre,u.display_name host_name,COUNT(DISTINCT gp.user_id) player_count,ROUND(AVG(sr.rating),1) average_rating,COUNT(DISTINCT sr.story_rating_id) rating_count FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id LEFT JOIN game_players gp ON gp.game_id=g.game_id LEFT JOIN story_ratings sr ON sr.game_id=g.game_id GROUP BY g.game_id ORDER BY g.created_at_utc DESC');$human=$q->fetchAll();}
if($view==='simulations'||$view==='simulation'){$where=$view==='simulations'?'WHERE g.game_id IS NULL':'';$q=db()->query('SELECT s.simulation_id,s.status,s.current_phase,s.elapsed_seconds,s.created_at_utc,p.title,p.genre,g.game_id,COALESCE(hu.display_name,"System") host_name,COALESCE(cc.human_count,0) human_count,COALESCE(cc.ai_count,0) ai_count,rr.average_rating,COALESCE(rr.rating_count,0) rating_count FROM simulations s JOIN plots p ON p.plot_id=s.plot_id LEFT JOIN games g ON g.simulation_id=s.simulation_id LEFT JOIN users hu ON hu.user_id=g.host_user_id LEFT JOIN (SELECT simulation_id,SUM(controller_type="HUMAN") human_count,SUM(controller_type="AI") ai_count FROM characters GROUP BY simulation_id) cc ON cc.simulation_id=s.simulation_id LEFT JOIN (SELECT game_id,ROUND(AVG(rating),1) average_rating,COUNT(*) rating_count FROM story_ratings GROUP BY game_id) rr ON rr.game_id=g.game_id '.$where.' ORDER BY s.simulation_id DESC LIMIT 250');$sims=$q->fetchAll();}
if($view==='user'){$uid=(int)($_GET['user']??0);$q=db()->prepare('SELECT user_id,email,display_name,is_admin,status,created_at_utc,last_login_at_utc FROM users WHERE user_id=?');$q->execute([$uid]);$detailUser=$q->fetch();if(!$detailUser)throw new RuntimeException('User not found.');$q=db()->prepare('SELECT g.game_id,g.simulation_id,g.status,g.created_at_utc,p.title,p.genre FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE g.host_user_id=? ORDER BY g.created_at_utc DESC');$q->execute([$uid]);$hosted=$q->fetchAll();$q=db()->prepare('SELECT g.game_id,g.simulation_id,g.status,g.created_at_utc,p.title,p.genre,u.display_name host_name,c.name character_name FROM game_players gp JOIN games g ON g.game_id=gp.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id LEFT JOIN characters c ON c.character_id=gp.character_id WHERE gp.user_id=? ORDER BY g.created_at_utc DESC');$q->execute([$uid]);$joined=$q->fetchAll();$q=db()->prepare('SELECT sr.rating,sr.review_text,sr.created_at_utc,p.title,p.genre,g.simulation_id FROM story_ratings sr JOIN games g ON g.game_id=sr.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE sr.user_id=? ORDER BY sr.created_at_utc DESC');$q->execute([$uid]);$ratings=$q->fetchAll();}
if($view==='invitations'){$q=db()->query('SELECT i.*,u.display_name invited_by FROM user_invitations i JOIN users u ON u.user_id=i.invited_by_user_id ORDER BY i.created_at_utc DESC LIMIT 200');$invites=$q->fetchAll();}
$writerRequests=$storyReviews=$themes=[];$themeMedia=[];$security=['SECURITY_CAPTCHA_MODE'=>'BUILTIN','GOOGLE_RECAPTCHA_SITE_KEY'=>'','GOOGLE_RECAPTCHA_SECRET_KEY'=>''];$brandFonts=$brandFontRoles;if($view==='writer_requests')$writerRequests=db()->query('SELECT r.*,u.display_name,u.email,reviewer.display_name reviewer_name FROM writer_access_requests r JOIN users u ON u.user_id=r.user_id LEFT JOIN users reviewer ON reviewer.user_id=r.reviewed_by_user_id ORDER BY FIELD(r.status,"PENDING","APPROVED","DECLINED","WITHDRAWN"),r.created_at_utc DESC')->fetchAll();if($view==='story_reviews')$storyReviews=db()->query('SELECT v.*,p.title,p.code,p.workflow_status plot_workflow,u.display_name submitted_by,reviewer.display_name reviewed_by FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id JOIN users u ON u.user_id=v.submitted_by_user_id LEFT JOIN users reviewer ON reviewer.user_id=v.reviewed_by_user_id ORDER BY FIELD(v.workflow_status,"SUBMITTED","CHANGES_REQUESTED","APPROVED","PUBLISHED","ARCHIVED"),v.created_at_utc DESC')->fetchAll();if($view==='themes'){$themes=db()->query('SELECT g.*,t.* FROM story_genres g JOIN genre_themes t ON t.genre_id=g.genre_id ORDER BY g.sort_order,g.label_text')->fetchAll();$themeMedia=MediaLibraryService::visibleTo($admin);}if($view==='brand_fonts'){$q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_FONT_STYLES"');$q->execute();$saved=json_decode((string)$q->fetchColumn(),true);if(is_array($saved))foreach($brandFonts as$key=>&$role)if(isset($saved[$key])&&is_array($saved[$key]))$role=array_merge($role,$saved[$key]);unset($role);}if($view==='security'){$q=db()->query('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN("SECURITY_CAPTCHA_MODE","GOOGLE_RECAPTCHA_SITE_KEY","GOOGLE_RECAPTCHA_SECRET_KEY")');foreach($q->fetchAll()as$row)$security[$row['setting_key']]=$row['setting_value'];}
function csrf_field():string{return'<input type="hidden" name="csrf_token" value="'.e(AuthService::csrfToken()).'">';}
function theme_rgb(string $color):string{return preg_match('/^#[0-9A-Fa-f]{6}/',$color)?strtoupper(substr($color,0,7)):'#000000';}
function theme_opacity(string $color):int{return preg_match('/^#[0-9A-Fa-f]{8}$/',$color)?(int)round(hexdec(substr($color,7,2))*100/255):100;}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Toinky Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond&family=Instrument+Sans&family=IBM+Plex+Mono&family=Cinzel&family=Alegreya+Sans+SC&family=Fredoka&family=Baloo+2&family=DM+Sans&family=Manrope&family=Oswald&family=Roboto+Slab&family=Playfair+Display&family=Uncial+Antiqua&family=MedievalSharp&family=Libre+Baskerville&family=EB+Garamond&family=Creepster&family=Nosifer&family=Special+Elite&family=IM+Fell+English&family=Spectral&family=Crimson+Pro&family=Lora&family=Parisienne&family=Orbitron&family=Space+Grotesk&family=Rye&family=Grenze+Gotisch&family=Barlow+Condensed&family=Archivo+Narrow&family=Bebas+Neue&family=Saira+Condensed&family=Inter&family=Merriweather&display=swap" rel="stylesheet">
<style>
:root{font-family:Inter,system-ui,sans-serif;color:#172033;background:#eef2f7}*{box-sizing:border-box}body{margin:0}.layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:#1C242B;color:#fff;padding:24px 16px}.brand{font-size:22px;font-weight:900;margin-bottom:28px}.group{font-size:11px;text-transform:uppercase;color:#F4EEE2;font-weight:800;letter-spacing:.08em;margin:22px 10px 7px}.side a{display:block;color:#F4EEE2;text-decoration:none;padding:9px 10px;border-radius:8px}.side a.active,.side a:hover{background:#66715C;color:#fff}.main{padding:28px;min-width:0}.top{display:flex;justify-content:space-between;align-items:center}.top a{color:#6B2938}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}.metric,.panel{background:#fff;border:1px solid #dbe3ec;border-radius:14px;padding:18px}.metric b{font-size:32px;display:block}.panel{margin-top:18px;overflow:auto}.table{width:100%;border-collapse:collapse;font-size:13px}.table th,.table td{text-align:left;border-bottom:1px solid #e5e7eb;padding:10px 7px;vertical-align:top}.btn{border:0;border-radius:8px;background:#B58E46;color:#1C242B;padding:8px 11px;font:inherit;font-weight:750;cursor:pointer;text-decoration:none;display:inline-block}.btn.alt{background:#fff;color:#273043;border:1px solid #cbd5e1}.field{display:block;margin:10px 0}.field input,.field textarea,.field select{width:min(480px,100%);display:block;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:5px}.notice,.error{padding:12px;border-radius:9px;margin:12px 0}.notice{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.audit{white-space:pre-wrap;background:#111827;color:#f8fafc;padding:16px;border-radius:8px;max-height:560px;overflow:auto;font:12px/1.5 ui-monospace,monospace}.theme-preview{padding:18px;border-radius:10px;margin:10px 0}.theme-colors,.font-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.color-control{display:grid;grid-template-columns:46px minmax(105px,1fr);gap:7px 10px;align-items:center;padding:11px;border:1px solid #d5cec1;border-radius:6px;background:#fbf8f1}.color-control>span{grid-column:1/-1;font-weight:700}.color-control input[type=color]{width:46px;height:38px;padding:2px}.color-control input[type=text]{width:100%;font-family:"IBM Plex Mono",monospace;text-transform:uppercase}.color-alpha{grid-column:1/-1;display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}.color-alpha input{width:100%}.color-alpha output{min-width:42px;font:600 12px "IBM Plex Mono",monospace}.theme-tabs,.folder-tabs{display:flex;gap:8px;overflow-x:auto;padding:4px 2px 10px;position:sticky;top:0;background:#fff;z-index:3}.theme-tab,.folder-tab{white-space:nowrap;border:1px solid #cbd5e1;background:#fff;color:#273043;border-radius:8px 8px 0 0;padding:9px 12px;cursor:pointer}.theme-tab.active,.folder-tab.active{background:#B58E46;color:#1C242B;border-color:#B58E46}.theme-form[hidden]{display:none}.font-card{border:1px solid #d5cec1;border-radius:8px;padding:14px;background:#fbf8f1}.font-card h3{margin-top:0}.font-card .field input,.font-card .field select{width:100%}.font-color{display:grid;grid-template-columns:46px 1fr;gap:8px}.font-color input[type=color]{width:46px;height:40px;padding:2px}.brand-save{margin-top:22px}.url{word-break:break-all;background:#f8fafc;padding:10px;border-radius:8px}.actions form{display:inline}.top-actions form,.logout-form{margin:0}.correction-help{background:#F4EEE2;border-left:4px solid #B58E46;padding:14px;margin:14px 0}.correction-help code{font-family:"IBM Plex Mono",monospace}.correction-upload{margin-top:14px;padding-top:14px;border-top:1px solid #dbe3ec}.muted{color:#64748b}@media(max-width:760px){.layout{grid-template-columns:1fr}.side{position:static}.main{padding:18px}.theme-tabs{position:static}}
</style>
<link rel="stylesheet" href="assets/toinky-theme.css">
<link rel="stylesheet" href="assets/toinky-admin-shell.css">
</head>
<body>
<div class="layout">
<aside class="side admin-shell-side">
<div class="toinky-brand-group">
<a class="toinky-brand" href="./" aria-label="Toinky home">
<span class="toinky-logo-mark" role="img" aria-label="Toinky">
</span>
</a>
<span class="brand-context">Admin</span>
</div>
<div class="group">Accounts</div>
<a class="<?=$view==='overview'?'active':''?>" href="?view=overview">Overview</a>
<a class="<?=$view==='accounts'?'active':''?>" href="?view=accounts">Manage Users</a>
<a class="<?=$view==='invitations'?'active':''?>" href="?view=invitations">Invitations</a>
<a class="<?=$view==='writer_requests'?'active':''?>" href="?view=writer_requests">Writer Requests</a>
<div class="group">Gameplay</div>
<a class="<?=$view==='simulation'?'active':''?>" href="?view=simulation">Simulation View</a>
<a href="lab.php">Story Engine Lab</a>
<a href="writer.php">Story Writer</a>
<div class="group">Story Publishing</div>
<a class="<?=in_array($view,['story_reviews','story_review'],true)?'active':''?>" href="?view=story_reviews">Story Reviews</a>
<a class="<?=$view==='themes'?'active':''?>" href="?view=themes">Theme Manager</a>
<div class="group">Stories Played</div>
<a class="<?=$view==='human'?'active':''?>" href="?view=human">Human Games</a>
<a class="<?=$view==='simulations'?'active':''?>" href="?view=simulations">Simulation Games</a>
<div class="group">Site Settings</div>
<a class="<?=$view==='brand_fonts'?'active':''?>" href="?view=brand_fonts">Brand</a>
<a class="<?=$view==='security'?'active':''?>" href="?view=security">Security</a>
<div class="group">Account</div>
<a href="./">Public Portal</a>
</aside>
<main class="main">
<div class="top">
<div>
<h1>
<?=e(ucwords(str_replace('_',' ',$view)))?>
</h1>
<span class="muted">Signed in as <?=e($admin['display_name'])?>
</span>
</div>
<div class="top-actions">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="logout">
<button class="btn alt">Log Out</button>
</form>
</div>
</div>
<?php if($error):?>
<div class="error">
<?=e($error)?>
</div>
<?php endif;?>
<?php if($notice):?>
<div class="notice">
<?=e($notice)?>
</div>
<?php endif;?>
<?php if($generatedUrl):?>
<div class="url">
<b>Fallback link:</b> <?=e($generatedUrl)?>
</div>
<?php endif;?>
<?php if($view==='overview'):?>
<div class="cards">
<div class="metric">
<b>
<?=$counts['users']?>
</b>Users</div>
<div class="metric">
<b>
<?=$counts['human']?>
</b>Hosted games</div>
<div class="metric">
<b>
<?=$counts['simulations']?>
</b>Simulations</div>
<div class="metric">
<b>
<?=$counts['ratings']?>
</b>Ratings</div>
</div>
<section class="panel">
<h2>Recent users</h2>
<?php $users=array_slice($users,0,10);require __DIR__.'/_admin_users_table.php';?>
</section>
<?php elseif($view==='accounts'):?>
<section class="panel">
<h2>User accounts</h2>
<?php require __DIR__.'/_admin_users_table.php';?>
</section>
<?php elseif($view==='user'):?>
<section class="panel">
<h2>
<?=e($detailUser['display_name'])?>
</h2>
<p>
<?=e($detailUser['email'])?> · <?=e($detailUser['status'])?>
<?=((int)$detailUser['is_admin']===1)?' · Administrator':''?>
</p>
</section>
<section class="panel">
<h2>Stories hosted</h2>
<table class="table">
<tr>
<th>Story</th>
<th>Genre</th>
<th>Status</th>
<th>Created</th>
<th>Storyboard</th>
</tr>
<?php foreach($hosted as$g):?>
<tr>
<td>
<?=e($g['title'])?>
</td>
<td>
<?=e($g['genre'])?>
</td>
<td>
<?=e($g['status'])?>
</td>
<td>
<?=e($g['created_at_utc'])?>
</td>
<td>
<a href="lab.php?simulation=<?=(int)$g['simulation_id']?>">View</a>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<section class="panel">
<h2>Stories joined</h2>
<table class="table">
<tr>
<th>Story</th>
<th>Genre</th>
<th>Host</th>
<th>Character</th>
<th>Status</th>
<th>Storyboard</th>
</tr>
<?php foreach($joined as$g):?>
<tr>
<td>
<?=e($g['title'])?>
</td>
<td>
<?=e($g['genre'])?>
</td>
<td>
<?=e($g['host_name'])?>
</td>
<td>
<?=e($g['character_name']??'Not selected')?>
</td>
<td>
<?=e($g['status'])?>
</td>
<td>
<a href="lab.php?simulation=<?=(int)$g['simulation_id']?>">View</a>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<section class="panel">
<h2>Story ratings</h2>
<table class="table">
<tr>
<th>Story</th>
<th>Genre</th>
<th>Rating</th>
<th>Review</th>
<th>Date</th>
</tr>
<?php foreach($ratings as$r):?>
<tr>
<td>
<?=e($r['title'])?>
</td>
<td>
<?=e($r['genre'])?>
</td>
<td>
<?=(int)$r['rating']?> / 5</td>
<td>
<?=e($r['review_text']??'')?>
</td>
<td>
<?=e($r['created_at_utc'])?>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<?php elseif($view==='invitations'):?>
<section class="panel">
<h2>Invite a user</h2>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="invite">
<label class="field">Email address<input type="email" name="email" required>
</label>
<button class="btn">Send invitation</button>
</form>
</section>
<section class="panel">
<h2>Invitation history</h2>
<table class="table">
<tr>
<th>Email</th>
<th>Invited by</th>
<th>Created</th>
<th>Expires</th>
<th>Status</th>
</tr>
<?php foreach($invites as$i):?>
<tr>
<td>
<?=e($i['email'])?>
</td>
<td>
<?=e($i['invited_by'])?>
</td>
<td>
<?=e($i['created_at_utc'])?>
</td>
<td>
<?=e($i['expires_at_utc'])?>
</td>
<td>
<?=!empty($i['accepted_at_utc'])?'Accepted':(strtotime($i['expires_at_utc'])<time()?'Expired':'Open')?>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<?php elseif($view==='writer_requests'):?>
<section class="panel">
<h2>Story Writer access requests</h2>
<p class="muted">New accounts can host and participate in games. Story-building access is granted here after review.</p>
<table class="table">
<tr>
<th>Applicant</th>
<th>Request</th>
<th>Status</th>
<th>Review</th>
</tr>
<?php foreach($writerRequests as$r):?>
<tr>
<td>
<b>
<?=e($r['display_name'])?>
</b>
<br>
<?=e($r['email'])?>
<br>
<span class="muted">
<?=e($r['created_at_utc'])?>
</span>
</td>
<td>
<?=nl2br(e($r['request_text']))?>
</td>
<td>
<?=e(ucwords(strtolower($r['status'])))?>
<?php if($r['reviewer_name']):?>
<br>
<span class="muted">by <?=e($r['reviewer_name'])?>
</span>
<?php endif;?>
</td>
<td>
<?php if($r['status']==='PENDING'):?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="review_writer">
<input type="hidden" name="writer_request_id" value="<?=(int)$r['writer_request_id']?>">
<label class="field">Notes<textarea name="reviewer_notes" rows="3">
</textarea>
</label>
<button class="btn" name="decision" value="approve">Approve</button> <button class="btn alt" name="decision" value="decline">Decline</button>
</form>
<?php else:?>
<?=nl2br(e($r['reviewer_notes']??''))?>
<?php endif;?>
</td>
</tr>
<?php endforeach;?>
</table>
<?php if(!$writerRequests):?>
<p>No access requests yet.</p>
<?php endif;?>
</section>
<?php elseif($view==='story_reviews'):?>
<section class="panel">
<h2>Submitted Story Revisions</h2>
<p class="muted">Each submission is a fixed revision. Open a revision to review it in a focused window.</p>
<table class="table">
<tr>
<th>Story / Revision</th>
<th>Submitted</th>
<th>Status</th>
<th>Action</th>
</tr>
<?php foreach($storyReviews as$r):?>
<tr>
<td>
<b>
<?=e($r['title'])?>
</b>
<br>
<?=e($r['code'])?> · revision <?=(int)$r['version_no']?>
</td>
<td>
<?=e($r['submitted_by'])?>
<br>
<span class="muted">
<?=e($r['created_at_utc'])?>
</span>
</td>
<td>
<?=e(ucwords(strtolower(str_replace('_',' ',$r['workflow_status']))))?>
<?php if($r['reviewed_by']):?>
<br>
<span class="muted">by <?=e($r['reviewed_by'])?>
</span>
<?php endif;?>
</td>
<td>
<a class="btn" href="?view=story_review&amp;version=<?=(int)$r['plot_version_id']?>" target="_blank" rel="noopener">Review</a>
</td>
</tr>
<?php endforeach;?>
</table>
<?php if(!$storyReviews):?>
<p>No submitted revisions yet.</p>
<?php endif;?>
</section>

<?php elseif($view==='story_review'):?>
<?php $versionId=(int)($_GET['version']??0);$q=db()->prepare('SELECT v.*,p.title,p.code,u.display_name submitted_by,reviewer.display_name reviewed_by FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id JOIN users u ON u.user_id=v.submitted_by_user_id LEFT JOIN users reviewer ON reviewer.user_id=v.reviewed_by_user_id WHERE v.plot_version_id=?');$q->execute([$versionId]);$reviewVersion=$q->fetch();?>
<?php if(!$reviewVersion):?>
<section class="panel"><h2>Revision Not Found</h2><p>The requested story revision is unavailable.</p></section>
<?php else:?>
<section class="panel"><div class="review-heading"><div><h2><?=e($reviewVersion['title'])?> · Revision <?=(int)$reviewVersion['version_no']?></h2><p class="muted"><?=e($reviewVersion['code'])?> · submitted by <?=e($reviewVersion['submitted_by'])?> on <?=e($reviewVersion['created_at_utc'])?> · <?=e(ucwords(strtolower(str_replace('_',' ',$reviewVersion['workflow_status']))))?></p></div><button type="button" class="btn alt" onclick="window.close()">Close Window</button></div></section>
<section class="panel"><h2>Review and Decision</h2>
<form method="post" style="margin-bottom:18px"><?=csrf_field()?><input type="hidden" name="action" value="audit_story"><input type="hidden" name="plot_id" value="<?=(int)$reviewVersion['plot_id']?>"><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><button class="btn alt">Generate AI Review Package</button></form>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review_story"><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><label class="field">Decision<select name="decision"><option value="CHANGES_REQUESTED">Request changes</option><option value="APPROVED">Approve</option><option value="PUBLISHED">Publish</option><option value="ARCHIVED">Archive</option></select></label><label class="field">Review Notes<textarea name="review_notes" rows="5"><?=e($reviewVersion['review_notes']??'')?></textarea></label><button class="btn">Save Review</button></form>
<form method="post" enctype="multipart/form-data" class="correction-upload"><?=csrf_field()?><input type="hidden" name="action" value="import_story_corrections"><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><label class="field">Import AI Correction Package<input type="file" name="correction_file" accept=".json,.csv,application/json,text/csv" required></label><span class="muted">Latest revision only · JSON or CSV · maximum 2 MB</span><br><button class="btn" style="margin-top:10px">Apply Corrections as New Revision</button></form>
</section>
<?php if($auditText):?>
<section class="panel">
<h2>AI-Ready Story Review Package</h2>
<p>Copy the review package and the instructions below into an AI reviewer. The AI must return a correction package that Toinky can validate and import.</p>
<button type="button" class="btn" id="copy-review-package">Copy All</button>
<div id="ai-review-copy-source">
<div class="correction-help">
<h3>Instructions for the AI Reviewer</h3>
<ol>
<li>Review the story package and identify specific corrections.</li>
<li>Return one complete <b>Toinky Story Corrections v1</b> file as JSON or CSV. Do not return SQL, PHP, migrations, or narrative instructions in place of the file.</li>
<li>Preserve stable story keys. Use only existing entities and allowed fields.</li>
<li>For JSON, use <code>{"format":"toinky-story-corrections-v1","plot_code":"<?=e($auditContext['code']??'STORY_CODE')?>","base_version":<?=(int)($auditContext['version_no']??0)?>,"changes":[{"entity":"character","key":"ROLE_CODE","field":"goal_text","value":"Corrected text"}]}</code>.</li>
<li>For CSV, use columns <code>entity,key,field,value</code>. Preserve multiline values using standard CSV quoting.</li>
<li>Supported keys: <code>plot</code> (story code), <code>character</code> (role code), <code>event</code> (event code), <code>decision</code> (<code>EVENT_CODE|ROLE_CODE</code>), <code>option</code> (<code>EVENT_CODE|ROLE_CODE|OPTION_CODE</code>), <code>opportunity</code> (opportunity code), <code>dynamic_option</code> (<code>OPPORTUNITY_CODE|OPTION_CODE</code>), and <code>resolution</code> (<code>SECTION_CODE|PRIORITY</code>).</li>
<li>Allowed fields: plot — <code>title, archetype, setting_name, scenario_text, max_players, max_npcs</code>; character — <code>name, public_description, private_knowledge, starting_location, goal_text, fear_text, pronoun_set, simulation_active, slot_no</code>; event — <code>headline, event_text, event_second, phase, active, sort_order</code>; decision — <code>prompt_text, decision_window_seconds, actor_type</code>; option and dynamic option — <code>label, result_text, sort_order</code>; opportunity — <code>prompt_text, window_seconds, once_only, priority, active</code>; resolution — <code>fragment_text, section_order, priority, active</code>.</li>
<li>Do not add, delete, or rename structural keys. Corrections are applied transactionally and become a new numbered revision.</li>
</ol>
</div>
<pre class="audit">
<?=e($auditText)?>
</pre>
</div>
</section>
<?php endif;?>
<?php endif;?>
<?php elseif($view==='themes'):?>
<section class="panel">
<h2>Genre Theme Manager</h2>
<p class="muted">Choose a theme tab to edit its palette, complete typography, and media. Existing themes remain attached to their stories.</p>
<div class="theme-tabs" role="tablist">
<?php foreach($themes as$i=>$t):?>
<button type="button" class="theme-tab <?=($activeTheme?(int)$t['genre_id']===$activeTheme:$i===0)?'active':''?>" data-theme-tab="theme-<?=(int)$t['genre_id']?>">
<?=e($t['label_text'])?>
</button>
<?php endforeach;?>
<button type="button" class="theme-tab" data-theme-tab="theme-new">+ New Theme</button>
</div>
<?php foreach($themes as$i=>$t):?>
<form method="post" id="theme-<?=(int)$t['genre_id']?>" class="panel theme-form" style="box-shadow:none" <?=($activeTheme?(int)$t['genre_id']===$activeTheme:$i===0)?'':'hidden'?>>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_theme">
<input type="hidden" name="genre_id" value="<?=(int)$t['genre_id']?>">
<h3>
<?=e($t['label_text'])?>
</h3>
<div class="theme-preview" style="background:<?=e($t['background_color'])?>;color:<?=e($t['text_color'])?>;border:4px solid <?=e($t['accent_color'])?>;font-family:<?=e($t['body_font'])?>">
<b style="font-family:<?=e($t['title_font'])?>;color:<?=e($t['title_color'])?>">A preview of this genre</b>
<br>Story prose appears on this surface.</div>
<h3>Palette</h3>
<div class="theme-colors">
<?php foreach(['primary_color'=>'Primary','secondary_color'=>'Secondary','accent_color'=>'Accent','surface_color'=>'Surface','background_color'=>'Background']as$key=>$label):?>
<label class="color-control" data-color-control>
<span>
<?=$label?>
</span>
<input type="color" data-color-picker value="<?=e(theme_rgb($t[$key]))?>" aria-label="<?=$label?> color picker">
<input type="text" name="<?=$key?>" data-color-hex value="<?=e(strtoupper($t[$key]))?>" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required aria-label="<?=$label?> hexadecimal color">
<span class="color-alpha">
<input type="range" data-color-alpha min="0" max="100" value="<?=theme_opacity($t[$key])?>" aria-label="<?=$label?> opacity">
<output>
<?=theme_opacity($t[$key])?>%</output>
</span>
</label>
<?php endforeach;?>
</div>
<h3>Typography</h3>
<div class="font-grid">
<?php foreach($themeFontRoles as$role=>$fields):?>
<div class="font-card">
<h3>
<?=e($fields['label'])?>
</h3>
<label class="field">Font Family<select name="<?=e($fields['font'])?>" required>
<?php foreach($themeFonts as$fontLabel=>$fontStack):?>
<option value="<?=e($fontStack)?>" <?=$t[$fields['font']]===$fontStack?'selected':''?>>
<?=e($fontLabel)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">Font Color<span class="font-color" data-color-control>
<input type="color" data-color-picker value="<?=e(theme_rgb($t[$fields['color']]))?>">
<input type="text" name="<?=e($fields['color'])?>" data-color-hex value="<?=e(strtoupper($t[$fields['color']]))?>" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required>
<span class="color-alpha">
<input type="range" data-color-alpha min="0" max="100" value="<?=theme_opacity($t[$fields['color']])?>">
<output>
<?=theme_opacity($t[$fields['color']])?>%</output>
</span>
</span>
</label>
<label class="field">Font Size<input name="<?=e($fields['size'])?>" value="<?=e($t[$fields['size']])?>" pattern="^(?:0\.[1-9][0-9]?|[1-9][0-9]{0,2})(?:px|rem|em|%)$" required>
</label>
<label class="field">Font Weight<select name="<?=e($fields['weight'])?>">
<?php foreach($fontWeights as$value=>$label):?>
<option value="<?=$value?>" <?=$t[$fields['weight']]===(string)$value?'selected':''?>>
<?=e($label.' · '.$value)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">Text Decoration<select name="<?=e($fields['decoration'])?>">
<?php foreach($fontDecorations as$value=>$label):?>
<option value="<?=$value?>" <?=$t[$fields['decoration']]===(string)$value?'selected':''?>>
<?=e($label)?>
</option>
<?php endforeach;?>
</select>
</label>
</div>
<?php endforeach;?>
</div>
<h3>Media</h3>
<?php foreach(['BACKGROUND'=>'Background','DIVIDER'=>'Story Divider','CARD'=>'Card Texture']as$type=>$label):$field=strtolower($type).'_media_id';?>
<label class="field">
<?=$label?> Image<select name="<?=$field?>">
<option value="">No image</option>
<?php foreach($themeMedia as$m):if($m['media_type']!==$type)continue;?>
<option value="<?=(int)$m['media_id']?>" <?=((int)($t[$field]??0)===(int)$m['media_id'])?'selected':''?>>
<?=e($m['title_text'].' · '.($m['uploader_name']??'System'))?>
</option>
<?php endforeach;?>
</select>
</label>
<?php endforeach;?>
<button class="btn">Save <?=e($t['label_text'])?> Theme</button>
</form>
<?php endforeach;?>
<form method="post" id="theme-new" class="panel theme-form" style="box-shadow:none" hidden>
<?=csrf_field()?>
<input type="hidden" name="action" value="create_theme">
<h3>Add New Theme</h3>
<label class="field">Theme Name<input name="label_text" maxlength="120" required placeholder="Example: Gothic Romance">
</label>
<label class="field">Description<textarea name="description_text" rows="3" maxlength="500" placeholder="Describe the stories that use this theme.">
</textarea>
</label>
<p class="muted">The theme will start with the standard Toinky palette and typography. Save it, then open its new tab to customize every setting.</p>
<button class="btn">Add Theme</button>
</form>
</section>
<?php elseif($view==='brand_fonts'):?>
<section class="panel">
<h2>Brand</h2>
<p class="muted">Site-wide brand controls are organized into folders so additional brand settings can be added later.</p>
<div class="folder-tabs">
<button type="button" class="folder-tab active">Fonts</button>
</div>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_brand_fonts">
<div class="font-grid">
<?php foreach($brandFonts as$key=>$role):?>
<div class="font-card">
<h3>
<?=e($role['label'])?>
</h3>
<p class="muted">
<code>
<?=e($role['selector'])?>
</code>
</p>
<label class="field">Font Family<select name="brand_<?=e($key)?>_family" required>
<?php foreach($themeFonts as$fontLabel=>$fontStack):?>
<option value="<?=e($fontStack)?>" <?=$role['family']===$fontStack?'selected':''?>>
<?=e($fontLabel)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">Font Color<span class="font-color" data-color-control>
<input type="color" data-color-picker value="<?=e(theme_rgb($role['color']))?>">
<input type="text" name="brand_<?=e($key)?>_color" data-color-hex value="<?=e(strtoupper($role['color']))?>" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required>
<span class="color-alpha">
<input type="range" data-color-alpha min="0" max="100" value="<?=theme_opacity($role['color'])?>">
<output>
<?=theme_opacity($role['color'])?>%</output>
</span>
</span>
</label>
<label class="field">Font Size<input name="brand_<?=e($key)?>_size" value="<?=e($role['size'])?>" pattern="^(?:0\.[1-9][0-9]?|[1-9][0-9]{0,2})(?:px|rem|em|%)$" required>
</label>
<label class="field">Font Weight<select name="brand_<?=e($key)?>_weight">
<?php foreach($fontWeights as$value=>$label):?>
<option value="<?=$value?>" <?=$role['weight']===(string)$value?'selected':''?>>
<?=e($label.' · '.$value)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">Text Decoration<select name="brand_<?=e($key)?>_decoration">
<?php foreach($fontDecorations as$value=>$label):?>
<option value="<?=$value?>" <?=$role['decoration']===(string)$value?'selected':''?>>
<?=e($label)?>
</option>
<?php endforeach;?>
</select>
</label>
</div>
<?php endforeach;?>
</div>
<button class="btn brand-save">Save Brand Fonts</button>
</form>
</section>
<?php elseif($view==='security'):?>
<section class="panel">
<h2>Login CAPTCHA</h2>
<p class="muted">Built-in CAPTCHA is enabled by default and needs no external account. Google reCAPTCHA is optional and requires both keys.</p>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_security">
<label class="field">CAPTCHA Mode<select name="captcha_mode">
<option value="BUILTIN" <?=$security['SECURITY_CAPTCHA_MODE']==='BUILTIN'?'selected':''?>>Built-in Challenge</option>
<option value="VISUAL" <?=$security['SECURITY_CAPTCHA_MODE']==='VISUAL'?'selected':''?>>Random Image &amp; Text</option>
<option value="GOOGLE" <?=$security['SECURITY_CAPTCHA_MODE']==='GOOGLE'?'selected':''?>>Google reCAPTCHA</option>
<option value="OFF" <?=$security['SECURITY_CAPTCHA_MODE']==='OFF'?'selected':''?>>Off</option>
</select>
</label>
<label class="field">Google Site Key<input name="recaptcha_site_key" value="<?=e($security['GOOGLE_RECAPTCHA_SITE_KEY'])?>">
</label>
<label class="field">Google Secret Key<input type="password" name="recaptcha_secret_key" placeholder="Leave blank to keep the saved secret">
</label>
<button class="btn">Save Security Settings</button>
</form>
</section>
<?php elseif($view==='human'):?>
<section class="panel">
<h2>Human-hosted games</h2>
<table class="table">
<tr>
<th>Story</th>
<th>Host</th>
<th>Players</th>
<th>Status</th>
<th>Rating</th>
<th>Created</th>
<th>Storyboard</th>
</tr>
<?php foreach($human as$g):?>
<tr>
<td>
<b>
<?=e($g['title'])?>
</b>
<br>
<?=e($g['genre'])?>
</td>
<td>
<?=e($g['host_name'])?>
</td>
<td>
<?=(int)$g['player_count']?>
</td>
<td>
<?=e($g['status'])?>
</td>
<td>
<?=e($g['average_rating']??'—')?> (<?=(int)$g['rating_count']?>)</td>
<td>
<?=e($g['created_at_utc'])?>
</td>
<td>
<a href="lab.php?simulation=<?=(int)$g['simulation_id']?>">View</a>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<?php else:?>
<section class="panel">
<h2>
<?=$view==='simulation'?'All simulations':'Standalone simulation games'?>
</h2>
<table class="table">
<tr>
<th>ID</th>
<th>Story</th>
<th>Host</th>
<th>Players / AI</th>
<th>Status</th>
<th>Rating</th>
<th>Created</th>
<th>Storyboard</th>
</tr>
<?php foreach($sims as$s):?>
<tr>
<td>
<?=(int)$s['simulation_id']?>
</td>
<td>
<b>
<?=e($s['title'])?>
</b>
<br>
<?=e($s['genre'])?>
</td>
<td>
<?=e($s['host_name'])?>
</td>
<td>
<?=(int)$s['human_count']?> human · <?=(int)$s['ai_count']?> AI</td>
<td>
<?=e($s['status'])?>
<br>
<span class="muted">
<?=e($s['current_phase'])?> · <?=gmdate('i:s',(int)$s['elapsed_seconds'])?>
</span>
</td>
<td>
<?=e($s['average_rating']??'—')?>
<?=((int)$s['rating_count']>0)?' ('.(int)$s['rating_count'].')':''?>
</td>
<td>
<?=e($s['created_at_utc'])?>
</td>
<td>
<a href="lab.php?simulation=<?=(int)$s['simulation_id']?>">View</a>
</td>
</tr>
<?php endforeach;?>
</table>
</section>
<?php endif;?>
</main>
</div>
<script>
document.querySelectorAll('[data-color-control]').forEach(control=>{
 const picker=control.querySelector('[data-color-picker]');
 const hex=control.querySelector('[data-color-hex]');
 const alpha=control.querySelector('[data-color-alpha]');
 const output=control.querySelector('output');
 const syncFromControls=()=>{
  const pct=Number(alpha.value);
  const alphaHex=Math.round(255*pct/100).toString(16).padStart(2,'0').toUpperCase();
  hex.value=picker.value.toUpperCase()+(pct<100?alphaHex:'');
  output.value=pct+'%';
 };
 const syncFromHex=()=>{
  const value=hex.value.trim().toUpperCase();
  if(!/^#[0-9A-F]{6}([0-9A-F]{2})?$/.test(value))return;
  picker.value=value.slice(0,7);
  alpha.value=value.length===9?Math.round(parseInt(value.slice(7,9),16)*100/255):100;
  output.value=alpha.value+'%';
 };
 picker.addEventListener('input',syncFromControls);
 alpha.addEventListener('input',syncFromControls);
 hex.addEventListener('input',syncFromHex);
});
document.querySelectorAll('[data-theme-tab]').forEach(tab=>tab.addEventListener('click',()=>{
 document.querySelectorAll('[data-theme-tab]').forEach(item=>item.classList.toggle('active',item===tab));
 document.querySelectorAll('.theme-form').forEach(form=>form.hidden=form.id!==tab.dataset.themeTab);
}));
const copyReviewButton=document.querySelector('#copy-review-package');
if(copyReviewButton)copyReviewButton.addEventListener('click',async()=>{
 const source=document.querySelector('#ai-review-copy-source');
 if(!source)return;
 const text=source.innerText.trim();
 try{await navigator.clipboard.writeText(text);}catch(error){
  const range=document.createRange();range.selectNodeContents(source);
  const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);
  document.execCommand('copy');selection.removeAllRanges();
 }
 const original=copyReviewButton.textContent;copyReviewButton.textContent='Copied';
 setTimeout(()=>copyReviewButton.textContent=original,1800);
});
</script>
</body>
</html>
