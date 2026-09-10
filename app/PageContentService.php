<?php
require_once __DIR__.'/MediaLibraryService.php';

final class PageContentService
{
    public const LAYOUTS=['IMAGE_RIGHT','IMAGE_LEFT','THREE_COLUMN','FOUR_COLUMN','SOCIALS','TEXT_CENTER','TEXT_TOP','CARDS_ONLY','TEXT_BULLETS','CONTAINER','STORY_CARDS'];
    public const BACKGROUND_FITS=['COVER','CONTAIN','TILE','STRETCH','CENTER'];
    public const TEXT_TRANSFORMS=['none','uppercase','capitalize','lowercase'];
    public const SOCIAL_PLATFORMS=['facebook'=>'Facebook','instagram'=>'Instagram','x'=>'X / Twitter','tiktok'=>'TikTok','youtube'=>'YouTube','linkedin'=>'LinkedIn','pinterest'=>'Pinterest','threads'=>'Threads','bluesky'=>'Bluesky','discord'=>'Discord'];
    public const BUTTON_LIMIT=8;
    public const BUTTON_HOVER_EFFECTS=['NONE','LIFT','GLOW','UNDERLINE'];
    public const BUTTON_BACKGROUND_TYPES=['SOLID','TRANSPARENT','GRADIENT'];
    public const BUTTON_WIDTHS=['AUTO','FULL','FIXED'];

    public static function pages(): array
    {
        return db()->query('SELECT * FROM site_pages WHERE active=1 ORDER BY sort_order,site_page_id')->fetchAll();
    }

    public static function sections(string $pageCode): array
    {
        $q=db()->prepare('SELECT s.*,im.file_path image_path,im.thumbnail_path image_thumbnail,im.alt_text image_alt,bg.media_id background_media_id,bg.file_path background_path FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id LEFT JOIN writer_media_assets im ON im.media_id=s.image_media_id AND im.active=1 LEFT JOIN writer_media_assets bg ON bg.media_id=s.background_media_id AND bg.active=1 WHERE p.page_code=? AND p.active=1 AND s.active=1 ORDER BY s.sort_order,s.site_page_section_id');$q->execute([strtoupper($pageCode)]);return$q->fetchAll();
    }

    public static function adminSections(string $pageCode): array
    {
        $q=db()->prepare('SELECT s.*,im.file_path image_path,im.thumbnail_path image_thumbnail,im.alt_text image_alt,bg.media_id background_media_id,bg.file_path background_path FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id LEFT JOIN writer_media_assets im ON im.media_id=s.image_media_id AND im.active=1 LEFT JOIN writer_media_assets bg ON bg.media_id=s.background_media_id AND bg.active=1 WHERE p.page_code=? AND p.active=1 ORDER BY s.sort_order,s.site_page_section_id');$q->execute([strtoupper($pageCode)]);return $q->fetchAll();
    }
    public static function keyed(string $pageCode): array
    {
        $out=[];foreach(self::sections($pageCode)as$section)$out[$section['section_code']]=$section;return$out;
    }

    public static function rows(array $section): array
    {
        $saved=json_decode((string)($section['rows_json']??''),true);
        if(!is_array($saved)||!$saved)return [self::fallbackRow($section)];
        $rows=[];foreach($saved as $index=>$row)if(is_array($row))$rows[]=self::normalizeRow($row,(int)$index);
        return $rows?:[self::fallbackRow($section)];
    }

    public static function editorRows(array $section): array{return self::rows($section);}

    public static function setSectionActive(array $admin,int $sectionId,bool $active): void
    {if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');db()->prepare('UPDATE site_page_sections SET active=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([$active?1:0,$admin['user_id'],$sectionId]);}

    public static function deleteSection(array $admin,int $sectionId): void
    {if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$q=db()->prepare('SELECT is_default FROM site_page_sections WHERE site_page_section_id=?');$q->execute([$sectionId]);if((int)$q->fetchColumn()===1)throw new RuntimeException('Default page sections cannot be deleted. Hide the section instead.');db()->prepare('DELETE FROM site_page_sections WHERE site_page_section_id=?')->execute([$sectionId]);}

    public static function renameSection(array $admin,int $sectionId,string $label): void
    {if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$label=trim($label);$length=function_exists('mb_strlen')?mb_strlen($label):strlen($label);if($label===''||$length>120)throw new RuntimeException('Enter a section name up to 120 characters.');$q=db()->prepare('SELECT section_code FROM site_page_sections WHERE site_page_section_id=?');$q->execute([$sectionId]);$code=$q->fetchColumn();if($code===false)throw new RuntimeException('Page section not found.');if((string)$code==='HERO')throw new RuntimeException('Hero sections keep their dedicated title editor.');db()->prepare('UPDATE site_page_sections SET label_text=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([$label,$admin['user_id'],$sectionId]);}

    public static function reorderSections(array $admin,string $pageCode,array $ids): void
    {if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$q=db()->prepare('SELECT s.site_page_section_id FROM site_page_sections s JOIN site_pages p ON p.site_page_id=s.site_page_id WHERE p.page_code=?');$q->execute([strtoupper($pageCode)]);$allowed=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$u=db()->prepare('UPDATE site_page_sections SET sort_order=?,updated_by_user_id=? WHERE site_page_section_id=?');$sort=10;foreach($ids as $id)if(in_array((int)$id,$allowed,true)){$u->execute([$sort,$admin['user_id'],(int)$id]);$sort+=10;}}
    public static function saveSection(array $admin,int $sectionId,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$q=db()->prepare('SELECT 1 FROM site_page_sections WHERE site_page_section_id=?');$q->execute([$sectionId]);if(!$q->fetchColumn())throw new RuntimeException('Page section not found.');
        $imageId=self::mediaChoice($admin,$input['image_media_id']??'');$layout=in_array(($input['layout_type']??''),self::LAYOUTS,true)?$input['layout_type']:'IMAGE_RIGHT';$mode=in_array(($input['background_mode']??''),['DEFAULT','COLOR','MEDIA'],true)?$input['background_mode']:'DEFAULT';$backgroundId=$mode==='MEDIA'?self::mediaChoice($admin,$input['background_media_id']??''):null;$color=self::color($input['background_color']??'#F4EEE2','background color');$fit=in_array(($input['background_fit']??''),self::BACKGROUND_FITS,true)?$input['background_fit']:'COVER';$backgroundOpacity=self::percent($input['background_opacity']??100);$overlayMode=in_array(($input['overlay_mode']??''),['NONE','COLOR','GRADIENT'],true)?$input['overlay_mode']:'NONE';$overlayStart=self::color($input['overlay_color_start']??'#1C242B','overlay color');$overlayEnd=self::color($input['overlay_color_end']??'#6B2938','gradient color');$overlayOpacity=self::percent($input['overlay_opacity']??0);
        $eyebrow=trim((string)($input['eyebrow_text']??''));$title=trim((string)($input['title_text']??''));if(mb_strlen($eyebrow)>190||mb_strlen($title)>500)throw new RuntimeException('A page title or label is too long.');$html=self::sanitizeHtml((string)($input['content_html']??''));
        $current=db()->prepare('SELECT buttons_json,rows_json FROM site_page_sections WHERE site_page_section_id=?');$current->execute([$sectionId]);$currentRow=$current->fetch(PDO::FETCH_ASSOC)?:[];$currentButtons=$currentRow['buttons_json']??null;$buttonsJson=$currentButtons;$rowsJson=$currentRow['rows_json']??null;if(array_key_exists('rows_present',$input))$rowsJson=json_encode(self::normalizeRows(is_array($input['rows']??null)?$input['rows']:[],$admin),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(array_key_exists('hero_buttons_present',$input))$buttonsJson=json_encode(self::normalizeButtons(is_array($input['hero_buttons']??null)?$input['hero_buttons']:[],true),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $columns=[];for($i=1;$i<=3;$i++)$columns[]=['title'=>mb_substr(trim((string)($input['column_'.$i.'_title']??'')),0,190),'content'=>self::sanitizeHtml((string)($input['column_'.$i.'_content']??'')),'image_media_id'=>self::mediaChoice($admin,$input['column_'.$i.'_image_media_id']??'')];
        $padding=[];foreach(['top','right','bottom','left']as$edge){$padding[$edge]=self::optionalPixels($input['padding_'.$edge.'_px']??null);$padding['mobile_'.$edge]=self::optionalPixels($input['mobile_padding_'.$edge.'_px']??null);}
        $type=[];foreach(['label','title','body']as$role){$type[$role.'_font_size']=self::optionalSize($input[$role.'_font_size']??null);$type[$role.'_color']=self::optionalColor($input[$role.'_color']??null,$role.' color');$type[$role.'_transform']=self::optionalTransform($input[$role.'_transform']??null);}
        db()->prepare('UPDATE site_page_sections SET eyebrow_text=?,title_text=?,content_html=?,buttons_json=?,rows_json=?,image_media_id=?,layout_type=?,columns_json=?,background_mode=?,background_color=?,background_media_id=?,background_fit=?,background_opacity=?,overlay_mode=?,overlay_color_start=?,overlay_color_end=?,overlay_opacity=?,padding_top_px=?,padding_right_px=?,padding_bottom_px=?,padding_left_px=?,mobile_padding_top_px=?,mobile_padding_right_px=?,mobile_padding_bottom_px=?,mobile_padding_left_px=?,label_font_size=?,label_color=?,label_transform=?,title_font_size=?,title_color=?,title_transform=?,body_font_size=?,body_color=?,body_transform=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([$eyebrow?:null,$title?:null,$html?:null,$buttonsJson,$rowsJson,$imageId,$layout,json_encode($columns,JSON_UNESCAPED_SLASHES),$mode,$mode==='COLOR'?$color:null,$backgroundId,$fit,$backgroundOpacity,$overlayMode,$overlayStart,$overlayEnd,$overlayOpacity,$padding['top'],$padding['right'],$padding['bottom'],$padding['left'],$padding['mobile_top'],$padding['mobile_right'],$padding['mobile_bottom'],$padding['mobile_left'],$type['label_font_size'],$type['label_color'],$type['label_transform'],$type['title_font_size'],$type['title_color'],$type['title_transform'],$type['body_font_size'],$type['body_color'],$type['body_transform'],$admin['user_id'],$sectionId]);
    }

    public static function addSection(array $admin,string $pageCode,array $input): int
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$label=trim((string)($input['label_text']??''));if($label===''||mb_strlen($label)>120)throw new RuntimeException('Enter a section name up to 120 characters.');$baseCode=strtoupper(trim((string)preg_replace('/[^A-Za-z0-9]+/','_',$label),'_'));if($baseCode==='')throw new RuntimeException('Enter a valid section name.');$q=db()->prepare('SELECT site_page_id FROM site_pages WHERE page_code=? AND active=1');$q->execute([strtoupper($pageCode)]);$pageId=(int)$q->fetchColumn();if(!$pageId)throw new RuntimeException('Page not found.');$code=$baseCode;$suffix=2;$exists=db()->prepare('SELECT 1 FROM site_page_sections WHERE site_page_id=? AND section_code=?');while(true){$exists->execute([$pageId,$code]);if(!$exists->fetchColumn())break;$code=$baseCode.'_'.$suffix++;}$sort=(int)db()->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM site_page_sections WHERE site_page_id='.$pageId)->fetchColumn();$layout=in_array(($input['layout_type']??''),self::LAYOUTS,true)?$input['layout_type']:'IMAGE_RIGHT';$q=db()->prepare('INSERT INTO site_page_sections(site_page_id,section_code,label_text,layout_type,sort_order,updated_by_user_id) VALUES(?,?,?,?,?,?)');$q->execute([$pageId,$code,$label,$layout,$sort,$admin['user_id']]);return(int)db()->lastInsertId();
    }

    public static function columns(array $section): array
    {
        $columns=json_decode((string)($section['columns_json']??''),true);if(!is_array($columns))return[];$q=db()->prepare('SELECT file_path,thumbnail_path,alt_text FROM writer_media_assets WHERE media_id=? AND active=1');foreach($columns as&$column){$id=(int)($column['image_media_id']??0);$column['image_path']=null;$column['alt_text']='';if($id>0){$q->execute([$id]);if($media=$q->fetch()){$column['image_path']=$media['file_path'];$column['alt_text']=$media['alt_text'];}}}unset($column);return array_slice($columns,0,3);
    }

    public static function buttons(array $section): array
    {
        if(!array_key_exists('buttons_json',$section)||$section['buttons_json']===null||trim((string)$section['buttons_json'])==='')return self::defaultButtons();
        $saved=json_decode((string)$section['buttons_json'],true);if(!is_array($saved))return[];
        $buttons=[];foreach(array_slice($saved,0,self::BUTTON_LIMIT)as$button)if(is_array($button))$buttons[]=self::normalizeButton($button,false);
        return$buttons;
    }

    public static function defaultButtons(): array
    {
        $base=['target'=>'_self','visible'=>true,'text_color'=>'#1C242B','text_hover_color'=>'#1C242B','background_color'=>'#F4EEE2','background_hover_color'=>'#FFFFFF','background_end_color'=>'#F4EEE2','background_hover_end_color'=>'#FFFFFF','border_color'=>'#1C242B','border_hover_color'=>'#6B2938','border_width'=>1,'border_radius'=>6,'padding_y'=>13,'padding_x'=>20,'font_size'=>'','font_weight'=>'','text_transform'=>'','hover_effect'=>'LIFT','background_type'=>'SOLID','width'=>'AUTO','min_width'=>150,'border_style'=>'SOLID','shadow'=>'SOFT'];
        return[array_merge($base,['label'=>'See How It Works','url'=>'?view=how_it_works','member_url'=>'']),array_merge($base,['label'=>'Join A Story','url'=>'?view=login','member_url'=>'./','background_color'=>'#B58E46','background_hover_color'=>'#D5B56B','border_color'=>'#B58E46','border_hover_color'=>'#D5B56B']),array_merge($base,['label'=>'Host A Game','url'=>'?view=register','member_url'=>'./'])];
    }

    public static function buttonStyle(array $button): string
    {
        $button=self::normalizeButton($button,false);$background=$button['background_type']==='TRANSPARENT'?'transparent':($button['background_type']==='GRADIENT'?'linear-gradient(135deg,'.$button['background_color'].','.$button['background_end_color'].')':$button['background_color']);$hoverBackground=$button['background_type']==='TRANSPARENT'?'transparent':($button['background_type']==='GRADIENT'?'linear-gradient(135deg,'.$button['background_hover_color'].','.$button['background_hover_end_color'].')':$button['background_hover_color']);$shadow=match($button['shadow']){'NONE'=>'none','STRONG'=>'0 12px 25px rgba(28,36,43,.24)',default=>'0 6px 14px rgba(28,36,43,.14)'};return implode(';',['--hero-button-color:'.$button['text_color'],'--hero-button-hover-color:'.$button['text_hover_color'],'--hero-button-background:'.$background,'--hero-button-hover-background:'.$hoverBackground,'--hero-button-border:'.$button['border_color'],'--hero-button-hover-border:'.$button['border_hover_color'],'--hero-button-border-width:'.$button['border_width'].'px','--hero-button-border-style:'.$button['border_style'],'--hero-button-radius:'.$button['border_radius'].'px','--hero-button-padding-y:'.$button['padding_y'].'px','--hero-button-padding-x:'.$button['padding_x'].'px','--hero-button-min-width:'.$button['min_width'].'px','--hero-button-shadow:'.$shadow,$button['font_size']!==''?'--hero-button-font-size:'.$button['font_size']:'',$button['font_weight']!==''?'--hero-button-font-weight:'.$button['font_weight']:'',$button['text_transform']!==''?'--hero-button-transform:'.$button['text_transform']:'']).';';
    }

    public static function socials(): array
    {
        $q=db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key="BRAND_SOCIAL_URLS"');$q->execute();$saved=json_decode((string)$q->fetchColumn(),true);$out=[];foreach(self::SOCIAL_PLATFORMS as$key=>$label)$out[$key]=['label'=>$label,'url'=>is_array($saved)?(string)($saved[$key]??''):''];return$out;
    }

    public static function saveSocials(array $admin,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');$urls=[];foreach(self::SOCIAL_PLATFORMS as$key=>$label){$url=trim((string)($input['social_'.$key]??''));if($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!str_starts_with(strtolower($url),'https://')))throw new RuntimeException($label.' needs a complete URL beginning with https://.');$urls[$key]=$url;}$q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES("BRAND_SOCIAL_URLS",?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');$q->execute([json_encode($urls,JSON_UNESCAPED_SLASHES),$admin['user_id']]);
    }

    public static function style(array $section): string
    {
        $parts=[];$mode=$section['background_mode']??'DEFAULT';if($mode==='COLOR'&&!empty($section['background_color']))$parts[]='--section-bg-color:'.$section['background_color'];if($mode==='MEDIA'&&(!empty($section['background_media_id'])||!empty($section['background_path']))){$mediaId=(int)($section['background_media_id']??0);$path=$mediaId>0?'theme-media.php?id='.$mediaId:str_replace(['"',"'",')','\\'],['','','','/'],(string)$section['background_path']);if(!preg_match('~^(?:https?:)?//~i',$path))$path='/'.ltrim($path,'/');$parts[]='--section-bg-image:url("'.$path.'")';[$size,$repeat,$position]=match($section['background_fit']??'COVER'){'CONTAIN'=>['contain','no-repeat','center'],'TILE'=>['auto','repeat','top left'],'STRETCH'=>['100% 100%','no-repeat','center'],'CENTER'=>['auto','no-repeat','center'],default=>['cover','no-repeat','center']};$parts[]='--section-bg-size:'.$size;$parts[]='--section-bg-repeat:'.$repeat;$parts[]='--section-bg-position:'.$position;$parts[]='--section-bg-opacity:'.(max(0,min(100,(int)($section['background_opacity']??100)))/100);}$overlay=$section['overlay_mode']??'NONE';$opacity=max(0,min(100,(int)($section['overlay_opacity']??0)))/100;if($overlay!=='NONE'&&$opacity>0){$start=self::rgba((string)($section['overlay_color_start']??'#1C242B'),$opacity);$end=self::rgba((string)($section['overlay_color_end']??'#6B2938'),$opacity);$parts[]='--section-overlay:'.($overlay==='GRADIENT'?'linear-gradient(135deg,'.$start.','.$end.')':$start);}
        foreach(['top','right','bottom','left']as$edge){if(isset($section['padding_'.$edge.'_px'])&&$section['padding_'.$edge.'_px']!=='')$parts[]='--section-pad-'.$edge.':'.(int)$section['padding_'.$edge.'_px'].'px';if(isset($section['mobile_padding_'.$edge.'_px'])&&$section['mobile_padding_'.$edge.'_px']!=='')$parts[]='--section-mobile-pad-'.$edge.':'.(int)$section['mobile_padding_'.$edge.'_px'].'px';}
        foreach(['label','title','body']as$role){if(!empty($section[$role.'_font_size']))$parts[]='--section-'.$role.'-size:'.$section[$role.'_font_size'];if(!empty($section[$role.'_color']))$parts[]='--section-'.$role.'-color:'.$section[$role.'_color'];if(!empty($section[$role.'_transform']))$parts[]='--section-'.$role.'-transform:'.$section[$role.'_transform'];}
        return$parts?implode(';',$parts).';':'';
    }

    private static function normalizeRows(array $rows,?array $admin=null): array{$out=[];foreach(array_slice($rows,0,12)as$index=>$row)if(is_array($row))$out[]=self::normalizeRow($row,$index,$admin);return$out;}

    private static function fallbackRow(array $section): array
    {return self::normalizeRow(['eyebrow_text'=>$section['eyebrow_text']??'','title_text'=>$section['title_text']??'','content_html'=>$section['content_html']??'','layout_type'=>$section['layout_type']??'IMAGE_RIGHT','columns'=>json_decode((string)($section['columns_json']??'[]'),true)?:[]],0);}

    private static function normalizeRow(array $row,int $index,?array $admin=null,int $depth=0): array
    {
        $layout=strtoupper(trim((string)($row['layout_type']??'TEXT_TOP')));
        if(!in_array($layout,self::LAYOUTS,true)||$layout==='HERO')$layout='TEXT_TOP';
        $depth=min(12,max(0,$depth));
        $childInput=$row['children']??[];
        if(isset($row['children_json'])){
            $decoded=json_decode((string)$row['children_json'],true);
            if(is_array($decoded))$childInput=$decoded;
        }
        $children=[];
        if($depth<12&&($layout==='CONTAINER'||isset($row['children'])||isset($row['children_json']))){
            foreach(array_slice((array)$childInput,0,12)as$childIndex=>$child){
                if(is_array($child))$children[]=self::normalizeRow($child,$childIndex,$admin,$depth+1);
            }
        }
        $media=static function($value)use($admin): ?int{
            return $admin?self::mediaChoice($admin,$value):((int)$value?:null);
        };
        $hex=static function($value): string{
            $value=strtoupper(trim((string)$value));
            return preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$value)?$value:'';
        };
        $columns=[];
        foreach(array_slice((array)($row['columns']??[]),0,6)as$column){
            if(is_array($column))$columns[]=[
                'title'=>self::textSlice(trim((string)($column['title']??'')),0,190),
                'content'=>self::sanitizeHtml((string)($column['content']??'')),
                'icon_text'=>self::textSlice(trim((string)($column['icon_text']??'')),0,120),
                'image_media_id'=>$media($column['image_media_id']??''),
                'buttons'=>self::normalizeButtons(array_slice((array)($column['buttons']??[]),0,2),false),
            ];
        }
        $buttons=self::normalizeButtons(array_slice((array)($row['buttons']??[]),0,4),false);
        $bullets=[];
        $sourceBullets=$row['bullets']??(isset($row['bullets_text'])?preg_split('/\r?\n/',(string)$row['bullets_text']):[]);
        foreach(array_slice((array)$sourceBullets,0,20)as$bullet){
            $text=is_array($bullet)?trim((string)($bullet['text']??'')):trim((string)$bullet);
            if($text!=='')$bullets[]=self::textSlice($text,0,500);
        }
        $textAlign=(string)($row['text_align']??'left');
        $blendMode=(string)($row['blend_mode']??'normal');
        $fontSize=trim((string)($row['font_size']??''));
        $fontWeight=(string)($row['font_weight']??'');
        $textTransform=(string)($row['text_transform']??'none');
        return [
            'label_text'=>self::textSlice(trim((string)($row['label_text']??'')),0,190),
            'eyebrow_text'=>self::textSlice(trim((string)($row['eyebrow_text']??'')),0,190),
            'title_text'=>self::textSlice(trim((string)($row['title_text']??'')),0,500),
            'content_html'=>self::sanitizeHtml((string)($row['content_html']??'')),
            'layout_type'=>$layout,
            'active'=>!isset($row['active'])||!empty($row['active']),
            'image_media_id'=>$media($row['image_media_id']??''),
            'background_media_id'=>$media($row['background_media_id']??''),
            'background_color'=>$hex($row['background_color']??''),
            'text_color'=>$hex($row['text_color']??''),
            'text_align'=>in_array($textAlign,['left','center','right'],true)?$textAlign:'left',
            'blend_mode'=>in_array($blendMode,['normal','multiply','screen','overlay','darken','lighten','difference'],true)?$blendMode:'normal',
            'font_size'=>preg_match('/^(?:10|1[1-9]|2[0-9]|3[0-6])px$/',$fontSize)?$fontSize:'',
            'font_weight'=>in_array($fontWeight,['400','500','600','700','800'],true)?$fontWeight:'',
            'text_transform'=>in_array($textTransform,['none','uppercase','capitalize','lowercase'],true)?$textTransform:'none',
            'padding_top_px'=>max(0,min(2000,(int)($row['padding_top_px']??0))),
            'padding_right_px'=>max(0,min(2000,(int)($row['padding_right_px']??0))),
            'padding_bottom_px'=>max(0,min(2000,(int)($row['padding_bottom_px']??0))),
            'padding_left_px'=>max(0,min(2000,(int)($row['padding_left_px']??0))),
            'bullets'=>$bullets,
            'columns'=>$columns,
            'buttons'=>$buttons,
            'sort_order'=>$index,
            'children'=>$children,
        ];
    }
    private static function mediaChoice(array $admin,$value): ?int
    {
        $id=(int)$value;if($id<1)return null;MediaLibraryService::requireVisible($admin,$id);return$id;
    }

    private static function color($value,string $label): string{$color=strtoupper(trim((string)$value));if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$color))throw new RuntimeException('Choose a valid '.$label.'.');return$color;}
    private static function percent($value): int{return max(0,min(100,(int)$value));}
    private static function optionalPixels($value): ?int{$value=trim((string)$value);if($value==='')return null;if(!preg_match('/^\d{1,3}$/',$value)||(int)$value>400)throw new RuntimeException('Section padding must be between 0 and 400 pixels.');return(int)$value;}
    private static function optionalSize($value): ?string{$value=trim((string)$value);if($value==='')return null;if(preg_match('/^(?:0|[1-9][0-9]{0,3})(?:\.[0-9]+)?$/',$value))$value.='px';if(mb_strlen($value)>120||!preg_match('/^(?:(?:0|[1-9][0-9]{0,3})(?:\.[0-9]+)?(?:px|rem|em|%|vw|vh|vmin|vmax|ch|ex|pt)|(?:clamp|min|max|calc)\([0-9a-z.%+\-*\/,\s()]+\))$/i',$value))throw new RuntimeException('Typography override sizes must use a supported CSS size or responsive size function.');return$value;}
    private static function optionalColor($value,string $label): ?string{$value=trim((string)$value);return$value===''?null:self::color($value,$label);}
    private static function optionalTransform($value): ?string{$value=trim((string)$value);if($value==='')return null;if(!in_array($value,self::TEXT_TRANSFORMS,true))throw new RuntimeException('Choose a supported text transform.');return$value;}
    private static function normalizeButtons(array $buttons,bool $strict): array
    {
        if(count($buttons)>self::BUTTON_LIMIT)throw new RuntimeException('Hero buttons are limited to '.self::BUTTON_LIMIT.'.');$out=[];foreach($buttons as$button){if(!is_array($button))continue;$normalized=self::normalizeButton($button,$strict);if($normalized['label']==='')continue;if($strict&&$normalized['visible']&&$normalized['url']==='')throw new RuntimeException('Every visible Hero button needs a link.');$out[]=$normalized;}return$out;
    }

    private static function normalizeButton(array $input,bool $strict): array
    {
        $defaults=self::defaultButton();$text=static function($value,string $fallback,int $limit)use($strict): string{$value=trim((string)$value);if(self::textLength($value)>$limit){if($strict)throw new RuntimeException('Hero button text is too long.');return self::textSlice($value,0,$limit);}return$value===''?$fallback:$value;};$link=static function($value,string $fallback)use($strict): string{$value=trim((string)$value);if($value===''||self::textLength($value)>500||!preg_match('~^(?:https?://|mailto:|/|\?|#)~i',$value)){if($strict&&$value!=='')throw new RuntimeException('Hero button links must use a relative path, # anchor, https:// URL, or mailto: URL.');return$value===''?'':$fallback;}return$value;};$color=static function($value,string $fallback)use($strict): string{$value=strtoupper(trim((string)$value));if(!preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$value)){if($strict)throw new RuntimeException('Hero button colors must use #RRGGBB or #RRGGBBAA.');return$fallback;}return$value;};$enum=static function($value,array$allowed,string$fallback)use($strict): string{$value=strtoupper(trim((string)$value));if(!in_array($value,$allowed,true)){if($strict)throw new RuntimeException('A Hero button setting is invalid.');return$fallback;}return$value;};$number=static function($value,int$min,int$max,int$fallback): int{$value=filter_var($value,FILTER_VALIDATE_INT);return$value===false?$fallback:max($min,min($max,$value));};$out=$defaults;$out['label']=$text($input['label']??'',$defaults['label'],90);$out['url']=$link($input['url']??'',$defaults['url']);$out['member_url']=$link($input['member_url']??'','');$target=strtolower(trim((string)($input['target']??$defaults['target'])));if(!in_array($target,['_self','_blank'],true)){if($strict)throw new RuntimeException('Hero button target is invalid.');$target=$defaults['target'];}$out['target']=$target;$out['visible']=!empty($input['visible']);foreach(['text_color','text_hover_color','background_color','background_hover_color','background_end_color','background_hover_end_color','border_color','border_hover_color']as$key)$out[$key]=$color($input[$key]??$defaults[$key],$defaults[$key]);$out['border_width']=$number($input['border_width']??$defaults['border_width'],0,12,$defaults['border_width']);$out['border_radius']=$number($input['border_radius']??$defaults['border_radius'],0,60,$defaults['border_radius']);$out['padding_y']=$number($input['padding_y']??$defaults['padding_y'],0,60,$defaults['padding_y']);$out['padding_x']=$number($input['padding_x']??$defaults['padding_x'],0,100,$defaults['padding_x']);$out['min_width']=$number($input['min_width']??$defaults['min_width'],0,600,$defaults['min_width']);$out['font_size']=self::buttonSize($input['font_size']??'',$strict);$out['font_weight']=$enum($input['font_weight']??'',['','400','500','600','700','800'],$defaults['font_weight']);$out['text_transform']=$enum($input['text_transform']??'',['','NONE','UPPERCASE','CAPITALIZE','LOWERCASE'],$defaults['text_transform']);$out['hover_effect']=$enum($input['hover_effect']??$defaults['hover_effect'],self::BUTTON_HOVER_EFFECTS,$defaults['hover_effect']);$out['background_type']=$enum($input['background_type']??$defaults['background_type'],self::BUTTON_BACKGROUND_TYPES,$defaults['background_type']);$out['width']=$enum($input['width']??$defaults['width'],self::BUTTON_WIDTHS,$defaults['width']);$out['border_style']=$enum($input['border_style']??$defaults['border_style'],['SOLID','DASHED','NONE'],$defaults['border_style']);$out['shadow']=$enum($input['shadow']??$defaults['shadow'],['NONE','SOFT','STRONG'],$defaults['shadow']);return$out;
    }

    private static function defaultButton(): array{return['label'=>'Button','url'=>'#','member_url'=>'','target'=>'_self','visible'=>true,'text_color'=>'#1C242B','text_hover_color'=>'#1C242B','background_color'=>'#F4EEE2','background_hover_color'=>'#FFFFFF','background_end_color'=>'#F4EEE2','background_hover_end_color'=>'#FFFFFF','border_color'=>'#1C242B','border_hover_color'=>'#6B2938','border_width'=>1,'border_radius'=>6,'padding_y'=>13,'padding_x'=>20,'font_size'=>'','font_weight'=>'','text_transform'=>'','hover_effect'=>'LIFT','background_type'=>'SOLID','width'=>'AUTO','min_width'=>150,'border_style'=>'SOLID','shadow'=>'SOFT'];}

    private static function buttonSize($value,bool $strict): string{$value=trim((string)$value);if($value===''||preg_match('/^(?:10|1[1-9]|2[0-9]|3[0-6])px$/',$value))return$value;if($strict)throw new RuntimeException('Hero button font size must be blank or between 10px and 36px.');return'';}
    private static function textLength(string $value): int{return function_exists('mb_strlen')?mb_strlen($value):strlen($value);}
    private static function textSlice(string $value,int $start,int $length): string{return function_exists('mb_substr')?mb_substr($value,$start,$length):substr($value,$start,$length);}
    private static function rgba(string $hex,float $opacity): string
    {
        $hex=ltrim(strtoupper($hex),'#');
        $colorOpacity=strlen($hex)===8?hexdec(substr($hex,6,2))/255:1;
        $opacity=max(0,min(1,$opacity*$colorOpacity));
        return'rgba('.hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2)).','.$opacity.')';
    }

    private static function sanitizeHtml(string $html): string
    {
        $html=strip_tags($html,'<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><blockquote><a>');$html=preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i',static function(array $match):string{$tag=strtolower($match[1]);$closing=str_starts_with($match[0],'</');if($closing)return'</'.$tag.'>';if($tag==='br')return'<br>';if($tag!=='a')return'<'.$tag.'>';$href='';if(preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i',$match[0],$found))$href=trim($found[2]);if($href!==''&&!preg_match('~^(?:https?://|mailto:|/|\?|#)~i',$href))$href='';return$href===''?'<a>':'<a href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'">';},$html);return trim((string)$html);
    }
}

