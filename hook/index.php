<?php
/**
 * AuthXphp 事件钩子
 *
 * 用法：
 *   Hook::on('login.success', function ($payload) { ... });
 *   Hook::trigger('login.success', ['uid' => 1, 'guard' => 'app']);
 *
 * 事件清单见下方常量。
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../log/index.php';

class Hook
{
    const EVENT_BEFORE_LOGIN           = 'before.login';
    const EVENT_LOGIN_SUCCESS          = 'login.success';
    const EVENT_LOGIN_FAILED           = 'login.failed';
    const EVENT_GUARD_ATTEMPT_DISABLED = 'guard.attempt.disabled';
    const EVENT_LOGOUT                 = 'logout';
    const EVENT_TOKEN_VERIFIED         = 'token.verified';
    const EVENT_TOKEN_EXPIRED          = 'token.expired';
    const EVENT_TOKEN_REVOKED          = 'token.revoked';
    const EVENT_REGISTER_SUCCESS       = 'register.success';
    const EVENT_PASSWORD_CHANGED       = 'password.changed';

    /** @var array<string, array<int, array{0:callable,1:int}>> */
    private static $listeners = [];

    /**
     * 注册事件回调
     */
    public static function on(string $event, callable $callback, int $priority = 10): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        self::$listeners[$event][] = [$callback, $priority];
        usort(self::$listeners[$event], function ($a, $b) {
            return $b[1] - $a[1];
        });
    }

    /**
     * 注销事件
     */
    public static function off(string $event, ?callable $callback = null): void
    {
        if (!isset(self::$listeners[$event])) {
            return;
        }
        if ($callback === null) {
            unset(self::$listeners[$event]);
            return;
        }
        foreach (self::$listeners[$event] as $i => $row) {
            if ($row[0] === $callback) {
                array_splice(self::$listeners[$event], $i, 1);
            }
        }
    }

    /**
     * 触发事件
     */
    public static function trigger(string $event, array $payload = []): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }
        $payload['_event'] = $event;
        $payload['_ts']    = time();
        foreach (self::$listeners[$event] as $row) {
            try {
                $row[0]($payload);
            } catch (\Throwable $e) {
                Log::error('钩子回调异常：' . $event, [
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile(),
                    'line'  => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * 已注册的所有事件名
     */
    public static function events(): array
    {
        return array_keys(self::$listeners);
    }

    /**
     * 返回某事件回调数量
     */
    public static function count(string $event): int
    {
        return isset(self::$listeners[$event]) ? count(self::$listeners[$event]) : 0;
    }
}
