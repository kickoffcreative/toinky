<?php
// Task 20: thin structured logger. One error_log call site; every line
// carries a level, a timestamp, the per-request correlation ID (from
// bootstrap.php) and optional context. Use Logger::info()/warning()/error()
// for new logs. The 11 pre-existing ad-hoc error_log() calls are left as
// they are in this change; migrate them to Logger when their call sites
// are next touched.
final class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $cid = function_exists('correlationId') ? correlationId() : '';
        $line = '[' . gmdate('c') . '] Toinky [' . strtoupper($level) . '] [cid=' . $cid . '] ' . $message;
        if ($context) $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log($line);
    }

    public static function info(string $message, array $context = []): void { self::log('info', $message, $context); }
    public static function warning(string $message, array $context = []): void { self::log('warning', $message, $context); }
    public static function error(string $message, array $context = []): void { self::log('error', $message, $context); }
}
