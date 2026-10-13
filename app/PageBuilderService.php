<?php
final class PageBuilderService
{
    public const MAX_DEPTH=12;

    public static function saveRows(array $admin,int $sectionId,array $rows): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $q=db()->prepare('SELECT section_code FROM site_page_sections WHERE site_page_section_id=?');
        $q->execute([$sectionId]);
        $code=$q->fetchColumn();
        if($code===false)throw new RuntimeException('Page section not found.');
        if((string)$code==='HERO')throw new RuntimeException('Hero sections use their dedicated editor.');
        $normalized=self::normalizeRows($rows);
        db()->prepare('UPDATE site_page_sections SET rows_json=?,updated_by_user_id=? WHERE site_page_section_id=?')->execute([
            json_encode($normalized,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            $admin['user_id'],
            $sectionId,
        ]);
    }

    public static function normalizeRows(array $rows,int $depth=0): array
    {
        if($depth>self::MAX_DEPTH)return[];
        $out=[];
        foreach(array_values($rows)as$row){
            if(!is_array($row))continue;
            $layout=strtoupper(trim((string)($row['layout_type']??'TEXT_TOP')));
            if(!in_array($layout,PageContentService::LAYOUTS,true)||$layout==='HERO')$layout='TEXT_TOP';
            $item=[
                'layout_type'=>$layout,
                'active'=>self::boolValue($row['active']??true),
            ];
            if($layout==='CONTAINER'){
                $item['style_name']=self::text($row['style_name']??'',80);
                $item['spacing_preset']=self::spacingPreset($row['spacing_preset']??'');
                $item['background_color']=self::optionalColor($row['background_color']??'');
                $item['background_media_id']=self::mediaId($row['background_media_id']??null);
                $item['background_fit']=self::backgroundFit($row['background_fit']??'FILL');
                $item['content_width']=self::contentWidth($row['content_width']??'FULL');
                $item['max_width_px']=self::maxWidth($row['max_width_px']??1980);
                $item['min_height_px']=self::pixels($row['min_height_px']??0);
                $item['items_direction']=self::itemsDirection($row['items_direction']??'COLUMN');
                $item['align_items']=self::alignItems($row['align_items']??'stretch');
                $item['wrap_items']=self::boolValue($row['wrap_items']??true);
                $blendMode=(string)($row['blend_mode']??'normal');
                $item['blend_mode']=in_array($blendMode,['normal','multiply','screen','overlay','darken','lighten','difference'],true)?$blendMode:'normal';
                $item['align_self']=self::alignSelf($row['align_self']??'stretch');
                self::applySpacing($item,$row,'margin');
                self::applySpacing($item,$row,'padding');
                self::applyResponsive($item,$row);
                if($depth===0)$item['style_library']=self::styleLibrary($row['style_library']??[]);
                $item['children']=self::normalizeRows(is_array($row['children']??null)?$row['children']:[],$depth+1);
                $out[]=$item;
                continue;
            }
            $item['style_name']=self::text($row['style_name']??'',80);
            $item['spacing_preset']=self::spacingPreset($row['spacing_preset']??'');
            foreach(['label_text','eyebrow_text','title_text']as$key)$item[$key]=self::text($row[$key]??'',500);
            $item['content_html']=self::html($row['content_html']??'');
            $item['bullets']=self::listValues($row['bullets']??[]);
            $item['image_media_id']=self::mediaId($row['image_media_id']??null);
            $item['image_width_px']=self::imagePixels($row['image_width_px']??0);
            $item['image_height_px']=self::imagePixels($row['image_height_px']??0);
            $item['tablet_image_width_px']=self::imagePixels($row['tablet_image_width_px']??0);
            $item['tablet_image_height_px']=self::imagePixels($row['tablet_image_height_px']??0);
            $item['mobile_image_width_px']=self::imagePixels($row['mobile_image_width_px']??0);
            $item['mobile_image_height_px']=self::imagePixels($row['mobile_image_height_px']??0);
            $item['background_media_id']=self::mediaId($row['background_media_id']??null);
            $item['background_color']=self::optionalColor($row['background_color']??'');
            $item['background_fit']=self::backgroundFit($row['background_fit']??'FILL');
            $item['text_color']=self::optionalColor($row['text_color']??'');
            $textAlign=(string)($row['text_align']??'left');
            $item['text_align']=in_array($textAlign,['left','center','right'],true)?$textAlign:'left';
            $blendMode=(string)($row['blend_mode']??'normal');
            $item['blend_mode']=in_array($blendMode,['normal','multiply','screen','overlay','darken','lighten','difference'],true)?$blendMode:'normal';
            $item['align_self']=self::alignSelf($row['align_self']??'stretch');
            self::applySpacing($item,$row,'margin');
            self::applySpacing($item,$row,'padding');
            self::applyResponsive($item,$row);
            $item['column_background_color']=self::optionalColor($row['column_background_color']??'');
            $item['column_background_media_id']=self::mediaId($row['column_background_media_id']??null);
            $item['column_background_fit']=self::backgroundFit($row['column_background_fit']??'FILL');
            self::applySpacing($item,$row,'column_margin',10);
            self::applySpacing($item,$row,'column_padding');
            if(is_array($row['columns']??null)){
                $item['columns']=[];
                foreach(array_slice($row['columns'],0,6)as$column){
                    if(!is_array($column))$column=[];
                    $columnItem=[
                        'icon_text'=>self::text($column['icon_text']??'',120),
                        'icon_size_px'=>self::pixels($column['icon_size_px']??32),
                        'icon_color'=>self::optionalColor($column['icon_color']??'#B58E46'),
                        'title'=>self::text($column['title']??'',190),
                        'content'=>self::html($column['content']??''),
                        'background_color'=>self::optionalColor($column['background_color']??''),
                        'background_media_id'=>self::mediaId($column['background_media_id']??null),
                        'background_fit'=>self::backgroundFit($column['background_fit']??'FILL'),
                    ];
                    self::applySpacing($columnItem,$column,'margin',10);
                    self::applySpacing($columnItem,$column,'padding');
                    $item['columns'][]=$columnItem;
                }
            }
            $out[]=$item;
        }
        return$out;
    }

    private static function text(mixed$value,int$limit): string
    {
        $value=trim((string)$value);
        return function_exists('mb_substr')?mb_substr($value,0,$limit):substr($value,0,$limit);
    }

    private static function html(mixed$value): string
    {
        $html=strip_tags((string)$value,'<p><br><strong><em><u><h2><h3><ul><ol><li><a>');
        $html=(string)preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/isu','',$html);
        $html=(string)preg_replace('/\s+style\s*=\s*(["\']).*?\1/isu','',$html);
        $html=(string)preg_replace_callback('/<\/?([a-z0-9]+)(?:\s+[^>]*)?>/i',static function(array $match): string{
            $tag=strtolower($match[1]);
            if(str_starts_with($match[0],'</'))return '</'.$tag.'>';
            if($tag==='br')return '<br>';
            if($tag!=='a')return '<'.$tag.'>';
            $href='';
            if(preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i',$match[0],$found))$href=trim($found[2]);
            if($href!==''&&!preg_match('~^(?:https?://|mailto:|/|\?|#)~i',$href))$href='';
            $cls='';if(preg_match('/\\bclass\\s*=\\s*([\"\'])(.*?)\\1/i',$match[0],$cfound)){$_c=array_intersect(preg_split('/\\s+/',trim($cfound[2])),['btn','hero-button']);if($_c)$cls=' class="'.htmlspecialchars(implode(' ',$_c),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';}return $href===''?'<a'.$cls.'>':'<a href="'.htmlspecialchars($href,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"'.$cls.'>';
        },$html);
        return function_exists('mb_substr')?mb_substr($html,0,20000):substr($html,0,20000);
    }

    private static function listValues(mixed$value): array
    {
        if(is_string($value))$value=preg_split('/\r?\n/',$value);
        if(!is_array($value))return[];
        $out=[];
        foreach($value as$item){$item=self::text($item,500);if($item!=='')$out[]=$item;}
        return array_slice($out,0,30);
    }

    private static function pixels(mixed$value): int
    {
        return max(0,min(2000,(int)$value));
    }

    private static function imagePixels(mixed$value): int
    {
        return max(0,min(3000,(int)$value));
    }

    private static function boolValue(mixed$value): bool
    {
        if(is_bool($value))return$value;
        $value=strtolower(trim((string)$value));
        return !in_array($value,['','0','false','off','no'],true);
    }

    private static function applySpacing(array&$item,array$row,string$prefix,int$defaultAll=0): void
    {
        $mode=strtoupper(trim((string)($row[$prefix.'_mode']??'ALL')));
        $item[$prefix.'_mode']=$mode==='ALL'?'ALL':'SIDES';
        $item[$prefix.'_all_px']=self::pixels($row[$prefix.'_all_px']??$defaultAll);
        foreach(['top','right','bottom','left']as$edge)$item[$prefix.'_'.$edge.'_px']=self::pixels($row[$prefix.'_'.$edge.'_px']??$defaultAll);
    }

    private static function applyResponsive(array&$item,array$row): void
    {
        foreach(['tablet','mobile']as$device){
            foreach(['padding','margin']as$type)$item[$device.'_'.$type.'_all_px']=self::pixels($row[$device.'_'.$type.'_all_px']??0);
            $item[$device.'_font_size']=self::fontSize($row[$device.'_font_size']??'');
            $item[$device.'_columns']=self::columnsCount($row[$device.'_columns']??0);
        }
    }

    private static function spacingPreset(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return in_array($value,['TIGHT','NORMAL','SPACIOUS'],true)?$value:'';
    }

    private static function fontSize(mixed$value): string
    {
        $value=trim((string)$value);
        if($value==='')return'';
        if(preg_match('/^(?:0|[1-9][0-9]{0,2})(?:\.[0-9]+)?$/',$value))$value.='px';
        return preg_match('/^(?:[8-9]|[1-7][0-9]|80)(?:\.[0-9]+)?px$/',$value)?$value:'';
    }

    private static function columnsCount(mixed$value): int
    {
        $value=(int)$value;
        return $value>=1&&$value<=4?$value:0;
    }

    private static function styleLibrary(mixed$value): array
    {
        if(!is_array($value))return[];
        $out=[];
        foreach(array_slice($value,0,30)as$style){
            if(!is_array($style))continue;
            $name=self::text($style['name']??'',80);
            $values=is_array($style['values']??null)?$style['values']:[];
            if($name==='')continue;
            $out[]=['name'=>$name,'values'=>$values];
        }
        return$out;
    }

    private static function color(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return preg_match('/^#[0-9A-F]{6}$/',$value)?$value:'#F4EEE2';
    }

    private static function optionalColor(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return preg_match('/^#[0-9A-F]{6}([0-9A-F]{2})?$/',$value)?$value:'';
    }

    private static function backgroundFit(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return in_array($value,['FILL','FIT','TILE'],true)?$value:'FILL';
    }

    private static function contentWidth(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return in_array($value,['FULL','MAX'],true)?$value:'FULL';
    }

    private static function maxWidth(mixed$value): int
    {
        return max(320,min(3840,(int)$value?:1980));
    }

    private static function itemsDirection(mixed$value): string
    {
        $value=strtoupper(trim((string)$value));
        return $value==='ROW'?'ROW':'COLUMN';
    }

    private static function alignItems(mixed$value): string
    {
        $value=trim((string)$value);
        return in_array($value,['flex-start','center','flex-end','stretch'],true)?$value:'stretch';
    }

    private static function alignSelf(mixed$value): string
    {
        $value=trim((string)$value);
        return in_array($value,['auto','flex-start','center','flex-end','stretch'],true)?$value:'stretch';
    }

    private static function mediaId(mixed$value): ?int
    {
        $id=(int)$value;
        if($id<1)return null;
        static $allowed=null;
        if($allowed===null)$allowed=array_fill_keys(array_map('intval',db()->query('SELECT media_id FROM writer_media_assets WHERE active=1')->fetchAll(PDO::FETCH_COLUMN)),true);
        return isset($allowed[$id])?$id:null;
    }
}
