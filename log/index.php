<?php
/**
 * AuthXphp 文件日志
 *
 * 按天轮转，存储于 storage/logs/YYYY-MM-DD.log
 * 格式：[时间][级别][request_id] msg | ctx_json
 */

require_once __DIR__ . '/../config/index.php';

class Log
{
    private static $initialized = false;

    private static function init(): void
    {
        if (self::$initialized) {
            return;
        }
        if (!is_dir(AUTHXPHP_LOG_PATH)) {
            @mkdir(AUTHXPHP_LOG_PATH, 0755, true);
        }
        self::$initialized = true;
    }

    public static function write(string $level, string $msg, array $ctx = []): void
    {
        self::init();
        $file = AUTHXPHP_LOG_PATH . '/' . date('Y-m-d') . '.log';
        $rid  = $_SERVER['HTTP_X_REQUEST_ID'] ?? Response::requestId();
        $line = sprintf(
            "[%s][%s][%s] %s | %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $rid,
            $msg,
            $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}'
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $msg, array $ctx = []): void
    {
        self::write('info', $msg, $ctx);
    }

    public static function warn(string $msg, array $ctx = []): void
    {
        self::write('warn', $msg, $ctx);
    }

    public static function error(string $msg, array $ctx = []): void
    {
        self::write('error', $msg, $ctx);
    }

    public static function debug(string $msg, array $ctx = []): void
    {
        if (config('app.debug')) {
            self::write('debug', $msg, $ctx);
        }
    }
}
