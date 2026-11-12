<?php
require_once __DIR__.'/Logger.php';
final class AiEndpointPolicy
{
    public static function config(): array
    {
        $path=dirname(__DIR__).'/config/ai.php';$config=is_file($path)?require $path:[];
        return is_array($config)?$config:[];
    }
    public static function allowedHosts(): array
    {
        $cfg=self::config();$path=dirname(__DIR__).'/config/ai-policy.php';$policy=is_file($path)?require $path:[];if(!is_array($policy))$policy=[];
        $hosts=$cfg['allowed_hosts']??$policy['allowed_hosts']??[];
        return is_array($hosts)?array_values(array_unique(array_map(static fn($host)=>strtolower(trim((string)$host)),$hosts))):[];
    }
    public static function configuredEndpoint(string $databaseFallback=''): string
    {
        $env=trim((string)getenv('GAME2_AI_ENDPOINT'));$cfg=self::config();
        return $env!==''?$env:trim((string)($cfg['endpoint']??$databaseFallback));
    }

    /** Parse and approve an origin before any DNS lookup or credential handling. */
    public static function validateUrl(string $url,?array $allowed=null): array
    {
        $allowed??=self::allowedHosts();$url=trim($url);
        if($url==='')throw new RuntimeException('AI service is not configured.');
        if(preg_match('/[\x00-\x20\x7f\\\\]/',$url)||!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('AI endpoint is invalid.');
        $parts=parse_url($url);$host=strtolower((string)($parts['host']??''));
        if(strtolower((string)($parts['scheme']??''))!=='https')throw new RuntimeException('AI endpoint must use HTTPS.');
        if(isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||(int)($parts['port']??443)!==443)throw new RuntimeException('AI endpoint must use HTTPS port 443 without credentials, query or fragment.');
        if(!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])$/D',$host)||filter_var($host,FILTER_VALIDATE_IP))throw new RuntimeException('AI endpoint must use an approved hostname.');
        if(!in_array($host,$allowed,true))throw new RuntimeException('AI endpoint host is not approved.');
        $path=rtrim((string)($parts['path']??''),'/');
        return ['url'=>'https://'.$host.$path,'host'=>$host,'port'=>443];
    }

    public static function resolve(string $url): array
    {
        $target=self::validateUrl($url);$records=@dns_get_record($target['host'],DNS_A|DNS_AAAA);$addresses=[];
        foreach(is_array($records)?$records:[] as $record){if(isset($record['ip']))$addresses[]=$record['ip'];if(isset($record['ipv6']))$addresses[]=$record['ipv6'];}
        $target['addresses']=self::publicAddresses($addresses);return $target;
    }

    public static function publicAddresses(array $addresses): array
    {
        if(!$addresses)throw new RuntimeException('AI host could not be resolved.');
        $public=[];foreach($addresses as $ip){
            if(!is_string($ip)||!filter_var($ip,FILTER_VALIDATE_IP))throw new RuntimeException('AI host resolved to an invalid address.');
            $packed=inet_pton($ip);$v4=strlen($packed)===4;
            $blocked=$v4?['0.0.0.0/8','10.0.0.0/8','100.64.0.0/10','127.0.0.0/8','169.254.0.0/16','172.16.0.0/12','192.0.0.0/24','192.0.2.0/24','192.88.99.0/24','192.168.0.0/16','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4']:['2001::/23','2001:db8::/32','2002::/16','3fff::/20'];
            if(!$v4&&!self::inCidr($packed,'2000::/3'))throw new RuntimeException('AI host resolved to a non-public address.');
            foreach($blocked as $cidr)if(self::inCidr($packed,$cidr))throw new RuntimeException('AI host resolved to a non-public address.');
            $public[]=inet_ntop($packed);
        }
        return array_values(array_unique($public));
    }

    /** Pin the validated addresses so cURL cannot perform a second, different lookup. */
    public static function curlOptions(array $target): array
    {
        $url=self::validateUrl((string)($target['url']??''));$addresses=self::publicAddresses((array)($target['addresses']??[]));
        $resolve=$url['host'].':443:'.implode(',',array_map(static fn($ip)=>str_contains($ip,':')?'['.$ip.']':$ip,$addresses));
        $options=[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_MAXREDIRS=>0,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$resolve],CURLOPT_PROXY=>''];
        // Use the modern API when available, with the older HTTPS-only bitmask as compatibility.
        if(defined('CURLOPT_PROTOCOLS_STR'))$options[constant('CURLOPT_PROTOCOLS_STR')]='https';else $options[CURLOPT_PROTOCOLS]=CURLPROTO_HTTPS;
        if(defined('CURLOPT_REDIR_PROTOCOLS_STR'))$options[constant('CURLOPT_REDIR_PROTOCOLS_STR')]='https';else $options[CURLOPT_REDIR_PROTOCOLS]=CURLPROTO_HTTPS;
        return $options;
    }
    private static function inCidr(string $packed,string $cidr): bool
    {
        [$base,$bits]=explode('/',$cidr);$network=inet_pton($base);if(strlen($packed)!==strlen($network))return false;
        $bits=(int)$bits;$bytes=intdiv($bits,8);$rest=$bits%8;
        if(substr($packed,0,$bytes)!==substr($network,0,$bytes))return false;
        return $rest===0||((ord($packed[$bytes])&(0xff<<(8-$rest)))===(ord($network[$bytes])&(0xff<<(8-$rest))));
    }
}
