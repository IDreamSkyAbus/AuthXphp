<?php
/**
 * AuthXphp 统一入口
 *
 * 负责：
 *   1. 引导（配置、错误处理、CORS）
 *   2. 路由分发（API / Admin SPA）
 *   3. 中间件链执行
 *
 * 嵌入式部署时，所有未匹配到真实文件的请求都会经由此处处理。
 */

declare(strict_types=1);

// ============================================================================
// 引导
// ============================================================================
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// 加载配置中心
require __DIR__ . '/config/index.php';

// 错误处理：未捕获异常统一返回 JSON
set_exception_handler(function (\Throwable $e) {
    // 标记进入异常处理上下文，使 Response::json() 不再调用 exit，
    // 以便异常处理器完整输出 JSON 响应。
    $GLOBALS['__authxphp_in_exception_handler'] = true;
    try {
        require_once __DIR__ . '/log/index.php';
        \Log::error('未捕获异常：' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    } catch (\Throwable $ignored) {
        // 忽略日志自身错误
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'code'       => 50000,
        'msg'        => config('app.debug') ? $e->getMessage() : '服务器内部错误',
        'data'       => null,
        'request_id' => bin2hex(random_bytes(8)),
        'ts'         => time(),
    ], JSON_UNESCAPED_UNICODE);
});

// ============================================================================
// 安装检测
// ============================================================================
$lockFile = __DIR__ . '/install/install.lock';
$reqPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isApi    = strpos($reqPath, '/api/') === 0 || $reqPath === '/api';

// 如果是 /install/ 路径前缀，直接放行（让 install 目录自己处理）
$isInstallPath = strpos($reqPath, '/install/') === 0 || $reqPath === '/install' || $reqPath === '/install/';

if (!is_file($lockFile) && !$isInstallPath) {
    if ($isApi) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code' => 50300,
            'msg'  => '系统尚未安装，请先访问 /install/ 完成安装',
            'data' => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // 浏览器请求 → 跳转到安装向导
    $base = rtrim(config('app.base_path') ?: '', '/');
    header('Location: ' . ($base ? $base : '') . '/install/');
    exit;
}

// ============================================================================
// 加载运行时所需组件
// ============================================================================
require_once __DIR__ . '/response/index.php';
require_once __DIR__ . '/log/index.php';
require_once __DIR__ . '/db/index.php';
require_once __DIR__ . '/token/index.php';
require_once __DIR__ . '/guard/index.php';
require_once __DIR__ . '/hook/index.php';
require_once __DIR__ . '/auth/index.php';
require_once __DIR__ . '/route/index.php';
require_once __DIR__ . '/middleware/index.php';
require_once __DIR__ . '/ratelimit/index.php';
require_once __DIR__ . '/captcha/index.php';
require_once __DIR__ . '/api/index.php';

// ============================================================================
// 调度
// ============================================================================
try {
    Middleware::run(['Cors'], $_SERVER, function () {
        // /api/* 走 API
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($path, '/api/') === 0 || $path === '/api') {
            Route::dispatchApi();
            return;
        }
        // /admin 走 Admin SPA（用 iframe 加载子页面）
        if ($path === '/admin' || $path === '/admin/' || $path === '/admin/index.php') {
            require __DIR__ . '/admin/index.php';
            return;
        }
        // 其它未知路径
        Response::notFound('接口不存在：' . $path);
    });
} catch (\Throwable $e) {
    Log::error('入口异常', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'code' => 50000,
        'msg'  => config('app.debug') ? $e->getMessage() : '服务器内部错误',
        'data' => null,
    ], JSON_UNESCAPED_UNICODE);
}
