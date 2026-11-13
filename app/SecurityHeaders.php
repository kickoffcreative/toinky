<?php
final class SecurityHeaders
{
    private static ?string $nonce=null;
    private static bool $sent=false;
    private static bool $preview=false;

    public static function nonce(): string
    {
        return self::$nonce??=base64_encode(random_bytes(24));
    }
    public static function enforced(): bool
    {
        return strtolower(trim((string)getenv('GAME2_CSP_MODE')))==='enforce';
    }
    public static function baseline(): string
    {
        return "frame-ancestors ".(self::$preview?"'self'":"'none'")."; base-uri 'self'; object-src 'none'; form-action 'self' https://checkout.stripe.com";
    }
    public static function policy(): string
    {
        // Trusted script tags carry an authored nonce. Never nonce arbitrary response HTML.
        // strict-dynamic preserves the narrator's intentional dynamic module imports;
        // wasm-unsafe-eval permits its runtime, without permitting JavaScript eval().
        return self::baseline()."; default-src 'self'; script-src 'self' 'nonce-".self::nonce()."' 'strict-dynamic' 'wasm-unsafe-eval'; script-src-attr 'none'; "
            ."style-src 'self' 'nonce-".self::nonce()."'; style-src-attr 'unsafe-inline'; font-src 'self'; "
            ."img-src 'self' data: blob:; media-src 'self' data: blob:; worker-src 'self' blob:; "
            ."connect-src 'self' https://cdn.jsdelivr.net https://huggingface.co https://*.huggingface.co https://*.hf.co; frame-src 'self'";
    }
    public static function send(): void
    {
        if(PHP_SAPI==='cli'||self::$sent||headers_sent())return;
        self::$sent=true;
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: '.(self::$preview?'SAMEORIGIN':'DENY'));
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header('Content-Security-Policy: '.(self::enforced()?self::policy():self::baseline()));
        if(!self::enforced())header('Content-Security-Policy-Report-Only: '.self::policy());
    }
    public static function allowSameOriginPreview(): void
    {
        self::$preview=true;self::$sent=false;self::send();self::noStore();
    }
    public static function noStore(): void
    {
        if(PHP_SAPI!=='cli'&&!headers_sent())header('Cache-Control: no-store');
    }
}
