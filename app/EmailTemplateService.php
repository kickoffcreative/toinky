<?php
declare(strict_types=1);

final class EmailTemplateService
{
    private const KEYS=['WELCOME','INVITATION','PASSWORD_RESET','GAME_INVITATION','EMAIL_CHANGE','TWO_FACTOR_CODE'];
    private const TOKENS=['recipient_name','site_name','site_url','logo_url','action_url','action_label','message','message_html','sender_name','story_title','game_code','expiry_text','brand_primary','brand_secondary','brand_accent','brand_surface','brand_text','brand_heading','brand_title_font','brand_body_font'];

    public static function all(): array
    {
        self::ensureDefaults();
        $order=implode(',',array_map(static fn(string $key): string=>"'{$key}'",self::KEYS));
        $q=db()->query('SELECT * FROM email_templates ORDER BY FIELD(template_key,'.$order.')');
        return$q->fetchAll();
    }

    public static function mailSettings(): array
    {
        $out=['MAIL_FROM_ADDRESS'=>'accounts@toinky.com','MAIL_FROM_NAME'=>'Toinky'];
        try{$q=db()->query('SELECT setting_key,setting_value FROM platform_settings WHERE setting_key IN("MAIL_FROM_ADDRESS","MAIL_FROM_NAME")');foreach($q->fetchAll()as$row)$out[$row['setting_key']]=(string)$row['setting_value'];}catch(Throwable$ignored){}
        return$out;
    }

    public static function save(array $admin,array $input): void
    {
        if((int)($admin['is_admin']??0)!==1)throw new RuntimeException('Administrator access is required.');
        $from=strtolower(trim((string)($input['mail_from_address']??'')));$name=trim((string)($input['mail_from_name']??''));
        if(!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid sender email address.');
        if($name===''||self::length($name)>120||preg_match('/[\r\n]/',$name))throw new RuntimeException('Enter a sender name up to 120 characters.');
        $q=db()->prepare('INSERT INTO platform_settings(setting_key,setting_value,updated_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)');
        $q->execute(['MAIL_FROM_ADDRESS',$from,$admin['user_id']]);$q->execute(['MAIL_FROM_NAME',$name,$admin['user_id']]);
        self::ensureDefaults();
        $update=db()->prepare('UPDATE email_templates SET subject_template=?,html_template=?,text_template=?,css_template=?,enabled=?,updated_by_user_id=? WHERE template_key=?');
        foreach(self::defaults()as$key=>$default){$subject=trim((string)($input['email_'.$key.'_subject']??$default['subject_template']));$html=self::sanitizeHtmlTemplate((string)($input['email_'.$key.'_html']??$default['html_template']));$text=trim((string)($input['email_'.$key.'_text']??$default['text_template']));$css=self::sanitizeCss((string)($input['email_'.$key.'_css']??$default['css_template']));$enabled=!empty($input['email_'.$key.'_enabled'])?1:0;if($subject===''||self::length($subject)>255)throw new RuntimeException($default['label_text'].' needs a subject up to 255 characters.');if($text===''||self::length($text)>30000)throw new RuntimeException($default['label_text'].' needs plain-text content up to 30,000 characters.');if($html===''||self::length($html)>60000)throw new RuntimeException($default['label_text'].' needs HTML content up to 60,000 characters.');self::assertTokens($subject.' '.$html.' '.$text.' '.$css);$update->execute([$subject,$html,$text,$css,$enabled,$admin['user_id'],$key]);}
    }

    public static function compose(string $key,array $data): array
    {
        self::ensureDefaults();$key=strtoupper(trim($key));if(!in_array($key,self::KEYS,true))throw new RuntimeException('Unknown email template.');
        $q=db()->prepare('SELECT * FROM email_templates WHERE template_key=?');$q->execute([$key]);$template=$q->fetch();if(!$template||!(int)$template['enabled'])throw new RuntimeException('The '.$key.' email is disabled.');
        $base=['recipient_name'=>'there','site_name'=>'Toinky','site_url'=>self::baseUrl(),'logo_url'=>rtrim(self::baseUrl(),'/').'/assets/toinky-logo.png','action_url'=>'','action_label'=>'Open Toinky','message'=>'','message_html'=>'','sender_name'=>'Toinky','story_title'=>'','game_code'=>'','expiry_text'=>'','brand_primary'=>'#1C242B','brand_secondary'=>'#6B2938','brand_accent'=>'#B58E46','brand_surface'=>'#F4EEE2','brand_text'=>'#35424B','brand_heading'=>'#1C242B','brand_title_font'=>'Cormorant Garamond, Georgia, serif','brand_body_font'=>'Instrument Sans, Arial, sans-serif'];$values=array_merge($base,$data);$values['message_html']=(string)($values['message_html']??'');if($values['message_html']===''&&$values['message']!=='')$values['message_html']=self::textToHtml((string)$values['message']);$subject=self::replace((string)$template['subject_template'],$values,false);$values['subject_line']=$subject;$css=self::replace((string)$template['css_template'],$values,false);return['subject'=>$subject,'html'=>'<style>'.$css.'</style>'.self::replace((string)$template['html_template'],$values,true),'text'=>self::replace((string)$template['text_template'],$values,false)];
    }

    public static function defaultTemplate(string $key): array
    {
        $defaults=self::defaults();$key=strtoupper(trim($key));return$defaults[$key]??[];
    }

    private static function ensureDefaults(): void
    {
        $q=db()->prepare('INSERT IGNORE INTO email_templates(template_key,label_text,subject_template,html_template,text_template,css_template) VALUES(?,?,?,?,?,?)');foreach(self::defaults()as$key=>$row)$q->execute([$key,$row['label_text'],$row['subject_template'],$row['html_template'],$row['text_template'],$row['css_template']]);
    }

    private static function defaults(): array
    {
        $css='body{margin:0;background:#F4EEE2;color:#35424B;font-family:{{brand_body_font}}}.email-shell{width:100%;padding:28px 12px;background:#F4EEE2}.email-card{max-width:620px;margin:0 auto;background:#fff;border-top:5px solid {{brand_accent}};border-radius:8px;overflow:hidden}.email-header{padding:22px 28px;background:{{brand_primary}}}.email-logo{display:block;width:150px;height:auto}.email-content{padding:30px 28px}.email-content h1{margin:0 0 16px;color:{{brand_heading}};font:600 30px/1.05 {{brand_title_font}}}.email-content p{font:16px/1.55 {{brand_body_font}}}.email-button{display:inline-block;margin:12px 0;padding:12px 18px;background:{{brand_accent}};color:{{brand_primary}}!important;text-decoration:none;font-weight:700;border-radius:5px}.email-footer{padding:18px 28px;background:{{brand_surface}};color:#66715C;font-size:12px}.email-footer a{color:{{brand_secondary}}}';
        $html=static fn(string $title,string $button): string=>'<div class="email-shell"><div class="email-card"><div class="email-header"><img class="email-logo" src="{{logo_url}}" alt="{{site_name}}"></div><div class="email-content"><h1>'.$title.'</h1><p>Hello {{recipient_name}},</p><div class="email-message">{{message_html}}</div><p><a class="email-button" href="{{action_url}}">'.$button.'</a></p><p class="email-expiry">{{expiry_text}}</p></div><div class="email-footer">You are receiving this message from {{site_name}}. <a href="{{site_url}}">Visit {{site_name}}</a>.</div></div></div>';
        $text=static fn(string $title,string $button): string=>$title."\n\nHello {{recipient_name}},\n\n{{message}}\n\n".$button.': {{action_url}}' . "\n{{expiry_text}}\n\n{{site_name}} · {{site_url}}";
        return['WELCOME'=>['label_text'=>'Welcome Email','subject_template'=>'Welcome to {{site_name}}','html_template'=>$html('Welcome to {{site_name}}','Sign in to Toinky'),'text_template'=>$text('Welcome to {{site_name}}','Sign in to Toinky'),'css_template'=>$css],'INVITATION'=>['label_text'=>'Account Invitation','subject_template'=>'You are invited to {{site_name}}','html_template'=>$html('You are invited to {{site_name}}','Create your account'),'text_template'=>$text('You are invited to {{site_name}}','Create your account'),'css_template'=>$css],'PASSWORD_RESET'=>['label_text'=>'Password Reset','subject_template'=>'Reset your {{site_name}} password','html_template'=>$html('Reset your password','Reset your password'),'text_template'=>$text('Reset your password','Reset your password'),'css_template'=>$css],'GAME_INVITATION'=>['label_text'=>'Game Invitation','subject_template'=>'You are invited to a {{site_name}} story','html_template'=>$html('You are invited to a story','Join the story'),'text_template'=>$text('You are invited to a story','Join the story'),'css_template'=>$css],'EMAIL_CHANGE'=>['label_text'=>'Email Change Verification','subject_template'=>'Verify your new {{site_name}} email','html_template'=>$html('Verify your new email','Verify email address'),'text_template'=>$text('Verify your new email','Verify email address'),'css_template'=>$css],'TWO_FACTOR_CODE'=>['label_text'=>'Sign-in Code','subject_template'=>'Your {{site_name}} sign-in code','html_template'=>$html('Your sign-in code','Sign in to Toinky'),'text_template'=>$text('Your sign-in code','Sign in to Toinky'),'css_template'=>$css]];
    }

    private static function replace(string $template,array $values,bool $html): string
    {
        return preg_replace_callback('/\{\{([a-z0-9_]+)\}\}/i',static function(array $match)use($values,$html): string{$value=(string)($values[$match[1]]??'');return$html&&$match[1]==='message_html'?$value:htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');},$template)??$template;
    }

    private static function assertTokens(string $value): void
    {
        preg_match_all('/\{\{([a-z0-9_]+)\}\}/i',$value,$matches);foreach($matches[1]??[]as$token)if(!in_array(strtolower($token),self::TOKENS,true))throw new RuntimeException('Unknown email template key: {{'.$token.'}}.');
    }

    private static function sanitizeHtmlTemplate(string $html): string
    {
        $html=str_replace(["\0",'<!--[if','<![endif]'],['','',''],$html);$html=strip_tags($html,'<a><br><p><div><span><strong><b><em><i><u><h1><h2><h3><ul><ol><li><table><tbody><thead><tfoot><tr><td><th><img>');$html=preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??$html;$html=preg_replace('/(?:javascript|vbscript|data):/i','',$html)??$html;$html=preg_replace_callback('/<([a-z0-9]+)([^>]*)>/i',static function(array$match): string{$tag=strtolower($match[1]);$attrs=$match[2];$out='';if(preg_match('/\b(href|src)\s*=\s*(["\'])(.*?)\2/i',$attrs,$found)){$url=trim($found[3]);if(!preg_match('~^(?:https?://|mailto:|/|#|\{\{[a-z0-9_]+\}\})~i',$url))$url='';if($url!=='')$out.=' '.$found[1].'="'.htmlspecialchars($url,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';}if(preg_match('/\balt\s*=\s*(["\'])(.*?)\1/i',$attrs,$found))$out.=' alt="'.htmlspecialchars($found[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';if(preg_match('/\bclass\s*=\s*(["\'])([a-z0-9_ -]+)\1/i',$attrs,$found))$out.=' class="'.htmlspecialchars($found[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';if(preg_match('/\bstyle\s*=\s*(["\'])(.*?)\1/i',$attrs,$found)){$style=preg_replace('/(?:url|expression|behavior)\s*\([^)]*\)/i','',$found[2])??'';$out.=' style="'.htmlspecialchars($style,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';}return'<'.$tag.$out.'>';},$html)??$html;return trim($html);
    }

    private static function sanitizeCss(string $css): string
    {
        $css=preg_replace('/<\/?style[^>]*>|@import|(?:url|expression|behavior)\s*\([^)]*\)/i','',$css)??$css;return trim(substr($css,0,20000));
    }

    private static function textToHtml(string $text): string{return nl2br(htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),false);}
    private static function length(string $value): int{return function_exists('mb_strlen')?mb_strlen($value):strlen($value);}
    private static function baseUrl(): string
    {
        $env=rtrim(trim((string)getenv('GAME2_BASE_URL')),'/');if($env!=='')return$env;$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';$host=(string)($_SERVER['HTTP_HOST']??'toinky.com');return($secure?'https':'http').'://'.$host;
    }
}
