<?php
require_once __DIR__.'/Logger.php';
final class AppUrlService
{
    public static function baseUrl(): string
    {
        return self::settings()['base_url'];
    }

    private static function settings(): array
    {
        $path=trim((string)getenv('GAME2_APP_CONFIG'));
        if($path==='')$path=dirname(__DIR__).'/config/app.php';
        $config=is_file($path)?require $path:[];
        if(!is_array($config))throw new RuntimeException('Invalid site address configuration.');
        $override=trim((string)getenv('GAME2_BASE_URL'));
        $base=$override!==''?$override:trim((string)($config['base_url']??''));
        $parts=parse_url($base);
        if($base===''||strlen($base)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$base)||!is_array($parts)
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])
            ||!isset($parts['scheme'],$parts['host'])||!self::validHost(strtolower($parts['host']))
            ||(isset($parts['port'])&&($parts['port']<1||$parts['port']>65535)))throw new RuntimeException('Invalid site address configuration.');
        $host=strtolower($parts['host']);$scheme=strtolower($parts['scheme']);$loopback=in_array($host,['localhost','127.0.0.1','[::1]'],true);
        if($scheme!=='https'&&!($scheme==='http'&&$loopback&&$override!==''))throw new RuntimeException('The configured site address must use HTTPS.');
        $pathPart=$parts['path']??'';
        if(str_contains($pathPart,'//')||preg_match('~(?:^|/)(?:\.|\.\.)(?:/|$)|%(?:0[0-9a-f]|1[0-9a-f]|7f|2e|2f|5c)|%(?![0-9a-f]{2})~i',$pathPart))throw new RuntimeException('Invalid site address path.');
        $allowed=$config['allowed_hosts']??[];
        if(!is_array($allowed))throw new RuntimeException('Invalid allowed site hosts.');
        $hosts=[$host];
        foreach($allowed as $entry){if(!is_string($entry)||!self::validHost(strtolower($entry)))throw new RuntimeException('Invalid allowed site hosts.');$hosts[]=strtolower($entry);}
        // An explicit local URL isolates local development/tests from production aliases.
        if($loopback&&$override!=='')$hosts=[$host];
        return ['base_url'=>rtrim($scheme.'://'.$host.(isset($parts['port'])?':'.$parts['port']:'').$pathPart,'/'),'hosts'=>array_unique($hosts),'port'=>$parts['port']??($scheme==='https'?443:80),'local_http'=>$loopback&&$scheme==='http'];
    }

    private static function validHost(string $host): bool
    {
        if($host==='[::1]')return true;
        return $host!==''&&!str_ends_with($host,'.')&&(bool)filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME);
    }

    /** The sole request-host reader. No sessions or database access precede it. */
    public static function guardHost(): void
    {
        try{$settings=self::settings();}
        catch(Throwable $e){Logger::exception($e,'Site address configuration unavailable');http_response_code(503);header('Cache-Control: no-store');exit('The site is temporarily unavailable.');}
        $raw=(string)($_SERVER['HTTP_HOST']??'');
        $match=[];$valid=preg_match('/^(\[[0-9a-f:]+\]|[a-z0-9.-]+)(?::([0-9]{1,5}))?$/iD',$raw,$match)===1;
        $host=strtolower($match[1]??'');$port=isset($match[2])?(int)$match[2]:null;
        $valid=$valid&&in_array($host,$settings['hosts'],true)&&($port===null||($port>0&&$port<=65535&&($settings['local_http']||$port===$settings['port'])));
        if(!$valid){Logger::warning('Rejected request host');http_response_code(400);header('Cache-Control: no-store');exit('Invalid request host.');}
    }
}
