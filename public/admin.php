<?php
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';require_once $root.'/app/MigrationService.php';MigrationService::run();require_once $root.'/app/AuthService.php';require_once $root.'/app/MediaLibraryService.php';require_once $root.'/app/StoryAuthoringService.php';require_once $root.'/app/StoryCorrectionService.php';require_once $root.'/app/StorySimulationBatchService.php';require_once $root.'/app/ParallaxService.php';require_once $root.'/app/PageContentService.php';AuthService::boot();$admin=AuthService::requireAdmin();$themeFonts=[
'Brand — Cormorant Garamond'=>'Cormorant Garamond, Georgia, serif',
'Brand — Instrument Sans'=>'Instrument Sans, Arial, sans-serif',
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
$textTransforms=['none'=>'As Written','uppercase'=>'Uppercase','capitalize'=>'Capitalize Each Word','lowercase'=>'Lowercase'];
$themeFontRoles=[
 'title'=>['label'=>'Titles','font'=>'title_font','color'=>'title_color','size'=>'title_size','weight'=>'title_weight','decoration'=>'title_decoration','transform'=>'title_transform'],
 'heading'=>['label'=>'Headings','font'=>'heading_font','color'=>'heading_color','size'=>'heading_size','weight'=>'heading_weight','decoration'=>'heading_decoration','transform'=>'heading_transform'],
 'body'=>['label'=>'Main Content','font'=>'body_font','color'=>'text_color','size'=>'body_size','weight'=>'body_weight','decoration'=>'body_decoration','transform'=>'body_transform'],
 'meta'=>['label'=>'Supporting Text','font'=>'meta_font','color'=>'meta_color','size'=>'meta_size','weight'=>'meta_weight','decoration'=>'meta_decoration','transform'=>'meta_transform'],
 'button'=>['label'=>'Buttons & Options','font'=>'button_font','color'=>'button_color','size'=>'button_size','weight'=>'button_weight','decoration'=>'button_decoration','transform'=>'button_transform']
];
$brandFontRoles=[
 'public_hero_title'=>['group'=>'Public Website','label'=>'Page Hero Title','description'=>'The largest title at the top of a public page.','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#F4EEE2','dark_color'=>'#F4EEE2','size'=>'clamp(3.2rem,7vw,6rem)','weight'=>'600','line_height'=>'0.95','letter_spacing'=>'-0.02em','transform'=>'none','decoration'=>'none'],
 'public_section_title'=>['group'=>'Public Website','label'=>'Section Title','description'=>'Main headings that introduce public content sections.','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'clamp(2.2rem,5vw,4.5rem)','weight'=>'600','line_height'=>'1','letter_spacing'=>'-0.015em','transform'=>'none','decoration'=>'none'],
 'public_card_title'=>['group'=>'Public Website','label'=>'Card & Story Title','description'=>'Headings inside story-world, audience, and feature cards.','usage'=>'Story cards, homepage story worlds, three-column cards, and Player / Host / Writer cards.','preview'=>'The Empty Throne','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'1.65rem','weight'=>'600','line_height'=>'1.08','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'public_section_label'=>['group'=>'Public Website','label'=>'Section Label','description'=>'Small labels above public section titles.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#6B2938','dark_color'=>'#D5B56B','size'=>'0.75rem','weight'=>'700','line_height'=>'1.2','letter_spacing'=>'0.16em','transform'=>'uppercase','decoration'=>'none'],
 'public_intro'=>['group'=>'Public Website','label'=>'Introduction Text','description'=>'Prominent lead copy beneath public hero and section titles.','usage'=>'Homepage introduction, public page hero introductions, and story-detail summaries.','preview'=>'Gather your people and step into a shared world.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#35424B','dark_color'=>'#F4EEE2','size'=>'1.2rem','weight'=>'400','line_height'=>'1.65','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'public_body'=>['group'=>'Public Website','label'=>'Body Text','description'=>'Paragraphs and long-form public page copy.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#35424B','dark_color'=>'#F4EEE2','size'=>'1rem','weight'=>'400','line_height'=>'1.65','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'public_meta'=>['group'=>'Public Website','label'=>'Metadata & Details','description'=>'Compact supporting facts that identify or describe public content.','usage'=>'Story genres, play counts, story settings, back links, and small supporting details.','preview'=>'Mystery · 24 plays','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#66715C','dark_color'=>'#D9D1C2','size'=>'0.78rem','weight'=>'600','line_height'=>'1.35','letter_spacing'=>'0.06em','transform'=>'none','decoration'=>'none'],
 'public_navigation'=>['group'=>'Public Website','label'=>'Top Navigation','description'=>'Public website navigation—not workspace drawer links.','usage'=>'Desktop and mobile public header links, plus public footer navigation.','preview'=>'How It Works   Stories   For Writers','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#F4EEE2','dark_color'=>'#F4EEE2','size'=>'0.9rem','weight'=>'600','line_height'=>'1.2','letter_spacing'=>'0.02em','transform'=>'none','decoration'=>'none'],
 'public_actions'=>['group'=>'Public Website','label'=>'Buttons & Links','description'=>'Prominent public calls to action.','usage'=>'Public buttons, story-card links, social buttons, and calls to action such as Explore This Story.','preview'=>'Explore This Story →','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'0.86rem','weight'=>'700','line_height'=>'1.2','letter_spacing'=>'0.02em','transform'=>'none','decoration'=>'none'],
 'internal_page_title'=>['group'=>'Application & Admin','label'=>'Page Title','description'=>'Primary titles on workspace and account screens.','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'2rem','weight'=>'600','line_height'=>'1.05','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'internal_section_title'=>['group'=>'Application & Admin','label'=>'Section & Card Title','description'=>'Panel and card headings inside the application.','family'=>'Cormorant Garamond, Georgia, serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'1.35rem','weight'=>'600','line_height'=>'1.15','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'internal_body'=>['group'=>'Application & Admin','label'=>'Body & Form Text','description'=>'Application copy, form fields, and table content.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'0.9rem','weight'=>'400','line_height'=>'1.5','letter_spacing'=>'0','transform'=>'none','decoration'=>'none'],
 'internal_labels'=>['group'=>'Application & Admin','label'=>'Labels & Helper Text','description'=>'Form labels, badges, metadata, and small guidance.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#52606A','dark_color'=>'#D9D1C2','size'=>'0.75rem','weight'=>'600','line_height'=>'1.35','letter_spacing'=>'0.03em','transform'=>'none','decoration'=>'none'],
 'internal_navigation'=>['group'=>'Application & Admin','label'=>'Workspace Navigation','description'=>'Drawer navigation and interior page tabs.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#F4EEE2','dark_color'=>'#F4EEE2','size'=>'0.82rem','weight'=>'600','line_height'=>'1.25','letter_spacing'=>'0.01em','transform'=>'none','decoration'=>'none'],
 'internal_actions'=>['group'=>'Application & Admin','label'=>'Buttons & Actions','description'=>'Save, publish, secondary, and destructive controls.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#1C242B','dark_color'=>'#F4EEE2','size'=>'0.8rem','weight'=>'700','line_height'=>'1.2','letter_spacing'=>'0.01em','transform'=>'none','decoration'=>'none'],
 'internal_table_heading'=>['group'=>'Application & Admin','label'=>'Table Headings','description'=>'Column headings in administrative and game tables.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#52606A','dark_color'=>'#F4EEE2','size'=>'0.72rem','weight'=>'700','line_height'=>'1.3','letter_spacing'=>'0.06em','transform'=>'uppercase','decoration'=>'none'],
 'brand_context'=>['group'=>'Application & Admin','label'=>'Workspace Context','description'=>'The small account context beneath the workspace logo.','family'=>'Instrument Sans, Arial, sans-serif','color'=>'#F4EEE2','dark_color'=>'#F4EEE2','size'=>'0.72rem','weight'=>'600','line_height'=>'1.3','letter_spacing'=>'0.02em','transform'=>'none','decoration'=>'none']
];
function admin_typography_size($value):string{$size=trim((string)$value);if(preg_match('/^(?:0|[1-9][0-9]{0,3})(?:\.[0-9]+)?$/',$size))$size.='px';if(mb_strlen($size)>120||!preg_match('/^(?:(?:0|[1-9][0-9]{0,3})(?:\.[0-9]+)?(?:px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt)|(?:clamp|min|max|calc)\([0-9a-z.%+\-*\/,\s()]+\))$/i',$size))throw new RuntimeException('Font sizes must use a supported CSS size or responsive size function.');return$size;}
$view=(string)($_GET['view']??'overview');if($view==='simulation')$view='simulations';$viewTitles=['overview'=>'Dashboard','accounts'=>'Users','user'=>'User Details','invitations'=>'Invitations','writer_requests'=>'Writer Requests','pages'=>'Pages','brand_fonts'=>'Brand','security'=>'Security','story_reviews'=>'Story Reviews','story_review'=>'Story Review','themes'=>'Theme Manager','human'=>'Live Games','simulations'=>'Simulations'];$simulationType=in_array((string)($_GET['simulation_type']??'all'),['all','standalone','hosted'],true)?(string)($_GET['simulation_type']??'all'):'all';$brandTab=in_array((string)($_GET['brand_tab']??$_POST['brand_tab']??'fonts'),['fonts','parallax','socials'],true)?(string)($_GET['brand_tab']??$_POST['brand_tab']??'fonts'):'fonts';$pageCode=strtoupper((string)($_GET['page']??$_POST['page_code']??'HOME'));$activeTheme=(int)($_GET['theme']??$_POST['genre_id']??0);$error=null;$notice=null;$generatedUrl=null;$auditText=null;$auditContext=null;$correctionPreview=null;
if($_SERVER['REQUEST_METHOD']==='POST'){try{AuthService::requireCsrf();$action=(string)($_POST['action']??'');if($action==='logout'){AuthService::logout();header('Location: ./?view=login');exit;}$target=(int)($_POST['user_id']??0);
 if($action==='invite'){$r=AuthService::createInvitation((string)$_POST['email'],(int)$admin['user_id']);$notice=$r['sent']?'Invitation email sent.':'Mail was unavailable; copy the invitation link below.';$generatedUrl=$r['url'];$view='invitations';}
 if($action==='reset_user'){$q=db()->prepare('SELECT email FROM users WHERE user_id=?');$q->execute([$target]);$email=$q->fetchColumn();if(!$email)throw new RuntimeException('User not found.');$r=AuthService::createPasswordResetForEmail((string)$email);$notice=$r&&$r['sent']?'Password-reset email sent.':'Mail was unavailable; copy the reset link below.';$generatedUrl=$r['url']??null;$view='accounts';}
 if($action==='toggle_status'){if($target===(int)$admin['user_id'])throw new RuntimeException('You cannot disable your own account.');db()->prepare('UPDATE users SET status=IF(status="ACTIVE","DISABLED","ACTIVE") WHERE user_id=?')->execute([$target]);$notice='Account status updated.';$view='accounts';}
 if($action==='toggle_admin'){if($target===(int)$admin['user_id'])throw new RuntimeException('You cannot remove your own administrator access.');db()->prepare('UPDATE users SET is_admin=IF(is_admin=1,0,1) WHERE user_id=?')->execute([$target]);$notice='Administrator access updated.';$view='accounts';}
 if($action==='toggle_writer'){$enabled=!AuthService::hasRole($target,'WRITER');AuthService::setRole($target,'WRITER',$enabled,(int)$admin['user_id']);$notice='Story Writer access updated.';$view='accounts';}
 if($action==='review_writer'){AuthService::reviewWriterRequest((int)$_POST['writer_request_id'],(string)$_POST['decision']==='approve',(int)$admin['user_id'],(string)($_POST['reviewer_notes']??''));$notice='Story Writer request reviewed.';$view='writer_requests';}
 if($action==='review_story'){$versionId=(int)$_POST['plot_version_id'];$status=in_array((string)$_POST['decision'],['APPROVED','CHANGES_REQUESTED','PUBLISHED','ARCHIVED'],true)?(string)$_POST['decision']:'CHANGES_REQUESTED';$q=db()->prepare('SELECT plot_id FROM plot_versions WHERE plot_version_id=?');$q->execute([$versionId]);$reviewPlot=(int)$q->fetchColumn();if(!$reviewPlot)throw new RuntimeException('Submitted revision not found.');db()->prepare('UPDATE plot_versions SET workflow_status=?,reviewed_by_user_id=?,review_notes=?,reviewed_at_utc=UTC_TIMESTAMP() WHERE plot_version_id=?')->execute([$status,$admin['user_id'],trim((string)($_POST['review_notes']??''))?:null,$versionId]);db()->prepare('UPDATE plots SET workflow_status=?,status=IF(?="PUBLISHED","active",status) WHERE plot_id=?')->execute([$status,$status,$reviewPlot]);$notice='Story review status updated.';$view='story_review';$_GET['version']=$versionId;}
 if($action==='audit_story'){$auditVersionId=(int)($_POST['plot_version_id']??0);$audit=StoryAuthoringService::saveAudit((int)$_POST['plot_id'],(int)$admin['user_id'],$auditVersionId?:null);$auditText=$audit['text'];$q=db()->prepare('SELECT p.code,p.title,v.version_no FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id WHERE v.plot_version_id=?');$q->execute([$auditVersionId]);$auditContext=$q->fetch();$notice='Story review package generated below.';$view='story_review';$_GET['version']=$auditVersionId;}
 if($action==='preview_story_corrections'){$versionId=(int)$_POST['plot_version_id'];$correctionPreview=StoryCorrectionService::inspect($admin,$versionId,$_FILES['correction_file']??[]);$notice='Validation passed. Review every proposed change below, then reselect the same file to apply it.';$view='story_review';$_GET['version']=$versionId;}
 if($action==='import_story_corrections'){$versionId=(int)$_POST['plot_version_id'];$result=StoryCorrectionService::import($admin,$versionId,$_FILES['correction_file']??[],!empty($_POST['confirm_structural']));$notice='Applied '.$result['change_count'].' correction(s) and created revision '.$result['version_no'].'.';$view='story_review';$_GET['version']=$versionId;}
 if($action==='start_simulation_review_batch'){$batchId=StorySimulationBatchService::create($admin,$_POST);$notice='Simulation review batch '.$batchId.' queued. The background worker continues after this page closes.';$view='story_reviews';$_GET['batch']=$batchId;}
 if($action==='process_simulation_review_batch'){StorySimulationBatchService::processNext();$notice='Processed the next queued simulation test.';$view='story_reviews';}
 if($action==='save_theme'){
  $genreId=(int)$_POST['genre_id'];
  foreach(['primary_color','secondary_color','accent_color','background_color','surface_color','text_color','heading_color','title_color','meta_color','button_color']as$color){$_POST[$color]=strtoupper(trim((string)($_POST[$color]??'')));if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$_POST[$color]))throw new RuntimeException('Theme colors must use #RRGGBB or #RRGGBBAA hexadecimal values.');}
  $typeValues=[];foreach($themeFontRoles as$role=>$fields){$family=trim((string)($_POST[$fields['font']]??''));$size=admin_typography_size($_POST[$fields['size']]??'');$weight=(string)($_POST[$fields['weight']]??'400');$decoration=(string)($_POST[$fields['decoration']]??'none');$transform=(string)($_POST[$fields['transform']]??'none');if(!in_array($family,$themeFonts,true))throw new RuntimeException('Choose fonts from the supported font list.');if(!isset($fontWeights[$weight])||!isset($fontDecorations[$decoration])||!isset($textTransforms[$transform]))throw new RuntimeException('Choose supported typography options.');$typeValues[$role]=[$family,$size,$weight,$decoration,$transform];}
  $ids=[];$paths=[];foreach(['BACKGROUND','DIVIDER','CARD']as$type){$field=strtolower($type).'_media_id';$id=(int)($_POST[$field]??0);$ids[$type]=$id?:null;$paths[$type]=$id?MediaLibraryService::requireVisible($admin,$id,$type)['file_path']:null;}
  db()->prepare('UPDATE genre_themes SET primary_color=?,secondary_color=?,accent_color=?,background_color=?,surface_color=?,text_color=?,heading_color=?,title_color=?,meta_color=?,button_color=?,title_font=?,title_size=?,title_weight=?,title_decoration=?,title_transform=?,heading_font=?,heading_size=?,heading_weight=?,heading_decoration=?,heading_transform=?,body_font=?,body_size=?,body_weight=?,body_decoration=?,body_transform=?,meta_font=?,meta_size=?,meta_weight=?,meta_decoration=?,meta_transform=?,button_font=?,button_size=?,button_weight=?,button_decoration=?,button_transform=?,background_media_id=?,divider_media_id=?,card_media_id=?,background_image_path=?,divider_image_path=?,card_image_path=?,updated_by_user_id=? WHERE genre_id=?')->execute([$_POST['primary_color'],$_POST['secondary_color'],$_POST['accent_color'],$_POST['background_color'],$_POST['surface_color'],$_POST['text_color'],$_POST['heading_color'],$_POST['title_color'],$_POST['meta_color'],$_POST['button_color'],...$typeValues['title'],...$typeValues['heading'],...$typeValues['body'],...$typeValues['meta'],...$typeValues['button'],$ids['BACKGROUND'],$ids['DIVIDER'],$ids['CARD'],$paths['BACKGROUND'],$paths['DIVIDER'],$paths['CARD'],$admin['user_id'],$genreId]);
  $notice='Genre theme saved.';$view='themes';
 }
 if($action==='create_theme'){$label=trim((string)($_POST['label_text']??''));$code=strtoupper(preg_replace('/[^A-Za-z0-9]+/','_',$label));$code=trim($code,'_');$description=trim((string)($_POST['description_text']??''));if($label===''||$code==='')throw new RuntimeException('Enter a theme name.');if(mb_strlen($label)>120)throw new RuntimeException('Theme names may not exceed 120 characters.');db()->beginTransaction();try{$sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM story_genres')->fetchColumn();$q=db()->prepare('INSERT INTO story_genres(genre_code,label_text,description_text,sort_order,active) VALUES(?,?,?,?,1)');$q->execute([$code,$label,$description,$sort]);$genreId=(int)db()->lastInsertId();db()->prepare('INSERT INTO genre_themes(genre_id,updated_by_user_id) VALUES(?,?)')->execute([$genreId,$admin['user_id']]);db()->commit();}catch(Throwable$e){if(db()->inTransaction())db()->rollBack();throw $e;}$activeTheme=$genreId;$notice='New theme created.';$view='themes';}
 if($action==='save_brand_fonts'){$styles=[];foreach($brandFontRoles as$key=>$defaults){$family=trim((string)($_POST['brand_'.$key.'_family']??''));$color=strtoupper(trim((string)($_POST['brand_'.$key.'_color']??'')));$darkColor=strtoupper(trim((string)($_POST['brand_'.$key.'_dark_color']??'')));$size=admin_typography_size($_POST['brand_'.$key.'_size']??'');$weight=(string)($_POST['brand_'.$key.'_weight']??'400');$lineHeight=trim((string)($_POST['brand_'.$key.'_line_height']??''));$letterSpacing=trim((string)($_POST['brand_'.$key.'_letter_spacing']??''));$transform=(string)($_POST['brand_'.$key.'_transform']??'none');$decoration=(string)($_POST['brand_'.$key.'_decoration']??'none');if(!in_array($family,$themeFonts,true))throw new RuntimeException('Choose fonts from the supported font list.');if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$color)||!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$darkColor))throw new RuntimeException('Typography colors must use #RRGGBB or #RRGGBBAA.');if(!preg_match('/^(?:0(?:\.\d+)?|[1-9](?:\.\d+)?)$/',$lineHeight)||!preg_match('/^(?:0|-?(?:0(?:\.\d+)?|[1-9][0-9]?(?:\.\d+)?)(?:px|rem|em))$/',$letterSpacing))throw new RuntimeException('Enter a valid line height and letter spacing.');if(!isset($fontWeights[$weight])||!isset($fontDecorations[$decoration])||!isset($textTransforms[$transform]))throw new RuntimeException('Choose supported typography options.');$styles[$key]=['family'=>$family,'color'=>$color,'dark_color'=>$darkColor,'size'=>$size,'weight'=>$weight,'line_height'=>$lineHeight,'letter_spacing'=>$letterSpacing,'transform'=>$transform,'decoration'=>$decoration];}$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES("BRAND_FONT_STYLES",?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');$q->execute([json_encode($styles,JSON_UNESCAPED_SLASHES),$admin['user_id']]);$notice='Brand typography saved.';$view='brand_fonts';}
 if($action==='add_parallax_layer'){ParallaxService::add($admin,$_POST,$_FILES['layer_image']??[]);$notice='Parallax layer added.';$view='brand_fonts';$brandTab='parallax';}
 if($action==='save_parallax_layer'){ParallaxService::update($admin,(int)($_POST['parallax_layer_id']??0),$_POST,$_FILES['layer_image']??null);$notice='Parallax layer updated.';$view='brand_fonts';$brandTab='parallax';}
 if($action==='remove_parallax_layer'){ParallaxService::remove((int)($_POST['parallax_layer_id']??0));$notice='Parallax layer removed.';$view='brand_fonts';$brandTab='parallax';}
 if($action==='reorder_parallax_layers'){ParallaxService::reorder((string)($_POST['layer_order']??''));$notice='Parallax layer order saved.';$view='brand_fonts';$brandTab='parallax';}
 if($action==='save_socials'){PageContentService::saveSocials($admin,$_POST);$notice='Social links saved.';$view='brand_fonts';$brandTab='socials';}
 if($action==='save_page_section'){PageContentService::saveSection($admin,(int)($_POST['site_page_section_id']??0),$_POST);$notice='Page section saved.';$view='pages';}
 if($action==='add_page_section'){PageContentService::addSection($admin,(string)($_POST['page_code']??''),$_POST);$notice='Page section added.';$view='pages';}
 if($action==='page_media_upload'){MediaLibraryService::upload($admin,$_POST,$_FILES['image']??[]);$notice='Page media uploaded and optimized.';$view='pages';}
 if($action==='page_media_update'){MediaLibraryService::update($admin,(int)$_POST['media_id'],$_POST);$notice='Page media details saved.';$view='pages';}
 if($action==='page_media_crop'){MediaLibraryService::cropResize($admin,(int)$_POST['media_id'],$_POST);$notice='Page media cropped and resized.';$view='pages';}
 if($action==='page_media_thumbnail'){MediaLibraryService::regenerateThumbnail($admin,(int)$_POST['media_id'],$_POST);$notice='Page media thumbnail recreated.';$view='pages';}
 if($action==='page_media_delete'){MediaLibraryService::delete($admin,(int)$_POST['media_id']);$notice='Page media deleted.';$view='pages';}
 if($action==='save_security'){$mode=in_array((string)($_POST['captcha_mode']??''),['BUILTIN','VISUAL','GOOGLE','OFF'],true)?(string)$_POST['captcha_mode']:'BUILTIN';$settings=['SECURITY_CAPTCHA_MODE'=>$mode,'GOOGLE_RECAPTCHA_SITE_KEY'=>trim((string)($_POST['recaptcha_site_key']??''))];if(trim((string)($_POST['recaptcha_secret_key']??''))!=='')$settings['GOOGLE_RECAPTCHA_SECRET_KEY']=trim((string)$_POST['recaptcha_secret_key']);$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');foreach($settings as$key=>$value)$q->execute([$key,$value,$admin['user_id']]);$notice='Login security settings saved.';$view='security';}
}catch(Throwable$e){$error=$e->getMessage();}}
$counts=['users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),'human'=>(int)db()->query('SELECT COUNT(*) FROM games')->fetchColumn(),'simulations'=>(int)db()->query('SELECT COUNT(*) FROM simulations')->fetchColumn(),'ratings'=>(int)db()->query('SELECT COUNT(*) FROM story_ratings')->fetchColumn()];
$users=$human=$sims=$invites=[];$detailUser=null;$hosted=$joined=$ratings=[];if($view==='accounts'||$view==='overview'){$q=db()->query('SELECT u.*,COUNT(DISTINCT gh.game_id) hosted_count,COUNT(DISTINCT gp.game_id) joined_count,COUNT(DISTINCT sr.story_rating_id) rating_count,ROUND(AVG(sr.rating),1) average_rating,EXISTS(SELECT 1 FROM user_platform_roles xur JOIN platform_roles xr ON xr.platform_role_id=xur.platform_role_id WHERE xur.user_id=u.user_id AND xr.role_code="WRITER") is_writer FROM users u LEFT JOIN games gh ON gh.host_user_id=u.user_id LEFT JOIN game_players gp ON gp.user_id=u.user_id LEFT JOIN story_ratings sr ON sr.user_id=u.user_id GROUP BY u.user_id ORDER BY u.created_at_utc DESC');$users=$q->fetchAll();}
if($view==='human'){$q=db()->query('SELECT g.*,s.status simulation_status,p.title,p.genre,u.display_name host_name,COUNT(DISTINCT gp.user_id) player_count,ROUND(AVG(sr.rating),1) average_rating,COUNT(DISTINCT sr.story_rating_id) rating_count FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id LEFT JOIN game_players gp ON gp.game_id=g.game_id LEFT JOIN story_ratings sr ON sr.game_id=g.game_id GROUP BY g.game_id ORDER BY g.created_at_utc DESC');$human=$q->fetchAll();}
if($view==='simulations'){$where=$simulationType==='standalone'?'WHERE g.game_id IS NULL':($simulationType==='hosted'?'WHERE g.game_id IS NOT NULL':'');$q=db()->query('SELECT s.simulation_id,s.status,s.current_phase,s.elapsed_seconds,s.created_at_utc,p.title,p.genre,g.game_id,COALESCE(hu.display_name,"System") host_name,COALESCE(cc.human_count,0) human_count,COALESCE(cc.ai_count,0) ai_count,rr.average_rating,COALESCE(rr.rating_count,0) rating_count FROM simulations s JOIN plots p ON p.plot_id=s.plot_id LEFT JOIN games g ON g.simulation_id=s.simulation_id LEFT JOIN users hu ON hu.user_id=g.host_user_id LEFT JOIN (SELECT simulation_id,SUM(controller_type="HUMAN") human_count,SUM(controller_type="AI") ai_count FROM characters GROUP BY simulation_id) cc ON cc.simulation_id=s.simulation_id LEFT JOIN (SELECT game_id,ROUND(AVG(rating),1) average_rating,COUNT(*) rating_count FROM story_ratings GROUP BY game_id) rr ON rr.game_id=g.game_id '.$where.' ORDER BY s.simulation_id DESC LIMIT 250');$sims=$q->fetchAll();}
if($view==='user'){$uid=(int)($_GET['user']??0);$q=db()->prepare('SELECT user_id,email,display_name,is_admin,status,created_at_utc,last_login_at_utc FROM users WHERE user_id=?');$q->execute([$uid]);$detailUser=$q->fetch();if(!$detailUser)throw new RuntimeException('User not found.');$q=db()->prepare('SELECT g.game_id,g.simulation_id,g.status,g.created_at_utc,p.title,p.genre FROM games g JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE g.host_user_id=? ORDER BY g.created_at_utc DESC');$q->execute([$uid]);$hosted=$q->fetchAll();$q=db()->prepare('SELECT g.game_id,g.simulation_id,g.status,g.created_at_utc,p.title,p.genre,u.display_name host_name,c.name character_name FROM game_players gp JOIN games g ON g.game_id=gp.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id JOIN users u ON u.user_id=g.host_user_id LEFT JOIN characters c ON c.character_id=gp.character_id WHERE gp.user_id=? ORDER BY g.created_at_utc DESC');$q->execute([$uid]);$joined=$q->fetchAll();$q=db()->prepare('SELECT sr.rating,sr.review_text,sr.created_at_utc,p.title,p.genre,g.simulation_id FROM story_ratings sr JOIN games g ON g.game_id=sr.game_id JOIN simulations s ON s.simulation_id=g.simulation_id JOIN plots p ON p.plot_id=s.plot_id WHERE sr.user_id=? ORDER BY sr.created_at_utc DESC');$q->execute([$uid]);$ratings=$q->fetchAll();}
if($view==='invitations'){$q=db()->query('SELECT i.*,u.display_name invited_by FROM user_invitations i JOIN users u ON u.user_id=i.invited_by_user_id ORDER BY i.created_at_utc DESC LIMIT 200');$invites=$q->fetchAll();}
$writerRequests=$storyReviews=$storyReviewGroups=$themes=[];$themeMedia=[];$parallaxLayers=[];$sitePages=[];$pageSections=[];$pageMedia=[];$socials=[];$security=['SECURITY_CAPTCHA_MODE'=>'BUILTIN','GOOGLE_RECAPTCHA_SITE_KEY'=>'','GOOGLE_RECAPTCHA_SECRET_KEY'=>''];$brandFonts=$brandFontRoles;if($view==='writer_requests')$writerRequests=db()->query('SELECT r.*,u.display_name,u.email,reviewer.display_name reviewer_name FROM writer_access_requests r JOIN users u ON u.user_id=r.user_id LEFT JOIN users reviewer ON reviewer.user_id=r.reviewed_by_user_id ORDER BY FIELD(r.status,"PENDING","APPROVED","DECLINED","WITHDRAWN"),r.created_at_utc DESC')->fetchAll();if($view==='story_reviews'){$groups=db()->query('SELECT p.plot_id,p.title,p.code,COUNT(*) revision_count,MAX(v.version_no) latest_version_no,MAX(v.created_at_utc) latest_created_at FROM plots p JOIN plot_versions v ON v.plot_id=p.plot_id GROUP BY p.plot_id,p.title,p.code ORDER BY latest_created_at DESC,p.title')->fetchAll();foreach($groups as$group){$pageKey='revisions_'.(int)$group['plot_id'];$pages=max(1,(int)ceil((int)$group['revision_count']/5));$page=min($pages,max(1,(int)($_GET[$pageKey]??1)));$offset=($page-1)*5;$q=db()->prepare('SELECT v.*,p.title,p.code,p.workflow_status plot_workflow,u.display_name submitted_by,reviewer.display_name reviewed_by FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id JOIN users u ON u.user_id=v.submitted_by_user_id LEFT JOIN users reviewer ON reviewer.user_id=v.reviewed_by_user_id WHERE v.plot_id=? ORDER BY v.version_no DESC,v.plot_version_id DESC LIMIT 5 OFFSET '.$offset);$q->execute([(int)$group['plot_id']]);$group['items']=$q->fetchAll();$group['page']=$page;$group['pages']=$pages;$group['page_key']=$pageKey;$storyReviewGroups[]=$group;}}if($view==='themes'){$themes=db()->query('SELECT g.*,t.* FROM story_genres g JOIN genre_themes t ON t.genre_id=g.genre_id ORDER BY g.sort_order,g.label_text')->fetchAll();$themeMedia=MediaLibraryService::visibleTo($admin);}if($view==='brand_fonts'){$q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_FONT_STYLES"');$q->execute();$saved=json_decode((string)$q->fetchColumn(),true);if(is_array($saved)){$legacyMap=['public_body'=>'body','internal_body'=>'body','public_hero_title'=>'headings','public_section_title'=>'headings','public_card_title'=>'headings','internal_page_title'=>'headings','internal_section_title'=>'headings','public_navigation'=>'navigation','internal_navigation'=>'navigation','public_section_label'=>'labels','public_meta'=>'labels','internal_labels'=>'labels','public_actions'=>'buttons','internal_actions'=>'buttons','internal_table_heading'=>'table_headings','brand_context'=>'brand_context'];foreach($brandFonts as$key=>&$role){$source=$saved[$key]??($saved[$legacyMap[$key]??'']??null);if(is_array($source))$role=array_merge($role,array_intersect_key($source,$role));}unset($role);}$parallaxLayers=ParallaxService::all();$socials=PageContentService::socials();}if($view==='pages'){$sitePages=PageContentService::pages();$validCodes=array_column($sitePages,'page_code');if(!in_array($pageCode,$validCodes,true))$pageCode=(string)($validCodes[0]??'HOME');$pageSections=PageContentService::sections($pageCode);$pageMedia=MediaLibraryService::visibleTo($admin);}if($view==='security'){$q=db()->query('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN("SECURITY_CAPTCHA_MODE","GOOGLE_RECAPTCHA_SITE_KEY","GOOGLE_RECAPTCHA_SECRET_KEY")');foreach($q->fetchAll()as$row)$security[$row['setting_key']]=$row['setting_value'];}
$reviewPlots=$reviewBatches=[];$selectedReviewBatch=null;if($view==='story_reviews'){$reviewPlots=db()->query('SELECT plot_id,code,title,workflow_status FROM plots ORDER BY title')->fetchAll();$reviewBatches=StorySimulationBatchService::batches();$selectedBatchId=(int)($_GET['batch']??0);if($selectedBatchId)$selectedReviewBatch=StorySimulationBatchService::one($selectedBatchId);}
function csrf_field():string{return'<input type="hidden" name="csrf_token" value="'.e(AuthService::csrfToken()).'">';}
function admin_query_url(array$changes):string{$query=array_merge($_GET,$changes);foreach($query as$key=>$value)if($value===null)unset($query[$key]);return'?'.http_build_query($query);}
function theme_rgb(string $color):string{return preg_match('/^#[0-9A-Fa-f]{6}/',$color)?strtoupper(substr($color,0,7)):'#000000';}
function theme_opacity(string $color):int{return preg_match('/^#[0-9A-Fa-f]{8}$/',$color)?(int)round(hexdec(substr($color,7,2))*100/255):100;}
function admin_color_control(string $label,string $name,string $color):string{$color=strtoupper($color);return'<label class="color-control page-color-control" data-color-control><span>'.e($label).'</span><input type="color" data-color-picker value="'.e(theme_rgb($color)).'"><input type="text" name="'.e($name).'" data-color-hex value="'.e($color).'" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required><span class="color-alpha"><input type="range" data-color-alpha min="0" max="100" value="'.theme_opacity($color).'" aria-label="'.e($label).' opacity"><output>'.theme_opacity($color).'%</output></span></label>';}
function admin_optional_color_control(string $label,string $name,?string $color,string $fallback):string{$current=$color?:$fallback;$value=$color?:'';return'<label class="color-control page-color-control" data-color-control data-optional-color><span>'.e($label).' <small>Follow Brand when blank</small></span><input type="color" data-color-picker value="'.e(theme_rgb($current)).'"><input type="text" name="'.e($name).'" data-color-hex value="'.e($value).'" pattern="^$|^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" placeholder="Follow Brand"><span class="color-alpha"><input type="range" data-color-alpha min="0" max="100" value="'.theme_opacity($current).'" aria-label="'.e($label).' opacity"><output>'.theme_opacity($current).'%</output></span><button class="color-clear" type="button" data-clear-color>Follow Brand</button></label>';}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Toinky Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond&family=Instrument+Sans&family=Cinzel&family=Alegreya+Sans+SC&family=Fredoka&family=Baloo+2&family=DM+Sans&family=Manrope&family=Oswald&family=Roboto+Slab&family=Playfair+Display&family=Uncial+Antiqua&family=MedievalSharp&family=Libre+Baskerville&family=EB+Garamond&family=Creepster&family=Nosifer&family=Special+Elite&family=IM+Fell+English&family=Spectral&family=Crimson+Pro&family=Lora&family=Parisienne&family=Orbitron&family=Space+Grotesk&family=Rye&family=Grenze+Gotisch&family=Barlow+Condensed&family=Archivo+Narrow&family=Bebas+Neue&family=Saira+Condensed&family=Inter&family=Merriweather&display=swap" rel="stylesheet">
<style>
:root{font-family:Inter,system-ui,sans-serif;color:#172033;background:#eef2f7}*{box-sizing:border-box}body{margin:0}.layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:#1C242B;color:#fff;padding:24px 16px}.brand{font-size:22px;font-weight:900;margin-bottom:28px}.group{font-size:11px;text-transform:uppercase;color:#F4EEE2;font-weight:800;letter-spacing:.08em;margin:22px 10px 7px}.side a{display:block;color:#F4EEE2;text-decoration:none;padding:9px 10px;border-radius:8px}.side a.active,.side a:hover{background:#66715C;color:#fff}.main{padding:28px;min-width:0}.top{display:flex;justify-content:space-between;align-items:center}.top a{color:#6B2938}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px}.metric,.panel{background:#fff;border:1px solid #dbe3ec;border-radius:14px;padding:18px}.metric b{font-size:32px;display:block}.panel{margin-top:18px;overflow:auto}.table{width:100%;border-collapse:collapse;font-size:13px}.table th,.table td{text-align:left;border-bottom:1px solid #e5e7eb;padding:10px 7px;vertical-align:top}.btn{border:0;border-radius:8px;background:#B58E46;color:#1C242B;padding:8px 11px;font:inherit;font-weight:750;cursor:pointer;text-decoration:none;display:inline-block}.btn.alt{background:#fff;color:#273043;border:1px solid #cbd5e1}.btn.danger{background:#fff1f2;color:#9f1239;border:1px solid #fecdd3}.field{display:block;margin:10px 0}.field input,.field textarea,.field select{width:min(480px,100%);display:block;padding:10px;border:1px solid #cbd5e1;border-radius:8px;margin-top:5px}.notice,.error{padding:12px;border-radius:9px;margin:12px 0}.notice{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.audit{white-space:pre-wrap;background:#111827;color:#f8fafc;padding:16px;border-radius:8px;max-height:560px;overflow:auto;font:12px/1.5 ui-monospace,monospace}.theme-preview{padding:18px;border-radius:10px;margin:10px 0}.theme-colors,.font-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.color-control{display:grid;grid-template-columns:46px minmax(105px,1fr);gap:7px 10px;align-items:center;padding:11px;border:1px solid #d5cec1;border-radius:6px;background:#fbf8f1}.color-control>span{grid-column:1/-1;font-weight:700}.color-control input[type=color]{width:46px;height:38px;padding:2px}.color-control input[type=text]{width:100%;font-family:"IBM Plex Mono",monospace;text-transform:uppercase}.color-alpha{grid-column:1/-1;display:grid;grid-template-columns:1fr auto;gap:8px;align-items:center}.color-alpha input{width:100%}.color-alpha output{min-width:42px;font:600 12px "IBM Plex Mono",monospace}.theme-tabs,.folder-tabs{display:flex;gap:8px;overflow-x:auto;padding:4px 2px 10px;position:sticky;top:0;background:#fff;z-index:3}.theme-tab,.folder-tab{white-space:nowrap;border:1px solid #cbd5e1;background:#fff;color:#273043;border-radius:8px 8px 0 0;padding:9px 12px;cursor:pointer;text-decoration:none}.theme-tab.active,.folder-tab.active{background:#B58E46;color:#1C242B;border-color:#B58E46}.theme-form[hidden]{display:none}.font-card{border:1px solid #d5cec1;border-radius:8px;padding:14px;background:#fbf8f1}.font-card h3{margin-top:0}.font-card .field input,.font-card .field select{width:100%}.font-color{display:grid;grid-template-columns:46px 1fr;gap:8px}.font-color input[type=color]{width:46px;height:40px;padding:2px}.brand-save{margin-top:22px}.parallax-list{display:grid;gap:14px;margin:16px 0}.parallax-card{display:grid;grid-template-columns:30px 180px minmax(320px,1fr);gap:16px;align-items:start;padding:14px;border:1px solid #d5cec1;border-radius:10px;background:#fbf8f1}.parallax-card.dragging{opacity:.45}.drag-handle{padding-top:52px;color:#66715C;font-size:22px;cursor:grab;text-align:center}.parallax-thumb{width:180px;aspect-ratio:16/9;object-fit:cover;border-radius:8px;border:1px solid #d5cec1;background:#e5e7eb}.parallax-fields{display:grid;grid-template-columns:repeat(5,minmax(105px,1fr));gap:8px}.parallax-fields .layer-name{grid-column:1/-1}.parallax-fields .field input{width:100%}.parallax-meta{margin:6px 0 0;font-size:12px;color:#64748b}.parallax-actions{display:flex;gap:8px;align-items:center;margin-top:10px}.parallax-add{margin-top:22px;padding:18px;border:1px dashed #B58E46;border-radius:10px;background:#fffaf0}.parallax-add-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:10px}.parallax-add-grid .wide{grid-column:1/-1}.url{word-break:break-all;background:#f8fafc;padding:10px;border-radius:8px}.actions form{display:inline}.top-actions form,.logout-form{margin:0}.correction-help{background:#F4EEE2;border-left:4px solid #B58E46;padding:14px;margin:14px 0}.correction-help code{font-family:"IBM Plex Mono",monospace}.correction-upload{margin-top:14px;padding-top:14px;border-top:1px solid #dbe3ec}.muted{color:#64748b}@media(max-width:900px){.parallax-card{grid-template-columns:28px 130px 1fr}.parallax-thumb{width:130px}.parallax-fields{grid-template-columns:repeat(2,minmax(110px,1fr))}.parallax-add-grid{grid-template-columns:1fr 1fr}}@media(max-width:760px){.layout{grid-template-columns:1fr}.side{position:static}.main{padding:18px}.theme-tabs{position:static}.parallax-card{grid-template-columns:24px 1fr}.parallax-thumb{grid-column:2;width:100%}.parallax-fields{grid-column:2}.parallax-add-grid{grid-template-columns:1fr}}
</style>
<style>
.color-control input[type=text],.color-alpha output,.correction-help code{font-family:"Instrument Sans",Arial,sans-serif}.page-tabs{display:flex;gap:8px;overflow-x:auto;margin:12px 0 18px}.page-section-card{border:1px solid #d5cec1;border-radius:12px;padding:18px;margin:16px 0;background:#fbf8f1}.page-editor-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(260px,.8fr);gap:18px}.editor-toolbar{display:flex;flex-wrap:wrap;gap:5px;padding:7px;border:1px solid #cbd5e1;border-bottom:0;border-radius:8px 8px 0 0;background:#f8fafc}.editor-toolbar button{background:#fff;border:1px solid #cbd5e1;border-radius:5px;padding:5px 8px;cursor:pointer}.rich-editor{min-height:180px;padding:12px;border:1px solid #cbd5e1;border-radius:0 0 8px 8px;background:#fff;overflow:auto}.media-choice{display:grid;grid-template-columns:90px 1fr;gap:10px;align-items:center}.media-choice img{width:90px;height:70px;object-fit:cover;border:1px solid #cbd5e1;border-radius:7px}.page-media-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px}.page-media-card{border:1px solid #d5cec1;border-radius:10px;padding:12px;background:#fbf8f1}.page-media-card>img{width:100%;height:180px;object-fit:cover;border-radius:7px;background:#e5e7eb}.media-form-row{display:grid;grid-template-columns:repeat(2,minmax(100px,1fr));gap:8px}.media-form-row .field input,.media-form-row .field select{width:100%}.source-dimensions{font-size:12px;color:#64748b}.page-media-upload{margin:20px 0;padding:18px;border:1px dashed #B58E46;border-radius:10px;background:#fffaf0}.section-columns{margin:16px 0;padding:12px;border:1px dashed #B58E46;border-radius:8px}.section-columns summary{font-weight:800;cursor:pointer}.three-column-editor,.social-settings-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px}.three-column-editor fieldset{min-width:0;border:1px solid #d5cec1;border-radius:8px}.three-column-editor .field input,.three-column-editor .field textarea,.three-column-editor .field select,.social-settings-grid .field input{width:100%}.remove-section-form{margin-top:10px}.add-section-form{padding:18px;border:1px dashed #B58E46;border-radius:10px;background:#fffaf0}.social-settings-grid .btn{align-self:end;margin-bottom:10px}@media(max-width:900px){.page-editor-grid,.three-column-editor,.social-settings-grid{grid-template-columns:1fr}}@media(max-width:760px){.media-form-row{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="assets/toinky-theme.css">
<link rel="stylesheet" href="assets/toinky-admin-shell.css">
<style>
.main{padding:20px!important}.top h1{margin:.15em 0;font-size:1.75rem}.top p{margin:.25em 0}.cards{gap:10px}.metric,.panel{padding:14px!important;border-radius:10px}.panel{margin-top:14px}.metric b{font-size:27px}.field{margin:8px 0}.field input,.field textarea,.field select{padding:8px!important}.table th,.table td{padding:8px 6px}.page-section-card{padding:0!important;overflow:hidden}.page-section-card>summary,.management-panel>summary,.font-card>summary{list-style:none;cursor:pointer}.page-section-card>summary::-webkit-details-marker,.management-panel>summary::-webkit-details-marker,.font-card>summary::-webkit-details-marker{display:none}.page-section-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;font-weight:800}.page-section-summary::after,.management-panel>summary::after,.font-card>summary::after{content:'+';font-size:18px}.page-section-card[open]>.page-section-summary::after,.management-panel[open]>summary::after,.font-card[open]>summary::after{content:'−'}.page-section-summary small{margin-left:auto;color:#64748b;font-weight:600}.page-section-body{padding:0 15px 15px;border-top:1px solid #e2ded6}.editor-toolbar button{background:#fff!important;color:#1C242B!important;border:1px solid #cbd5e1!important;border-radius:4px!important;padding:4px 7px!important;font-size:11px!important;font-weight:650!important;letter-spacing:0!important;text-transform:none!important;box-shadow:none!important}.editor-toolbar button:hover{background:#eef2f7!important;border-color:#8b98a5!important}.rich-editor{min-height:140px}.page-color-control{margin:10px 0;padding:9px}.page-editor-grid{gap:14px}.page-media-grid{grid-template-columns:repeat(auto-fill,minmax(190px,1fr))!important;gap:10px!important}.page-media-card{padding:8px!important;font-size:12px}.page-media-card>img{height:116px!important}.page-media-card .field{margin:6px 0}.page-media-card .field input,.page-media-card .field select{padding:6px!important;font-size:12px}.page-media-card details{margin-top:7px}.page-media-card summary{cursor:pointer;font-weight:750}.management-panel{margin-top:14px!important;padding:0!important;overflow:hidden}.management-panel>summary{display:flex;align-items:center;gap:10px;padding:14px 16px;font-size:1.25rem;font-weight:800}.management-panel>summary::after{margin-left:auto}.management-panel>summary .muted{font-size:12px;font-weight:650}.management-panel-body{padding:0 14px 14px;border-top:1px solid #e2ded6}.page-media-upload{padding:12px!important;margin:12px 0!important}.font-grid{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}.font-card{padding:0!important;overflow:hidden}.font-card>summary{display:flex;align-items:center;padding:11px 12px;font-weight:800}.font-card>summary::after{margin-left:auto}.font-card-body{padding:0 12px 12px;border-top:1px solid #e2ded6}.brand-font-guidance{padding:11px 13px;border-left:3px solid #668091;background:#f4eee2;margin:0 0 14px}.brand-font-guidance strong{color:#6B2938}.section-columns[hidden],[data-background-color-control][hidden],[data-background-media-controls][hidden],[data-overlay-start-control][hidden],[data-overlay-end-control][hidden]{display:none!important}.theme-media-panel{margin:14px 0;border:1px solid #d5cec1;border-radius:8px;background:#fbf8f1}.theme-media-panel>summary{padding:11px 13px;cursor:pointer;font-weight:800}.theme-media-body{padding:0 13px 12px;border-top:1px solid #e2ded6}@media(max-width:760px){.main{padding:14px!important}.page-section-summary{align-items:flex-start;flex-wrap:wrap}.page-section-summary small{width:100%;margin-left:0}.metric,.panel{padding:12px!important}}
</style>
<style>
[data-section-image-controls][hidden],[data-overlay-opacity-control][hidden]{display:none!important}.theme-form .font-card{padding:12px!important;overflow:visible}
.btn{background:#26323A!important;color:#F8F5EE!important;border:1px solid #26323A!important;box-shadow:none!important}.btn:hover{background:#172128!important}.btn.primary{background:#B58E46!important;color:#1C242B!important;border-color:#B58E46!important}.btn.alt{background:#fff!important;color:#273043!important;border-color:#cbd5e1!important}.btn.danger{background:#fff1f2!important;color:#9f1239!important;border-color:#fecdd3!important}
.theme-tabs,.folder-tabs{gap:18px;border-bottom:1px solid #d8d3ca;padding:2px 0 0}.theme-tab,.folder-tab{border:0!important;border-bottom:2px solid transparent!important;border-radius:0!important;background:transparent!important;color:#52606A!important;padding:9px 2px 10px!important;font-weight:700}.theme-tab:hover,.folder-tab:hover{color:#6B2938!important}.theme-tab.active,.folder-tab.active{background:transparent!important;color:#6B2938!important;border-bottom-color:#6B2938!important}
.typography-group-title{grid-column:1/-1;border-bottom:1px solid #d8d3ca;padding-top:8px}.typography-group-title h3{margin:0}.typography-group-title p{margin:3px 0 10px}.section-style-panel{margin:12px 0;border:1px solid #d5cec1;border-radius:8px;background:#fbf8f1}.section-style-panel>summary{cursor:pointer;padding:10px 12px;font-weight:800}.section-style-body{padding:0 12px 12px;border-top:1px solid #ded9d0}.spacing-grid{display:grid;grid-template-columns:repeat(4,minmax(62px,1fr));gap:8px}.spacing-grid .field input{width:100%}.type-override{border:1px solid #d8d3ca;border-radius:7px;margin:10px 0;padding:8px 10px}.type-override legend{font-weight:800}.override-badge{display:inline-block;margin-left:8px;padding:2px 6px;border-radius:999px;background:#efe5cf;color:#6B2938;font:700 10px Instrument Sans,sans-serif;text-transform:uppercase;letter-spacing:.05em}.color-clear{grid-column:1/-1;width:max-content;border:0;background:transparent;color:#6B2938;font-weight:700;padding:0;cursor:pointer}.color-control small{font-weight:500;color:#64748b}.top h1{font-size:1.65rem}.panel>h2{font-size:1.35rem;margin:.1em 0 .35em}.main{max-width:none}.font-role-preview{margin:8px 0 12px;padding:13px 14px;border:1px solid #ddd5c6;border-radius:6px;background:#fff;font-size:1.05rem;overflow-wrap:anywhere}
.font-role-usage{margin:.25rem 0;color:#52606a;font-size:.75rem}.font-role-preview{min-height:72px;max-height:220px;display:flex;align-items:center;font-size:inherit;overflow:auto}.font-card-actions{display:flex;justify-content:flex-end;margin:-2px 0 4px}.font-reset{border:1px solid #d5cec1!important;border-radius:4px!important;background:transparent!important;color:#6B2938!important;padding:3px 7px!important;font-size:10px!important;font-weight:700!important;line-height:1.2!important;letter-spacing:.02em!important;text-transform:none!important}.font-reset:hover{background:#f4eee2!important;border-color:#b58e46!important;transform:none!important}
.simulation-batch-form{display:grid;grid-template-columns:2fr 1.3fr .7fr .9fr .8fr auto;gap:10px;align-items:end}.simulation-batch-form .field{margin:0}.simulation-batch-form .field select,.simulation-batch-form .field input{width:100%}.worker-note{margin:10px 0;color:#64748b;font-size:10px}.worker-note summary{cursor:pointer;font-weight:750}.worker-note code{display:block;margin-top:6px;padding:7px;border-radius:5px;background:#f3f4f6;overflow:auto;font-size:10px}.revision-group{margin:14px 0;border:1px solid #dbe3ec;border-radius:10px;overflow:hidden}.revision-group-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 13px;background:#f7f8fa}.revision-group-heading h3{margin:0;font-size:15px}.revision-group .table{margin:0}.revision-pagination{display:flex;gap:5px;padding:9px 12px;border-top:1px solid #e5e7eb;background:#fafafa}.revision-pagination a{min-width:28px;padding:5px 7px;border:1px solid #cbd5e1;border-radius:5px;color:#273043;text-align:center;text-decoration:none;font-size:11px}.revision-pagination a.active{background:#26323A;color:#fff;border-color:#26323A}.batch-status{display:inline-block;padding:3px 7px;border-radius:999px;background:#eef0f1;color:#52606A;font-size:10px;font-weight:800}.batch-status.COMPLETE{background:#dcfce7;color:#166534}.batch-status.FAILED{background:#fee2e2;color:#991b1b}.batch-progress{width:140px;max-width:100%}.review-output{white-space:pre-wrap;background:#111827;color:#f8fafc;padding:16px;border-radius:8px;max-height:680px;overflow:auto;font:12px/1.5 ui-monospace,monospace}@media(max-width:1050px){.simulation-batch-form{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.simulation-batch-form{grid-template-columns:1fr}}
@media(max-width:760px){.spacing-grid{grid-template-columns:repeat(2,minmax(80px,1fr))}.theme-tabs,.folder-tabs{gap:14px}}
</style>
</head>
<body>
<div class="layout">
<?php require_once __DIR__.'/_workspace_sidebar.php';render_workspace_sidebar($admin,$view,true);?>
<main class="main">
<div class="top">
<div>
<h1>
<?=e($viewTitles[$view]??ucwords(str_replace('_',' ',$view)))?>
</h1>
<span class="muted">Signed in as <?=e($admin['display_name'])?>
</span>
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
<p class="muted">Revisions are grouped by story. Each group shows five revisions at a time, newest first.</p>
<?php foreach($storyReviewGroups as$group):?>
<section class="revision-group" id="story-revisions-<?=(int)$group['plot_id']?>">
<div class="revision-group-heading"><h3><?=e($group['title'])?> <span class="muted">· <?=e($group['code'])?></span></h3><span class="muted"><?=(int)$group['revision_count']?> revision<?=(int)$group['revision_count']===1?'':'s'?></span></div>
<table class="table"><tr><th>Revision</th><th>Submitted</th><th>Status</th><th>Action</th></tr>
<?php foreach($group['items']as$r):?><tr><td><b>Revision <?=(int)$r['version_no']?></b><?php if((int)$r['version_no']===(int)$group['latest_version_no']):?><br><span class="batch-status COMPLETE">Latest</span><?php endif;?></td><td><?=e($r['submitted_by'])?><br><span class="muted"><?=e($r['created_at_utc'])?></span></td><td><?=e(ucwords(strtolower(str_replace('_',' ',$r['workflow_status']))))?><?php if($r['reviewed_by']):?><br><span class="muted">by <?=e($r['reviewed_by'])?></span><?php endif;?></td><td><a class="btn" href="?view=story_review&amp;version=<?=(int)$r['plot_version_id']?>" target="_blank" rel="noopener">Review</a></td></tr><?php endforeach;?>
</table>
<?php if((int)$group['pages']>1):?><nav class="revision-pagination" aria-label="<?=e($group['title'])?> revision pages"><?php for($pageNo=1;$pageNo<=(int)$group['pages'];$pageNo++):?><a class="<?=$pageNo===(int)$group['page']?'active':''?>" href="<?=e(admin_query_url([$group['page_key']=>$pageNo]))?>#story-revisions-<?=(int)$group['plot_id']?>"><?=$pageNo?></a><?php endfor;?></nav><?php endif;?>
</section>
<?php endforeach;?>
<?php if(!$storyReviewGroups):?>
<p>No submitted revisions yet.</p>
<?php endif;?>
</section>
<section class="panel"><h2>Simulation Review Batches</h2><p class="muted">Run repeatable tests against one story, then generate evidence-backed improvement points and a copy-ready reviewer package. Jobs are stored in the database, so closing or refreshing Admin does not cancel them.</p><form method="post" class="simulation-batch-form"><?=csrf_field()?><input type="hidden" name="action" value="start_simulation_review_batch"><label class="field">Story<select name="plot_id" required><?php foreach($reviewPlots as$reviewPlot):?><option value="<?=(int)$reviewPlot['plot_id']?>"><?=e($reviewPlot['title'].' · '.$reviewPlot['code'])?></option><?php endforeach;?></select></label><label class="field">Control Mode<select name="controller_mode" title="Biased tests use each option’s Autonomous Choice Weight."><option value="AUTONOMOUS">All Autonomous</option><option value="HUMAN_RANDOM">All Human (Random)</option><option value="HUMAN_BIASED">All Human (Biased)</option></select></label><label class="field">Tests<input type="number" name="requested_runs" min="1" max="250" value="10"></label><label class="field">Story Duration (Seconds)<input type="number" name="duration_seconds" min="60" max="86400" value="300"></label><label class="field">Clock Step (Seconds)<input type="number" name="step_seconds" min="1" max="3600" value="30"></label><button class="btn">Queue Tests</button></form><details class="worker-note"><summary>Background Worker Setup</summary><code>/opt/cpanel/ea-php84/root/usr/bin/php /home/toinkyadmin/public_html/tools/process_simulation_review_batches.php --max=5</code><span>Run every minute. The manual button below is a fallback.</span></details><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="process_simulation_review_batch"><button class="btn alt">Run Next Queued Test Now</button></form>
<table class="table"><tr><th>Story / Mode</th><th>Progress</th><th>Status</th><th>Requested</th><th>Review</th></tr><?php foreach($reviewBatches as$batch):?><tr><td><b><?=e($batch['title'])?></b><br><span class="muted"><?=e(ucwords(strtolower(str_replace('_',' ',$batch['controller_mode']))))?></span></td><td><progress class="batch-progress" max="<?=(int)$batch['requested_runs']?>" value="<?=(int)$batch['completed_runs']?>"></progress><br><?=(int)$batch['completed_runs']?> / <?=(int)$batch['requested_runs']?></td><td><span class="batch-status <?=e($batch['status'])?>"><?=e(ucwords(strtolower($batch['status'])))?></span><?php if($batch['last_error']):?><br><span class="muted"><?=e($batch['last_error'])?></span><?php endif;?></td><td><?=e($batch['requested_by'])?><br><span class="muted"><?=e($batch['created_at_utc'])?></span></td><td><a class="btn alt" href="?view=story_reviews&amp;batch=<?=(int)$batch['simulation_review_batch_id']?>">Open</a></td></tr><?php endforeach;?></table>
</section>
<?php if($selectedReviewBatch):?><section class="panel"><h2><?=e($selectedReviewBatch['title'])?> · Batch <?=(int)$selectedReviewBatch['simulation_review_batch_id']?></h2><p><span class="batch-status <?=e($selectedReviewBatch['status'])?>"><?=e($selectedReviewBatch['status'])?></span> · <?=(int)$selectedReviewBatch['completed_runs']?> of <?=(int)$selectedReviewBatch['requested_runs']?> tests complete</p><?php $batchSummary=json_decode((string)($selectedReviewBatch['summary_json']??''),true)?:[];if($batchSummary):?><h3>Specific Improvement Points</h3><ol><?php foreach(($batchSummary['specific_improvement_points']??[])as$point):?><li><?=e($point)?></li><?php endforeach;?></ol><?php endif;?><?php if($selectedReviewBatch['review_package']):?><button type="button" class="btn" data-copy-target="simulation-review-package">Copy Reviewer Package</button><pre class="review-output" id="simulation-review-package"><?=e($selectedReviewBatch['review_package'])?></pre><?php elseif(in_array($selectedReviewBatch['status'],['QUEUED','RUNNING'],true)):?><p class="muted">This page will refresh while the worker completes the batch.</p><script>setTimeout(()=>location.reload(),15000)</script><?php endif;?></section><?php endif;?>

<?php elseif($view==='story_review'):?>
<?php $versionId=(int)($_GET['version']??0);$q=db()->prepare('SELECT v.*,p.title,p.code,u.display_name submitted_by,reviewer.display_name reviewed_by,(SELECT MAX(latest.version_no) FROM plot_versions latest WHERE latest.plot_id=v.plot_id) latest_version_no FROM plot_versions v JOIN plots p ON p.plot_id=v.plot_id JOIN users u ON u.user_id=v.submitted_by_user_id LEFT JOIN users reviewer ON reviewer.user_id=v.reviewed_by_user_id WHERE v.plot_version_id=?');$q->execute([$versionId]);$reviewVersion=$q->fetch();?>
<?php if(!$reviewVersion):?>
<section class="panel"><h2>Revision Not Found</h2><p>The requested story revision is unavailable.</p></section>
<?php else:?>
<section class="panel"><div class="review-heading"><div><h2><?=e($reviewVersion['title'])?> · Revision <?=(int)$reviewVersion['version_no']?></h2><p class="muted"><?=e($reviewVersion['code'])?> · submitted by <?=e($reviewVersion['submitted_by'])?> on <?=e($reviewVersion['created_at_utc'])?> · <?=e(ucwords(strtolower(str_replace('_',' ',$reviewVersion['workflow_status']))))?></p></div><button type="button" class="btn alt" onclick="window.close()">Close Window</button></div></section>
<section class="panel"><h2>Review and Decision</h2>
<form method="post" style="margin-bottom:18px"><?=csrf_field()?><input type="hidden" name="action" value="audit_story"><input type="hidden" name="plot_id" value="<?=(int)$reviewVersion['plot_id']?>"><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><button class="btn alt">Generate Reviewer Package</button></form>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="review_story"><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><label class="field">Decision<select name="decision"><option value="CHANGES_REQUESTED">Request changes</option><option value="APPROVED">Approve</option><option value="PUBLISHED">Publish</option><option value="ARCHIVED">Archive</option></select></label><label class="field">Review Notes<textarea name="review_notes" rows="5"><?=e($reviewVersion['review_notes']??'')?></textarea></label><button class="btn">Save Review</button></form>
<?php if((int)$reviewVersion['version_no']===(int)$reviewVersion['latest_version_no']):?><form method="post" enctype="multipart/form-data" class="correction-upload"><?=csrf_field()?><input type="hidden" name="plot_version_id" value="<?=(int)$reviewVersion['plot_version_id']?>"><label class="field">Reviewer Correction Package<input type="file" name="correction_file" accept=".json,.csv,application/json,text/csv" required></label><span class="muted">Latest revision only · maximum 2 MB · v2 structural packages require validation and explicit confirmation</span><div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px"><button class="btn alt" name="action" value="preview_story_corrections">Validate and Preview v2</button><label style="display:flex;align-items:center;gap:7px;font-size:12px"><input type="checkbox" name="confirm_structural" value="1"> I reviewed and approve every structural change in this file.</label><button class="btn" name="action" value="import_story_corrections">Apply as New Revision</button></div></form><?php else:?><div class="correction-help"><b>Correction upload unavailable.</b> Revision <?=(int)$reviewVersion['version_no']?> is archived for reference. Upload corrections to latest revision <?=(int)$reviewVersion['latest_version_no']?>.</div><?php endif;?>
</section>
<?php if($correctionPreview):?>
<section class="panel"><h2>Correction Validation Preview</h2><p><b><?=count($correctionPreview['report'])?></b> validated changes · <b><?=(int)$correctionPreview['structural_count']?></b> structural · source revision <b><?=(int)$correctionPreview['version_no']?></b></p><p class="muted">Nothing was changed during validation. Compare the old and proposed values below. To apply, reselect the same file, check the structural confirmation, and click Apply as New Revision.</p><table class="table"><tr><th>Risk</th><th>Target</th><th>Current</th><th>Proposed</th><th>Reason</th></tr><?php foreach($correctionPreview['report']as$item):?><tr><td><span class="batch-status <?=$item['risk']==='STRUCTURAL'?'FAILED':'COMPLETE'?>"><?=e(ucwords(strtolower($item['risk'])))?></span></td><td><b><?=e($item['entity'])?></b><br><code><?=e($item['key'])?></code></td><td><code><?=e(json_encode($item['before'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></code></td><td><code><?=e(json_encode($item['after'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></code></td><td><?=e($item['reason']?:'No model reason supplied.')?></td></tr><?php endforeach;?></table></section>
<?php if(!empty($correctionPreview['advisories'])):?><section class="panel"><h2>Reviewer Advisories</h2><p class="muted">These observations were not applied as changes. Evaluate them in Story Writer or a later simulation review.</p><ul><?php foreach($correctionPreview['advisories']as$advisory):?><li><?php if(is_array($advisory)):?><b><?=e($advisory['key'])?></b> · <?=e($advisory['issue'])?><br><span class="muted"><?=e($advisory['recommendation'])?></span><?php else:?><?=e($advisory)?><?php endif;?></li><?php endforeach;?></ul></section><?php endif;?>
<?php endif;?>
<?php if($auditText):?>
<section class="panel">
<h2>Reviewer-Ready Story Package</h2>
<p>Copy this into a capable reviewer. It includes the story’s managed values, timing, dependencies, effects, ending conditions, and strict correction rules.</p>
<button type="button" class="btn" id="copy-review-package">Copy All</button>
<div id="ai-review-copy-source">
<div class="correction-help">
<h3>Instructions for the Reviewer</h3>
<p><b>Story Design Contract:</b> fixed world events establish shared beats; character decisions create viewpoint-specific agency; conditional opportunities react to prior choices and managed Story Values; option effects create causal state changes; ending fragments read those states. Preserve that chain and keep all plot rules database-authored.</p>
<ol>
<li>Review the complete package. Tie every correction or advisory to a stable key and specific evidence from the package.</li>
<li>Return one complete <b>Toinky Story Corrections v2</b> JSON file. Do not return SQL, PHP, migrations, CSV, or narrative instructions in place of the file.</li>
<li>Preserve every stable key. Never add, delete, rename, merge, or split events, characters, decisions, options, opportunities, story values, or endings.</li>
<li>Use <code>{"format":"toinky-story-corrections-v2","plot_code":"<?=e($auditContext['code']??'STORY_CODE')?>","base_version":<?=(int)($auditContext['version_no']??0)?>,"changes":[{"operation":"update","entity":"event","key":"EVENT_CODE","expected":{"event_second":0},"values":{"event_second":45},"reason":"Named evidence supporting the change"}]}</code>.</li>
<li>Every update must include the exact current value in <code>expected</code> for every changed field. Never guess. If the package does not contain the current value, return an advisory instead of a change.</li>
<li>Use <code>advisories</code> for uncertain, creative, or structural recommendations that cannot be expressed safely with an existing key and allowed field. Prefer <code>{"key":"SHORT_CODE","issue":"Evidence-based concern","recommendation":"Specific next review step"}</code>. Advisories are shown in preview and never applied.</li>
<li>Content keys: <code>plot</code>; <code>character</code>; <code>event</code>; <code>decision</code> as <code>EVENT|ROLE</code>; <code>option</code> as <code>EVENT|ROLE|OPTION</code>; <code>opportunity</code>; <code>dynamic_option</code> as <code>OPPORTUNITY|OPTION</code>; and <code>resolution</code> as <code>SECTION|PRIORITY</code>.</li>
<li>Structural keys: <code>story_value</code> as <code>SCOPE|TARGET</code>; <code>dynamic_condition</code> as <code>OPPORTUNITY|SOURCE_TYPE|SOURCE_KEY_OR_-|CONDITION_ORDER</code>; <code>option_effect</code> as <code>EVENT|ROLE|OPTION|SCOPE|TARGET</code>; and <code>dynamic_effect</code> as <code>OPPORTUNITY|OPTION|SCOPE|TARGET</code>.</li>
<li><code>upsert</code> is permitted only for a missing option effect or dynamic effect and must use <code>"expected":null</code>. All other changes use <code>update</code>.</li>
<li>Event timing may change only when the final stable event sequence remains identical. Phase, stable codes, activation, cast size, ownership, and structural record creation remain protected.</li>
<li>Use exact types and units: seconds are whole numbers; autonomous weights are 0–10,000; preset values must come from the managed Story Value; operators must be EQ, NEQ, IN, NOT_IN, EXISTS, NOT_EXISTS, LT, LTE, GT, or GTE.</li>
<li>The administrator validates and previews the package before applying it. The import is transactional and creates a new SUBMITTED revision; it never publishes automatically.</li>
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
<label class="field">Text Transform<select name="<?=e($fields['transform'])?>">
<?php foreach($textTransforms as$value=>$label):?>
<option value="<?=$value?>" <?=($t[$fields['transform']]??'none')===(string)$value?'selected':''?>><?=e($label)?></option>
<?php endforeach;?>
</select></label>
</div>
<?php endforeach;?>
</div>
<details class="theme-media-panel"><summary>Media &amp; Textures</summary><div class="theme-media-body">
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
</div></details>
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
<a class="folder-tab <?=$brandTab==='fonts'?'active':''?>" href="?view=brand_fonts&amp;brand_tab=fonts">Typography</a>
<a class="folder-tab <?=$brandTab==='parallax'?'active':''?>" href="?view=brand_fonts&amp;brand_tab=parallax">Parallax</a>
<a class="folder-tab <?=$brandTab==='socials'?'active':''?>" href="?view=brand_fonts&amp;brand_tab=socials">Socials</a>
</div>
<?php if($brandTab==='fonts'):?>
<div class="brand-font-guidance"><strong>Typography is organized by where text appears.</strong> Public Website controls marketing pages; Application &amp; Admin controls signed-in workspaces. Page sections follow these defaults unless that section shows an override.</div>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_brand_fonts">
<input type="hidden" name="brand_tab" value="fonts">
<div class="font-grid">
<?php $fontGroup='';foreach($brandFonts as$key=>$role):if(($role['group']??'')!==$fontGroup):$fontGroup=(string)$role['group'];?><div class="typography-group-title"><h3><?=e($fontGroup)?></h3><p class="muted"><?=$fontGroup==='Public Website'?'Marketing pages, public navigation, and calls to action.':'Signed-in account, gameplay, writer, lab, and administrative screens.'?></p></div><?php endif;?>
<details class="font-card" data-font-card data-font-prefix="brand_<?=e($key)?>">
<summary>
<?=e($role['label'])?>
</summary>
<div class="font-card-body">
<p class="muted"><?=e($role['description']??'Site-wide typography role.')?></p>
<?php if(!empty($role['usage'])):?><p class="font-role-usage"><strong>Used on:</strong> <?=e($role['usage'])?></p><?php endif;?>
<div class="font-card-actions"><button type="button" class="font-reset" data-font-reset data-font-defaults="<?=e(json_encode(array_intersect_key($brandFontRoles[$key],array_flip(['family','color','dark_color','size','weight','line_height','letter_spacing','transform','decoration'])),JSON_UNESCAPED_SLASHES))?>" title="Restore this typography role to its original defaults">Reset</button></div>
<div class="font-role-preview" data-font-preview style="font-family:<?=e($role['family'])?>;font-size:<?=e($role['size'])?>;color:<?=e($role['color'])?>;font-weight:<?=e($role['weight'])?>;line-height:<?=e($role['line_height'])?>;letter-spacing:<?=e($role['letter_spacing'])?>;text-transform:<?=e($role['transform'])?>;text-decoration:<?=e($role['decoration'])?>"><?=e($role['preview']??($role['group']==='Public Website'?'One story. Different truths.':'Clear tools for stories in motion.'))?></div>
<label class="field">Font Family<select name="brand_<?=e($key)?>_family" required>
<?php foreach($themeFonts as$fontLabel=>$fontStack):?>
<option value="<?=e($fontStack)?>" <?=$role['family']===$fontStack?'selected':''?>>
<?=e($fontLabel)?>
</option>
<?php endforeach;?>
</select>
</label>
<label class="field">Default Color<span class="font-color" data-color-control>
<input type="color" data-color-picker value="<?=e(theme_rgb($role['color']))?>">
<input type="text" name="brand_<?=e($key)?>_color" data-color-hex value="<?=e(strtoupper($role['color']))?>" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required>
<span class="color-alpha">
<input type="range" data-color-alpha min="0" max="100" value="<?=theme_opacity($role['color'])?>">
<output>
<?=theme_opacity($role['color'])?>%</output>
</span>
</span>
</label>
<label class="field">Dark Surface Color<span class="font-color" data-color-control>
<input type="color" data-color-picker value="<?=e(theme_rgb($role['dark_color']))?>">
<input type="text" name="brand_<?=e($key)?>_dark_color" data-color-hex value="<?=e(strtoupper($role['dark_color']))?>" pattern="^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$" maxlength="9" required>
<span class="color-alpha"><input type="range" data-color-alpha min="0" max="100" value="<?=theme_opacity($role['dark_color'])?>"><output><?=theme_opacity($role['dark_color'])?>%</output></span>
</span></label>
<label class="field">Font Size<input name="brand_<?=e($key)?>_size" value="<?=e($role['size'])?>" required>
</label>
<label class="field">Font Weight<select name="brand_<?=e($key)?>_weight">
<?php foreach($fontWeights as$value=>$label):?>
<option value="<?=$value?>" <?=$role['weight']===(string)$value?'selected':''?>>
<?=e($label.' · '.$value)?>
</option>
<?php endforeach;?>
</select>
</label>
<div class="media-form-row"><label class="field">Line Height<input name="brand_<?=e($key)?>_line_height" value="<?=e($role['line_height'])?>" inputmode="decimal" required></label><label class="field">Letter Spacing<input name="brand_<?=e($key)?>_letter_spacing" value="<?=e($role['letter_spacing'])?>" placeholder="0 or 0.02em" required></label></div>
<label class="field">Text Transform<select name="brand_<?=e($key)?>_transform"><?php foreach($textTransforms as$value=>$label):?><option value="<?=e($value)?>" <?=$role['transform']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label class="field">Text Decoration<select name="brand_<?=e($key)?>_decoration">
<?php foreach($fontDecorations as$value=>$label):?>
<option value="<?=$value?>" <?=$role['decoration']===(string)$value?'selected':''?>>
<?=e($label)?>
</option>
<?php endforeach;?>
</select>
</label>
</div>
</details>
<?php endforeach;?>
</div>
<button class="btn primary brand-save">Save Brand Typography</button>
</form>
<?php elseif($brandTab==='parallax'):?>
<h3>Homepage Parallax Layers</h3>
<p class="muted">Drag layers into back-to-front order, then save the order. Offsets are pixels; negative values extend an image beyond an edge. Movement controls scroll speed from -100% to 100%, with negative values moving in reverse. The homepage clips all movement inside the hero row.</p>
<div class="parallax-list" data-parallax-list>
<?php foreach($parallaxLayers as$layer):?>
<article class="parallax-card" draggable="true" data-layer-id="<?=(int)$layer['parallax_layer_id']?>">
<div class="drag-handle" title="Drag to reorder" aria-label="Drag to reorder">⋮⋮</div>
<div>
<img class="parallax-thumb" src="<?=e($layer['image_path'])?>" alt="Preview of <?=e($layer['label_text'])?>">
<p class="parallax-meta"><?=(int)$layer['width_px']?> × <?=(int)$layer['height_px']?> · <?=e(strtoupper(pathinfo($layer['image_path'],PATHINFO_EXTENSION)))?></p>
</div>
<div>
<form method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_parallax_layer">
<input type="hidden" name="brand_tab" value="parallax">
<input type="hidden" name="parallax_layer_id" value="<?=(int)$layer['parallax_layer_id']?>">
<div class="parallax-fields">
<label class="field layer-name">Layer Name<input name="label_text" maxlength="120" value="<?=e($layer['label_text'])?>" required></label>
<label class="field">Left Offset<input type="number" name="offset_left_px" min="-1920" max="1920" step="1" value="<?=(int)$layer['offset_left_px']?>" required></label>
<label class="field">Right Offset<input type="number" name="offset_right_px" min="-1920" max="1920" step="1" value="<?=(int)$layer['offset_right_px']?>" required></label>
<label class="field">Top Offset<input type="number" name="offset_top_px" min="-1920" max="1920" step="1" value="<?=(int)$layer['offset_top_px']?>" required></label>
<label class="field">Bottom Offset<input type="number" name="offset_bottom_px" min="-1920" max="1920" step="1" value="<?=(int)$layer['offset_bottom_px']?>" required></label>
<label class="field">Movement %<input type="number" name="movement_percent" min="-100" max="100" step="0.1" value="<?=e((string)$layer['movement_percent'])?>" required></label>
</div>
<label class="field">Replace Image <span class="muted">(optional)</span><input type="file" name="layer_image" accept="image/png,image/jpeg,.png,.jpg,.jpeg"></label>
<button class="btn">Save Layer</button>
</form>
<form method="post" class="parallax-actions" onsubmit="return confirm('Remove this parallax layer?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="remove_parallax_layer">
<input type="hidden" name="brand_tab" value="parallax">
<input type="hidden" name="parallax_layer_id" value="<?=(int)$layer['parallax_layer_id']?>">
<button class="btn danger">Remove Layer</button>
</form>
</div>
</article>
<?php endforeach;?>
</div>
<?php if($parallaxLayers):?>
<form method="post" data-parallax-order-form>
<?=csrf_field()?>
<input type="hidden" name="action" value="reorder_parallax_layers">
<input type="hidden" name="brand_tab" value="parallax">
<input type="hidden" name="layer_order" data-layer-order value="<?=e(implode(',',array_column($parallaxLayers,'parallax_layer_id')))?>">
<button class="btn">Save Layer Order</button>
</form>
<?php endif;?>
<form method="post" enctype="multipart/form-data" class="parallax-add">
<?=csrf_field()?>
<input type="hidden" name="action" value="add_parallax_layer">
<input type="hidden" name="brand_tab" value="parallax">
<h3>Add Parallax Layer</h3>
<p class="muted">PNG and JPG only. Images are optimized and resized proportionally to a maximum of 1920 × 1080 pixels.</p>
<div class="parallax-add-grid">
<label class="field wide">Layer Name<input name="label_text" maxlength="120" required></label>
<label class="field wide">Image<input type="file" name="layer_image" accept="image/png,image/jpeg,.png,.jpg,.jpeg" required></label>
<label class="field">Left Offset<input type="number" name="offset_left_px" min="-1920" max="1920" step="1" value="0" required></label>
<label class="field">Right Offset<input type="number" name="offset_right_px" min="-1920" max="1920" step="1" value="0" required></label>
<label class="field">Top Offset<input type="number" name="offset_top_px" min="-1920" max="1920" step="1" value="0" required></label>
<label class="field">Bottom Offset<input type="number" name="offset_bottom_px" min="-1920" max="1920" step="1" value="0" required></label>
<label class="field">Movement %<input type="number" name="movement_percent" min="-100" max="100" step="0.1" value="0" required></label>
</div>
<button class="btn">Add Layer</button>
</form>
<?php else:?>
<h3>Social Media Links</h3>
<p class="muted">Add complete profile URLs. Only platforms with a saved link appear in the public website social row.</p>
<form method="post" class="social-settings-grid">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_socials">
<input type="hidden" name="brand_tab" value="socials">
<?php foreach($socials as$key=>$social):?>
<label class="field"><?=e($social['label'])?><input type="url" name="social_<?=e($key)?>" value="<?=e($social['url'])?>" placeholder="https://"></label>
<?php endforeach;?>
<button class="btn">Save Social Links</button>
</form>
<?php endif;?>
</section>
<?php elseif($view==='pages'):?>
<section class="panel">
<h2>Website Pages</h2>
<p class="muted">Edit each page by section. Text supports rich formatting; images may be chosen from the media library, while section backgrounds may use the default design, a color, or a media image.</p>
<nav class="page-tabs" aria-label="Website page editor">
<?php foreach($sitePages as$page):?>
<a class="folder-tab <?=$pageCode===$page['page_code']?'active':''?>" href="?view=pages&amp;page=<?=e($page['page_code'])?>"><?=e($page['label_text'])?></a>
<?php endforeach;?>
</nav>
<?php foreach($pageSections as$section):?>
<details class="page-section-card">
<summary class="page-section-summary"><span><?=e($section['label_text'])?></span><small><?=e(ucwords(strtolower(str_replace('_',' ',$section['layout_type']??'IMAGE_RIGHT'))))?></small></summary>
<div class="page-section-body">
<form method="post" data-rich-form>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_page_section">
<input type="hidden" name="page_code" value="<?=e($pageCode)?>">
<input type="hidden" name="site_page_section_id" value="<?=(int)$section['site_page_section_id']?>">
<div class="page-editor-grid">
<div>
<label class="field">Section Label<input name="eyebrow_text" maxlength="190" value="<?=e($section['eyebrow_text']??'')?>"></label>
<label class="field">Title<input name="title_text" maxlength="500" value="<?=e($section['title_text']??'')?>"></label>
<label class="field">Content</label>
<div class="editor-toolbar" data-editor-toolbar>
<button type="button" data-command="undo" title="Undo">↶</button><button type="button" data-command="redo" title="Redo">↷</button>
<button type="button" data-command="bold"><b>B</b></button><button type="button" data-command="italic"><i>I</i></button><button type="button" data-command="underline"><u>U</u></button>
<button type="button" data-block="h2">Heading 2</button><button type="button" data-block="h3">Heading 3</button><button type="button" data-block="p">Paragraph</button>
<button type="button" data-command="insertUnorderedList">Bullets</button><button type="button" data-command="insertOrderedList">Numbers</button><button type="button" data-link>Link</button><button type="button" data-command="removeFormat">Clear</button>
</div>
<div class="rich-editor" contenteditable="true" data-rich-editor><?=($section['content_html']??'')?></div>
<textarea name="content_html" data-rich-source hidden><?=e($section['content_html']??'')?></textarea>
</div>
<div>
<label class="field">Section Layout<select name="layout_type" data-section-layout>
<?php foreach(['IMAGE_RIGHT'=>'Image On Right','IMAGE_LEFT'=>'Image On Left','THREE_COLUMN'=>'Three Columns','SOCIALS'=>'Social Media Buttons','TEXT_CENTER'=>'No Image, Centered Text']as$layoutValue=>$layoutLabel):?><option value="<?=e($layoutValue)?>" <?=($section['layout_type']??'IMAGE_RIGHT')===$layoutValue?'selected':''?>><?=e($layoutLabel)?></option><?php endforeach;?>
</select></label>
<div data-section-image-controls><label class="field">Section Image<select name="image_media_id"><option value="">No image</option><?php foreach($pageMedia as$media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['image_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=e($media['title_text'].' · '.$media['media_type'])?></option><?php endforeach;?></select></label>
<?php if(!empty($section['image_path'])):?><div class="media-choice"><img src="<?=e($section['image_thumbnail']?:$section['image_path'])?>" alt=""><span class="muted">Current section image</span></div><?php endif;?></div>
<label class="field">Section Background<select name="background_mode" data-background-mode><option value="DEFAULT" <?=$section['background_mode']==='DEFAULT'?'selected':''?>>Default page design</option><option value="COLOR" <?=$section['background_mode']==='COLOR'?'selected':''?>>Solid color</option><option value="MEDIA" <?=$section['background_mode']==='MEDIA'?'selected':''?>>Media image</option></select></label>
<div data-background-color-control><?=admin_color_control('Background Color','background_color',(string)($section['background_color']?:'#F4EEE2'))?></div>
<div data-background-media-controls><label class="field">Background Image<select name="background_media_id"><option value="">Choose an image</option><?php foreach($pageMedia as$media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($section['background_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=e($media['title_text'].' · '.$media['media_type'])?></option><?php endforeach;?></select></label>
<label class="field">Background Image Fit<select name="background_fit"><?php foreach(['COVER'=>'Cover Section','CONTAIN'=>'Fit Entire Image','TILE'=>'Tile / Repeat','STRETCH'=>'Stretch To Section','CENTER'=>'Original Size, Centered']as$fitValue=>$fitLabel):?><option value="<?=e($fitValue)?>" <?=($section['background_fit']??'COVER')===$fitValue?'selected':''?>><?=e($fitLabel)?></option><?php endforeach;?></select></label>
<label class="field">Background Image Opacity <output><?=(int)($section['background_opacity']??100)?>%</output><input type="range" name="background_opacity" min="0" max="100" value="<?=(int)($section['background_opacity']??100)?>" oninput="this.previousElementSibling.value=this.value+'%'"></label></div>
<label class="field">Tint / Overlay<select name="overlay_mode" data-overlay-mode><option value="NONE" <?=($section['overlay_mode']??'NONE')==='NONE'?'selected':''?>>None</option><option value="COLOR" <?=($section['overlay_mode']??'NONE')==='COLOR'?'selected':''?>>Plain Color Tint</option><option value="GRADIENT" <?=($section['overlay_mode']??'NONE')==='GRADIENT'?'selected':''?>>Gradient Tint</option></select></label>
<div data-overlay-start-control><?=admin_color_control('Tint Color','overlay_color_start',(string)($section['overlay_color_start']?:'#1C242B'))?></div><div data-overlay-end-control><?=admin_color_control('Gradient End','overlay_color_end',(string)($section['overlay_color_end']?:'#6B2938'))?></div>
<label class="field" data-overlay-opacity-control>Tint Opacity <output><?=(int)($section['overlay_opacity']??0)?>%</output><input type="range" name="overlay_opacity" min="0" max="100" value="<?=(int)($section['overlay_opacity']??0)?>" oninput="this.previousElementSibling.value=this.value+'%'"></label>
<details class="section-style-panel"><summary>Inside Spacing</summary><div class="section-style-body">
<label class="field">Spacing Preset<select data-spacing-preset><option value="inherit">Follow Page Design</option><option value="compact">Compact</option><option value="standard">Standard</option><option value="spacious">Spacious</option><option value="custom">Custom</option></select></label>
<p class="muted">Blank values inherit the page design. Mobile left and right padding retain a safe minimum even when a smaller custom value is entered.</p>
<div class="spacing-grid" data-spacing-fields><?php foreach(['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left']as$edge=>$label):?><label class="field"><?=e($label)?><input type="number" name="padding_<?=e($edge)?>_px" min="0" max="400" value="<?=e((string)($section['padding_'.$edge.'_px']??''))?>" placeholder="Default"></label><?php endforeach;?></div>
<h4>Mobile Overrides <span class="muted">(optional)</span></h4><div class="spacing-grid"><?php foreach(['top'=>'Top','right'=>'Right','bottom'=>'Bottom','left'=>'Left']as$edge=>$label):?><label class="field"><?=e($label)?><input type="number" name="mobile_padding_<?=e($edge)?>_px" min="0" max="400" value="<?=e((string)($section['mobile_padding_'.$edge.'_px']??''))?>" placeholder="Desktop value"></label><?php endforeach;?></div>
</div></details>
<details class="section-style-panel"><summary>Typography Overrides</summary><div class="section-style-body"><p class="muted">These controls override Brand → Typography only for this section. Blank fields continue following the brand role.</p>
<?php foreach(['label'=>['Section Label','#6B2938'],'title'=>['Section Title','#1C242B'],'body'=>['Body Text','#35424B']]as$typeKey=>$typeInfo):?><fieldset class="type-override"><legend><?=e($typeInfo[0])?><?php if(!empty($section[$typeKey.'_font_size'])||!empty($section[$typeKey.'_color'])||!empty($section[$typeKey.'_transform'])):?><span class="override-badge">Override</span><?php endif;?></legend><label class="field">Font Size<input name="<?=e($typeKey)?>_font_size" value="<?=e((string)($section[$typeKey.'_font_size']??''))?>" placeholder="Follow Brand"></label><?=admin_optional_color_control('Color',$typeKey.'_color',$section[$typeKey.'_color']??null,$typeInfo[1])?><label class="field">Text Transform<select name="<?=e($typeKey)?>_transform"><option value="">Follow Brand</option><?php foreach($textTransforms as$value=>$label):?><option value="<?=e($value)?>" <?=($section[$typeKey.'_transform']??'')===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label></fieldset><?php endforeach;?>
</div></details>
</div>
</div>
<?php $sectionColumns=PageContentService::columns($section);?>
<details class="section-columns" data-section-columns <?=($section['layout_type']??'')==='THREE_COLUMN'?'':'hidden'?>><summary>Three-Column Content</summary><div class="three-column-editor">
<?php for($columnIndex=1;$columnIndex<=3;$columnIndex++):$column=$sectionColumns[$columnIndex-1]??[];?><fieldset><legend>Column <?=$columnIndex?></legend><label class="field">Thumbnail<select name="column_<?=$columnIndex?>_image_media_id"><option value="">No thumbnail</option><?php foreach($pageMedia as$media):?><option value="<?=(int)$media['media_id']?>" <?=(int)($column['image_media_id']??0)===(int)$media['media_id']?'selected':''?>><?=e($media['title_text'])?></option><?php endforeach;?></select></label><label class="field">Title<input name="column_<?=$columnIndex?>_title" maxlength="190" value="<?=e($column['title']??'')?>"></label><label class="field">Content<textarea name="column_<?=$columnIndex?>_content" rows="5"><?=e($column['content']??'')?></textarea></label></fieldset><?php endfor;?>
</div></details>
<button class="btn">Save <?=e($section['label_text'])?></button>
</form>
</div>
</details>
<?php endforeach;?>
<?php if(!$pageSections):?><p class="muted">This page has no editable sections yet.</p><?php endif;?>
<form method="post" class="add-section-form"><?=csrf_field()?><input type="hidden" name="action" value="add_page_section"><input type="hidden" name="page_code" value="<?=e($pageCode)?>"><h3>Add A New Section</h3><div class="media-form-row"><label class="field">Section Name<input name="label_text" maxlength="120" required placeholder="Example: Testimonials"></label><label class="field">Starting Layout<select name="layout_type"><?php foreach(['IMAGE_RIGHT'=>'Image On Right','IMAGE_LEFT'=>'Image On Left','THREE_COLUMN'=>'Three Columns','SOCIALS'=>'Social Media Buttons','TEXT_CENTER'=>'No Image, Centered Text']as$layoutValue=>$layoutLabel):?><option value="<?=e($layoutValue)?>"><?=e($layoutLabel)?></option><?php endforeach;?></select></label></div><button class="btn">Add Section</button></form>
</section>

<details class="panel management-panel">
<summary>Page Media <span class="muted"><?=count($pageMedia)?> item<?=count($pageMedia)===1?'':'s'?></span></summary>
<div class="management-panel-body">
<p class="muted">Uploaded files are immediately resized to a maximum 1920-pixel edge. The full-size upload is not retained, and a 150 × 150 thumbnail is created automatically.</p>
<form method="post" enctype="multipart/form-data" class="page-media-upload">
<?=csrf_field()?>
<input type="hidden" name="action" value="page_media_upload"><input type="hidden" name="page_code" value="<?=e($pageCode)?>">
<div class="media-form-row"><label class="field">Media Type<select name="media_type"><?php foreach(MediaLibraryService::TYPES as$type):?><option value="<?=e($type)?>" <?=$type==='OTHER'?'selected':''?>><?=e(ucwords(strtolower(str_replace('_',' ',$type))))?></option><?php endforeach;?></select></label><label class="field">Image<input type="file" name="image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" required></label></div>
<label class="field">Title<input name="title_text" maxlength="190" required></label><label class="field">Alternative Text<input name="alt_text" maxlength="500" required></label>
<button class="btn">Upload And Optimize</button>
</form>
<div class="page-media-grid">
<?php foreach($pageMedia as$media):$preview=$media['thumbnail_path']?:$media['file_path'];?>
<article class="page-media-card">
<img src="<?=e($preview)?>" alt="<?=e($media['alt_text'])?>"><p class="source-dimensions"><?=(int)$media['width_px']?> × <?=(int)$media['height_px']?> · <?=e($media['mime_type'])?></p>
<form method="post"><?=csrf_field()?><input type="hidden" name="action" value="page_media_update"><input type="hidden" name="page_code" value="<?=e($pageCode)?>"><input type="hidden" name="media_id" value="<?=(int)$media['media_id']?>"><label class="field">Type<select name="media_type"><?php foreach(MediaLibraryService::TYPES as$type):?><option value="<?=e($type)?>" <?=$media['media_type']===$type?'selected':''?>><?=e(ucwords(strtolower(str_replace('_',' ',$type))))?></option><?php endforeach;?></select></label><label class="field">Title<input name="title_text" maxlength="190" value="<?=e($media['title_text'])?>" required></label><label class="field">Alternative Text<input name="alt_text" maxlength="500" value="<?=e($media['alt_text'])?>" required></label><button class="btn alt">Save Details</button></form>
<details><summary>Crop or resize processed image</summary><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="page_media_crop"><input type="hidden" name="page_code" value="<?=e($pageCode)?>"><input type="hidden" name="media_id" value="<?=(int)$media['media_id']?>"><div class="media-form-row"><label class="field">Crop X<input type="number" name="crop_x" min="0" max="<?=(int)$media['width_px']?>" value="0"></label><label class="field">Crop Y<input type="number" name="crop_y" min="0" max="<?=(int)$media['height_px']?>" value="0"></label><label class="field">Crop Width<input type="number" name="crop_width" min="1" max="<?=(int)$media['width_px']?>" value="<?=(int)$media['width_px']?>"></label><label class="field">Crop Height<input type="number" name="crop_height" min="1" max="<?=(int)$media['height_px']?>" value="<?=(int)$media['height_px']?>"></label><label class="field">Resize Width<input type="number" name="resize_width" min="1" max="1920" placeholder="Keep crop width"></label><label class="field">Resize Height<input type="number" name="resize_height" min="1" max="1920" placeholder="Auto"></label></div><button class="btn alt">Apply Crop / Resize</button></form></details>
<details><summary>Recreate 150 × 150 thumbnail</summary><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="page_media_thumbnail"><input type="hidden" name="page_code" value="<?=e($pageCode)?>"><input type="hidden" name="media_id" value="<?=(int)$media['media_id']?>"><div class="media-form-row"><label class="field">Crop X<input type="number" name="thumbnail_crop_x" min="0" max="<?=(int)$media['width_px']?>" value="<?=(int)($media['thumbnail_crop_x']??0)?>"></label><label class="field">Crop Y<input type="number" name="thumbnail_crop_y" min="0" max="<?=(int)$media['height_px']?>" value="<?=(int)($media['thumbnail_crop_y']??0)?>"></label><label class="field">Square Size<input type="number" name="thumbnail_crop_size" min="1" max="<?=min((int)$media['width_px'],(int)$media['height_px'])?>" value="<?=(int)($media['thumbnail_crop_size']?:min((int)$media['width_px'],(int)$media['height_px']))?>"></label></div><button class="btn alt">Recreate Thumbnail</button></form></details>
<form method="post" onsubmit="return confirm('Permanently delete this media item?')"><?=csrf_field()?><input type="hidden" name="action" value="page_media_delete"><input type="hidden" name="page_code" value="<?=e($pageCode)?>"><input type="hidden" name="media_id" value="<?=(int)$media['media_id']?>"><button class="btn danger">Delete Media</button></form>
</article>
<?php endforeach;?>
</div>
</div>
</details>
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
Simulations
</h2>
<p class="muted">Review standalone engine runs and simulations connected to hosted games in one place.</p>
<nav class="page-tabs" aria-label="Simulation type">
<a class="folder-tab <?=$simulationType==='all'?'active':''?>" href="admin.php?view=simulations&amp;simulation_type=all">All</a>
<a class="folder-tab <?=$simulationType==='standalone'?'active':''?>" href="admin.php?view=simulations&amp;simulation_type=standalone">Standalone</a>
<a class="folder-tab <?=$simulationType==='hosted'?'active':''?>" href="admin.php?view=simulations&amp;simulation_type=hosted">Hosted Games</a>
</nav>
<table class="table">
<tr>
<th>ID</th>
<th>Story</th>
<th>Type</th>
<th>Host</th>
<th>Players / Autonomous</th>
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
<?=$s['game_id']?'Hosted Game':'Standalone'?>
</td>
<td>
<?=e($s['host_name'])?>
</td>
<td>
<?=(int)$s['human_count']?> human · <?=(int)$s['ai_count']?> autonomous</td>
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
document.querySelectorAll('[data-rich-form]').forEach(form=>{
 const editor=form.querySelector('[data-rich-editor]');const source=form.querySelector('[data-rich-source]');const toolbar=form.querySelector('[data-editor-toolbar]');
 if(!editor||!source||!toolbar)return;
 toolbar.addEventListener('mousedown',event=>event.preventDefault());
 toolbar.addEventListener('click',event=>{const button=event.target.closest('button');if(!button)return;editor.focus();if(button.dataset.command)document.execCommand(button.dataset.command,false,null);if(button.dataset.block)document.execCommand('formatBlock',false,button.dataset.block);if(button.hasAttribute('data-link')){const href=window.prompt('Link URL');if(href)document.execCommand('createLink',false,href);}});
 form.addEventListener('submit',()=>source.value=editor.innerHTML.trim());
});
document.querySelectorAll('.page-section-card form').forEach(form=>{
 const layout=form.querySelector('[data-section-layout]');
 const columns=form.querySelector('[data-section-columns]');
 const sectionImage=form.querySelector('[data-section-image-controls]');
 const backgroundMode=form.querySelector('[data-background-mode]');
 const backgroundColor=form.querySelector('[data-background-color-control]');
 const backgroundMedia=form.querySelector('[data-background-media-controls]');
 const overlayMode=form.querySelector('[data-overlay-mode]');
 const overlayStart=form.querySelector('[data-overlay-start-control]');
 const overlayEnd=form.querySelector('[data-overlay-end-control]');
 const overlayOpacity=form.querySelector('[data-overlay-opacity-control]');
 const spacingPreset=form.querySelector('[data-spacing-preset]');
 const spacingFields=form.querySelector('[data-spacing-fields]');
 const syncLayout=()=>{
  if(!layout)return;
  const threeColumns=layout.value==='THREE_COLUMN';
  if(columns){columns.hidden=!threeColumns;if(!threeColumns)columns.open=false;}
  if(sectionImage)sectionImage.hidden=!['IMAGE_LEFT','IMAGE_RIGHT'].includes(layout.value);
 };
 const syncBackground=()=>{
  if(!backgroundMode)return;
  if(backgroundColor)backgroundColor.hidden=backgroundMode.value!=='COLOR';
  if(backgroundMedia)backgroundMedia.hidden=backgroundMode.value!=='MEDIA';
 };
 const syncOverlay=()=>{
  if(!overlayMode)return;
  const active=overlayMode.value!=='NONE';
  if(overlayStart)overlayStart.hidden=!active;
  if(overlayEnd)overlayEnd.hidden=overlayMode.value!=='GRADIENT';
  if(overlayOpacity)overlayOpacity.hidden=!active;
 };
 layout?.addEventListener('change',syncLayout);backgroundMode?.addEventListener('change',syncBackground);overlayMode?.addEventListener('change',syncOverlay);
 spacingPreset?.addEventListener('change',()=>{
  const presets={inherit:['','','',''],compact:[40,24,40,24],standard:[72,48,72,48],spacious:[112,72,112,72]};
  if(!presets[spacingPreset.value]||!spacingFields)return;
  [...spacingFields.querySelectorAll('input')].forEach((input,index)=>input.value=presets[spacingPreset.value][index]);
 });
 syncLayout();syncBackground();syncOverlay();
});
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
 const clear=control.querySelector('[data-clear-color]');
 clear?.addEventListener('click',()=>{hex.value='';});
});
document.querySelectorAll('[data-font-card]').forEach(card=>{
 const prefix=card.dataset.fontPrefix;const preview=card.querySelector('[data-font-preview]');if(!prefix||!preview)return;
 const field=suffix=>card.querySelector(`[name="${prefix}_${suffix}"]`);
 const sync=()=>{preview.style.fontFamily=field('family')?.value||'';preview.style.fontSize=field('size')?.value||'';preview.style.color=field('color')?.value||'';preview.style.fontWeight=field('weight')?.value||'';preview.style.lineHeight=field('line_height')?.value||'';preview.style.letterSpacing=field('letter_spacing')?.value||'';preview.style.textTransform=field('transform')?.value||'none';preview.style.textDecoration=field('decoration')?.value||'none';};
 const reset=card.querySelector('[data-font-reset]');
 reset?.addEventListener('click',()=>{let defaults={};try{defaults=JSON.parse(reset.dataset.fontDefaults||'{}');}catch(error){return;}Object.entries(defaults).forEach(([suffix,value])=>{const input=field(suffix);if(!input)return;input.value=String(value);input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));});sync();});
 card.addEventListener('input',sync);card.addEventListener('change',sync);
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
document.querySelectorAll('[data-copy-target]').forEach(button=>button.addEventListener('click',async()=>{const source=document.getElementById(button.dataset.copyTarget);if(!source)return;const value=source.innerText.trim();try{await navigator.clipboard.writeText(value);}catch(error){const range=document.createRange();range.selectNodeContents(source);const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);document.execCommand('copy');selection.removeAllRanges();}const original=button.textContent;button.textContent='Copied';setTimeout(()=>button.textContent=original,1800);}));
const parallaxList=document.querySelector('[data-parallax-list]');
if(parallaxList){
 let dragged=null;
 const syncOrder=()=>{
  const input=document.querySelector('[data-layer-order]');
  if(input)input.value=[...parallaxList.querySelectorAll('[data-layer-id]')].map(item=>item.dataset.layerId).join(',');
 };
 parallaxList.querySelectorAll('[data-layer-id]').forEach(item=>{
  item.addEventListener('dragstart',event=>{dragged=item;item.classList.add('dragging');event.dataTransfer.effectAllowed='move';});
  item.addEventListener('dragend',()=>{item.classList.remove('dragging');dragged=null;syncOrder();});
 });
 parallaxList.addEventListener('dragover',event=>{
  event.preventDefault();if(!dragged)return;
  const candidates=[...parallaxList.querySelectorAll('[data-layer-id]:not(.dragging)')];
  const next=candidates.find(item=>event.clientY<=item.getBoundingClientRect().top+item.offsetHeight/2);
  parallaxList.insertBefore(dragged,next||null);
 });
 syncOrder();
}
</script>
</body>
</html>
