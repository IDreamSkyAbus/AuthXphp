<?php
/**
 * AuthXphp 统一响应工具
 *
 * 所有 API 响应均通过 Response 输出，确保格式一致：
 *   {
 *     code: 0,
 *     msg: 'ok',
 *     data: ...,
 *     request_id: '...',
 *     ts: 1700000000
 *   }
 */

require_once __DIR__ . '/../config/index.php';

class Response
{
    /**
     * 全局标志 key：用于标识当前是否处于 set_exception_handler 回调上下文中。
     * 由 index.php 的异常处理器置位，Response::json() 据此决定是否调用 exit。
     * 在异常处理上下文内不调用 exit，以便异常处理器自行控制后续输出。
     */
    public const IN_EXCEPTION_HANDLER = '__authxphp_in_exception_handler';

    /**
     * 输出并终止
     */
    public static function json(int $code, string $msg, $data = null, int $httpStatus = 200): void
    {
        if (!headers_sent()) {
            http_response_code($httpStatus);
            header('Content-Type: application/json; charset=utf-8');
            header('X-AuthXphp-Version: ' . (config('app.version') ?? '1.0.0'));
        }
        $payload = [
            'code'       => $code,
            'msg'        => $msg,
            'data'       => $data,
            'request_id' => self::requestId(),
            'ts'         => time(),
        ];
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // 异常处理上下文内不退出，由异常处理器自行决定后续流程
        if (empty($GLOBALS[self::IN_EXCEPTION_HANDLER] ?? false)) {
            exit;
        }
    }

    public static function ok($data = null, string $msg = 'ok'): void
    {
        self::json(0, $msg, $data, 200);
    }

    public static function fail(int $code, string $msg, $data = null): void
    {
        $httpStatus = config('response.http_status.' . $code, 400);
        if (is_int($code) && $code >= 50000) {
            $httpStatus = 500;
        }
        self::json($code, $msg, $data, $httpStatus);
    }

    public static function badRequest(string $msg = '参数错误', int $code = 40000): void
    {
        self::fail($code, $msg);
    }

    public static function unauthorized(string $msg = '未登录或登录已过期', int $code = 40100): void
    {
        self::fail($code, $msg, null, 401);
    }

    public static function forbidden(string $msg = '无权限', int $code = 40300): void
    {
        self::fail($code, $msg, null, 403);
    }

    public static function notFound(string $msg = '资源不存在'): void
    {
        self::fail(40400, $msg, null, 404);
    }

    public static function rateLimited(string $msg = '请求过于频繁，请稍后再试'): void
    {
        self::fail(42900, $msg, null, 429);
    }

    public static function serverError(string $msg = '服务器内部错误'): void
    {
        self::fail(50000, $msg, null, 500);
    }

    public static function notInstalled(string $msg = '系统未安装'): void
    {
        self::fail(50300, $msg, null, 503);
    }

    /**
     * 重定向（用于后台页面）
     */
    public static function redirect(string $url, int $code = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $code);
        }
        exit;
    }

    /**
     * 渲染 HTML 页
     */
    public static function html(string $html, int $httpStatus = 200): void
    {
        if (!headers_sent()) {
            http_response_code($httpStatus);
            header('Content-Type: text/html; charset=utf-8');
        }
        echo $html;
        exit;
    }

    /**
     * 获取 request_id（与日志联动）
     *
     * 优先使用客户端传入的 X-Request-ID 头（用于链路追踪）。
     * 若未传入，则在本次请求内生成并缓存一份。
     *
     * 注意：不使用 static 缓存全局值，因为 PHP-FPM 长驻进程中
     * static 变量会跨请求残留，导致不同请求共享同一个 request_id。
     */
    public static function requestId(): string
    {
        // 每请求重新评估客户端传入的 ID
        if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            return $_SERVER['HTTP_X_REQUEST_ID'];
        }
        // 本次请求内缓存（static 在每个请求中初始化为 null，请求结束后重置）
        static $rid = null;
        if ($rid !== null) {
            return $rid;
        }
        return $rid = bin2hex(random_bytes(8));
    }
}
