<?php
/**
 * AuthXphp 中间件
 *
 * 用法（在 index.php 入口）：
 *   Middleware::run(['Cors'], $_SERVER, function() { Route::dispatchApi(); });
 *
 * 内置：
 *   - Cors      跨域 / 预检
 *   - AuthGuard 通用 API 鉴权
 *   - Rbac      角色 / 权限
 *   - RateLimit 频率限制
 *   - Install   未安装拦截
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../response/index.php';

class Middleware
{
    /**
     * 执行中间件链
     *
     * @param string[] $names
     * @param array    $request
     * @param callable $terminal  最终业务
     */
    public static function run(array $names, array $request, callable $terminal): void
    {
        $chain = self::build($names);
        $i = 0;
        $next = function () use (&$i, &$chain, &$next, $terminal) {
            if ($i >= count($chain)) {
                $terminal();
                return;
            }
            $mw = $chain[$i++];
            $mw($request, $next);
        };
        $next();
    }

    /**
     * @param string[] $names
     * @return callable[]
     */
    private static function build(array $names): array
    {
        $list = [];
        foreach ($names as $n) {
            $fn = [self::class, 'mw_' . strtolower($n)];
            if (is_callable($fn)) {
                $list[] = $fn;
            }
        }
        return $list;
    }

    // ======================== 各中间件实现 ========================

    public static function mw_cors(array $req, callable $next): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = (array)(config('app.cors_origins') ?: ['*']);
        $allowCredentials = (bool)(config('app.cors_allow_credentials') ?? false);

        $allowOrigin = '';
        if (in_array('*', $allowed, true)) {
            // 通配模式：若允许 credentials 则回显请求 origin（浏览器要求），否则直接返回 *
            $allowOrigin = $allowCredentials && $origin !== '' ? $origin : '*';
        } elseif ($origin !== '' && self::originAllowed($origin, $allowed)) {
            $allowOrigin = $origin;
        }

        if ($allowOrigin === '') {
            // 不在白名单：不输出 ACAO 头，浏览器将阻止跨域读取
            // 仍继续 next() 以保证同源请求不受影响
        } else {
            if (!headers_sent()) {
                header('Access-Control-Allow-Origin: ' . $allowOrigin);
                header('Vary: Origin');
                if ($allowCredentials) {
                    header('Access-Control-Allow-Credentials: true');
                }
                header('Access-Control-Allow-Methods: ' . (config('app.cors_allow_methods') ?: 'GET, POST, PUT, DELETE, OPTIONS'));
                header('Access-Control-Allow-Headers: ' . (config('app.cors_allow_headers') ?: 'Content-Type, Authorization, X-Requested-With, X-Request-Id'));
                header('Access-Control-Expose-Headers: X-AuthXphp-Version');
                header('Access-Control-Max-Age: 86400');
            }
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            // 预检：不在白名单则直接 403，避免被探测
            if ($allowOrigin === '') {
                http_response_code(403);
                exit;
            }
            http_response_code(204);
            exit;
        }
        $next();
    }

    /**
     * 校验 Origin 是否在白名单
     * 支持精确匹配和 *.example.com 通配符
     */
    private static function originAllowed(string $origin, array $allowed): bool
    {
        foreach ($allowed as $rule) {
            $rule = trim((string)$rule);
            if ($rule === '') {
                continue;
            }
            if ($rule === $origin) {
                return true;
            }
            if (strpos($rule, '*.') === 0) {
                $suffix = substr($rule, 1); // ".example.com"
                if (strrpos($origin, $suffix) !== false
                    && stripos($origin, 'http' . $suffix) !== false) {
                    // 仅匹配协议+通配域，避免 *.com 误中所有 .com
                    $scheme = (stripos($origin, 'https://') === 0) ? 'https://' : 'http://';
                    $hostPart = substr($origin, strlen($scheme));
                    if (strrpos($hostPart, $suffix) === strlen($hostPart) - strlen($suffix)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function mw_install(array $req, callable $next): void
    {
        $lock = AUTHXPHP_PATH . '/install/install.lock';
        if (!is_file($lock)) {
            Response::notInstalled();
        }
        $next();
    }

    public static function mw_authguard(array $req, callable $next): void
    {
        $token = Route::bearerToken();
        if (!$token) {
            Response::unauthorized();
        }
        try {
            $r = Jwt::verify($token);
        } catch (TokenExpiredException $e) {
            Response::unauthorized($e->getMessage(), 40101);
        } catch (TokenRevokedException $e) {
            Response::unauthorized($e->getMessage(), 40103);
        } catch (TokenInvalidException $e) {
            Response::unauthorized($e->getMessage(), 40102);
        } catch (\Throwable $e) {
            Response::unauthorized('Token校验失败');
        }
        $data = $r['data'];
        $g    = Guard::driver($data['guard']);
        $user = $g->byId($data['uid']);
        if (!$user) {
            Response::unauthorized('用户不存在');
        }
        Auth::setCurrent($user, $data['guard'], $r['jti'], $r['payload']);
        $next();
    }

    public static function mw_rbac(array $req, callable $next): void
    {
        // AuthGuard 应当已经在 mw_authguard 之后；这里做角色 / 权限网关检查需要配合路由级 meta
        // 此中间件做"全局必须登录即可"语义；细粒度在 Route::dispatchApi 中处理
        if (!Auth::check()) {
            Response::unauthorized();
        }
        $next();
    }

    public static function mw_ratelimit(array $req, callable $next): void
    {
        $ip = Route::clientIp();
        $ok = RateLimiter::hit('global:' . $ip, 600, 60);
        if (!$ok) {
            Response::rateLimited('请求过于频繁（全局限流）');
        }
        $next();
    }
}
