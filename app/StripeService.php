<?php
final class StripeService
{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const API_VERSION = '2024-06-20';

    public static function configured(): bool
    {
        return self::secretKey() !== '';
    }

    public static function mode(): string
    {
        $key = self::secretKey();
        if (str_starts_with($key, 'sk_live_')) return 'LIVE';
        if (str_starts_with($key, 'sk_test_')) return 'TEST';
        return 'NOT SET';
    }

    public static function publishableKey(): string
    {
        return self::setting('STRIPE_PUBLISHABLE_KEY');
    }

    public static function secretKey(): string
    {
        return self::setting('STRIPE_SECRET_KEY');
    }

    public static function webhookSecret(): string
    {
        return self::setting('STRIPE_WEBHOOK_SECRET');
    }

    private static function setting(string $key): string
    {
        try {
            $q = db()->prepare('SELECT setting_value FROM platform_settings WHERE setting_key=?');
            $q->execute([$key]);
            $v = $q->fetchColumn();
            return $v === false ? '' : trim((string)$v);
        } catch (Throwable $e) {
            return '';
        }
    }

    public static function verifySignature(string $payload, string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) continue;
            if ($kv[0] === 't') $timestamp = $kv[1];
            if ($kv[0] === 'v1') $signatures[] = $kv[1];
        }
        if ($timestamp === null || $signatures === [] || !is_numeric($timestamp)) return false;
        if (abs(time() - (int)$timestamp) > $toleranceSeconds) return false;
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $sig) if (hash_equals($expected, $sig)) return true;
        return false;
    }

    public static function createCheckoutSession(array $params, string $idempotencyKey): array
    {
        return self::request('POST', '/checkout/sessions', $params, $idempotencyKey);
    }

    public static function retrieveCheckoutSession(string $id): array
    {
        return self::request('GET', '/checkout/sessions/' . urlencode($id), [], null);
    }

    private static function request(string $method, string $path, array $params, ?string $idempotencyKey): array
    {
        $secret = self::secretKey();
        if ($secret === '') throw new RuntimeException('Stripe is not configured yet. Add the Stripe keys in Commerce settings.');
        if (!function_exists('curl_init')) throw new RuntimeException('The server cannot reach Stripe (cURL is unavailable).');
        $ch = curl_init(self::API_BASE . $path);
        $headers = ['Authorization: Bearer ' . $secret, 'Stripe-Version: ' . self::API_VERSION];
        if ($idempotencyKey !== null && $idempotencyKey !== '') $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Could not reach Stripe: ' . $err);
        }
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $data = json_decode((string)$raw, true);
        if (!is_array($data)) throw new RuntimeException('Stripe returned an unreadable response.');
        if ($code < 200 || $code >= 300 || isset($data['error'])) throw new RuntimeException('Stripe error: ' . (string)($data['error']['message'] ?? ('HTTP ' . $code)));
        return $data;
    }
}
