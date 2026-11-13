<?php
// Task 20: thin structured logger. One error_log call site; every line
// carries a level, a timestamp, the per-request correlation ID (from
// bootstrap.php) and optional context. Use Logger::info()/warning()/error()
// for new logs. Exception diagnostics deliberately exclude raw messages and
// arguments, which can contain SQL values, credentials or provider bodies.
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

    public static function exception(Throwable $exception,string $message='Operation failed',array $context=[]): void
    {
        $frames=[];
        foreach(array_slice($exception->getTrace(),0,12) as $frame)$frames[]=[
            'file'=>basename((string)($frame['file']??'')),
            'line'=>(int)($frame['line']??0),
            'call'=>(string)($frame['class']??'').(string)($frame['type']??'').(string)($frame['function']??''),
        ];
        $diagnostic=['exception'=>get_class($exception),'code'=>(string)$exception->getCode(),
            'file'=>basename($exception->getFile()),'line'=>$exception->getLine(),'trace'=>$frames];
        if($exception instanceof PDOException&&is_array($exception->errorInfo??null)){
            $diagnostic['sqlstate']=(string)($exception->errorInfo[0]??'');
            $diagnostic['driver_code']=(int)($exception->errorInfo[1]??0);
        }
        // Never add getMessage(), errorInfo[2], getTraceAsString(), request
        // bodies or getPrevious() messages to this diagnostic record.
        self::error($message,array_merge($context,$diagnostic));
    }
}
