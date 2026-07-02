<?php
/**
 * AuthXphp 路由
 *
 * 用法：
 *   Route::get('/api/login',  'Api::login');
 *   Route::post('/api/login', 'Api::login', ['rate_limit'=>['max'=>10,'window'=>60]]);
 *   Route::group('/api', function () { ... });
 *
 * 元数据 meta 用于中间件：
 *   - guard:        期望的 guard 名称（与 token 中 guard 字段比对）
 *   - roles:        允许的角色列表
 *   - permissions:  必需的权限列表
 *   - rate_limit:   ['key'=>'login','max'=>10,'window'=>60]
 *   - captcha:      true 表示需要先过图形验证码
 *   - public:       true 标记为完全公开
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../response/index.php';
require_once __DIR__ . '/../auth/index.php';

class Route
{
    /** @var array<int, array{method:string,pattern:string,regex:string,params:array,handler:string,meta:array}> */
    private static $routes = [];
    private static $prefix = '';
    private static $globalMeta = [];

    /**
     * 设置全局元数据
     */
    public static function setGlobalMeta(array $meta): void
    {
        self::$globalMeta = $meta;
    }

    public static function get(string $path, $handler, array $meta = []): void
    {
        self::add('GET', $path, $handler, $meta);
    }
    public static function post(string $path, $handler, array $meta = []): void
    {
        self::add('POST', $path, $handler, $meta);
    }
    public static function put(string $path, $handler, array $meta = []): void
    {
        self::add('PUT', $path, $handler, $meta);
    }
    public static function delete(string $path, $handler, array $meta = []): void
    {
        self::add('DELETE', $path, $handler, $meta);
    }
    public static function any(string $path, $handler, array $meta = []): void
    {
        self::add('*', $path, $handler, $meta);
    }

    /**
     * 注册路由
     */
    private static function add(string $method, string $path, $handler, array $meta): void
    {
        $path = '/' . ltrim(self::$prefix . $path, '/');
        $regex = self::compile($path);
        $params = self::paramsOf($path);
        $mergedMeta = array_merge(self::$globalMeta, $meta);
        self::$routes[] = [
            'method'  => $method,
            'pattern' => $path,
            'regex'   => $regex,
            'params'  => $params,
            'handler' => $handler,
            'meta'    => $mergedMeta,
        ];
    }

    public static function group(string $prefix, callable $cb, array $meta = []): void
    {
        $oldPrefix = self::$prefix;
        $oldMeta   = self::$globalMeta;
        self::$prefix .= $prefix;
        self::$globalMeta = array_merge($oldMeta, $meta);
        try {
            $cb();
        } finally {
            self::$prefix = $oldPrefix;
            self::$globalMeta = $oldMeta;
        }
    }

    /**
     * 编译路径到正则
     * 支持 {id} 占位
     */
    private static function compile(string $path): string
    {
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) {
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);
        return '#^' . rtrim($regex, '/') . '/?$#';
    }

    private static function paramsOf(string $path): array
    {
        preg_match_all('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', $path, $m);
        return $m[1] ?? [];
    }

    /**
     * 调度 API 请求（入口 /index.php 命中 /api/* 时调用）
     */
    public static function dispatchApi(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        foreach (self::$routes as $r) {
            if ($r['method'] !== '*' && $r['method'] !== $method) {
                continue;
            }
            if (preg_match($r['regex'], $uri, $m)) {
                // 提取 path 参数
                $args = [];
                foreach ($r['params'] as $p) {
                    $args[$p] = $m[$p] ?? null;
                }
                self::runRoute($r, $args);
                return;
            }
        }
        Response::notFound('接口不存在：' . $method . ' ' . $uri);
    }

    /**
     * 执行命中路由：中间件 → 处理器
     */
    private static function runRoute(array $r, array $args): void
    {
        $meta = $r['meta'];

        // 1. 频率限制
        if (!empty($meta['rate_limit'])) {
            $rl = $meta['rate_limit'];
            $ip = self::clientIp();
            $key = ($rl['key'] ?? 'api') . ':' . $ip;
            $ok = RateLimiter::hit($key, (int)($rl['max'] ?? 60), (int)($rl['window'] ?? 60));
            if (!$ok) {
                Response::rateLimited();
            }
        }

        // 2. 图形验证码
        if (!empty($meta['captcha'])) {
            $body = self::jsonBody();
            if (empty($body['captcha_key']) || empty($body['captcha_code'])) {
                Response::badRequest('请先完成图形验证码', 40001);
            }
            if (!Captcha::verify((string)$body['captcha_key'], (string)$body['captcha_code'])) {
                Response::badRequest('验证码错误或已过期', 40003);
            }
        }

        // 3. 鉴权
        if (empty($meta['public'])) {
            $token = self::bearerToken();
            if (!$token) {
                Response::unauthorized('缺少 Token，请先登录');
            }
            $expectedGuard = $meta['guard'] ?? null;
            try {
                $verifyResult = Jwt::verify($token, $expectedGuard);
            } catch (TokenExpiredException $e) {
                Response::unauthorized($e->getMessage(), 40101);
            } catch (TokenGuardMismatchException $e) {
                Response::unauthorized($e->getMessage(), 40104);
            } catch (TokenRevokedException $e) {
                Response::unauthorized($e->getMessage(), 40103);
            } catch (TokenInvalidException $e) {
                Response::unauthorized($e->getMessage(), 40102);
            }
            $data = $verifyResult['data'];
            // 加载最新用户记录
            $g = Guard::driver($data['guard']);
            $user = $g->byId($data['uid']);
            if (!$user) {
                Response::unauthorized('用户不存在或已被删除');
            }
            Auth::setCurrent($user, $data['guard'], $verifyResult['jti'], $verifyResult['payload']);

            // 4. 角色
            if (!empty($meta['roles'])) {
                if (!Auth::hasRole($meta['roles'])) {
                    Response::forbidden('需要角色：' . implode(',', (array)$meta['roles']), 40301);
                }
            }
            // 5. 权限
            if (!empty($meta['permissions'])) {
                if (!Auth::can($meta['permissions'])) {
                    Response::forbidden('缺少权限：' . implode(',', (array)$meta['permissions']), 40302);
                }
            }
        }

        // 调用处理器
        $handler = $r['handler'];
        if (is_callable($handler)) {
            $handler($args);
            return;
        }
        if (is_string($handler) && strpos($handler, '::') !== false) {
            [$class, $method] = explode('::', $handler, 2);
            if (class_exists($class) && method_exists($class, $method)) {
                (new $class())->$method($args);
                return;
            }
        }
        if (is_string($handler) && function_exists($handler)) {
            $handler($args);
            return;
        }
        Response::serverError('路由处理器不存在：' . (is_string($handler) ? $handler : 'closure'));
    }

    /**
     * 取得当前请求的 Bearer Token
     *
     * 安全：仅接受 HTTP Authorization 头部，不支持 URL 参数传递。
     * URL 参数会导致 Token 出现在日志、浏览器历史、Referer 等地方，存在泄露风险。
     */
    public static function bearerToken(): ?string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$h && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $h = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        if (!$h) {
            $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $h, $m)) {
            return trim($m[1]);
        }
        // 已移除 $_GET['token'] / $_POST['token'] 支持（安全风险）
        return null;
    }

    /**
     * 解析 JSON Body
     *
     *  安全：JSON body 优先级高于 $_GET / $_POST，防止 URL 参数劫持。
     *  无 static 缓存：避免 PHP-FPM 长驻进程中跨请求数据残留。
     */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        // JSON body 优先级最高：先放 $data，再被 $_POST / $_GET 覆盖会破坏这一保证。
        // 因此顺序必须为 array_merge($data, $_POST, $_GET)，使 $data 在合并结果中胜出。
        return array_merge($data, $_POST, $_GET);
    }

    /**
     * 取参便捷方法
     */
    public static function input(string $key, $default = null, ?string $rule = null)
    {
        $body = self::jsonBody();
        $val  = $body[$key] ?? $default;
        if ($rule === 'trim') {
            return is_string($val) ? trim($val) : $val;
        }
        return $val;
    }

    /**
     * 客户端 IP
     *
     * 安全策略：
     *  - 默认仅信任 REMOTE_ADDR（直接对端连接 IP）。
     *  - 只有当 REMOTE_ADDR 命中 trusted_proxies 白名单（支持 IPv4 / IPv4 CIDR / 单 IP）时，
     *    才解析 X-Forwarded-For / X-Real-IP / CF-Connecting-IP / Client-IP。
     *  - 解析 X-Forwarded-For 时取最左边的非空 IP（最原始的客户端）。
     *  - 配置：config('route.trusted_proxies', [])，例如 ['127.0.0.1', '10.0.0.0/8', '::1']
     *  - 兼容 Nginx（real_ip_module）与 Cloudflare 反代场景。
     */
    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedProxies = config('route.trusted_proxies', []);
        if (!is_array($trustedProxies)) {
            $trustedProxies = [];
        }

        $isTrusted = $remote !== '' && self::ipMatchesAny($remote, $trustedProxies);

        if ($isTrusted) {
            // 优先级：CF > XFF > XRI > Client-IP，取每条 header 的最左非空 IP
            $headerKeys = [
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_X_FORWARDED_FOR',  // 标准反代
                'HTTP_X_REAL_IP',        // Nginx real_ip_module
                'HTTP_CLIENT_IP',        // 罕见反代
            ];
            foreach ($headerKeys as $k) {
                if (empty($_SERVER[$k])) {
                    continue;
                }
                $parts = explode(',', (string)$_SERVER[$k]);
                foreach ($parts as $p) {
                    $candidate = trim($p);
                    if ($candidate === '') {
                        continue;
                    }
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        // 未配置白名单 / REMOTE_ADDR 不在白名单 / 无有效代理头：仅返回 REMOTE_ADDR
        if ($remote !== '' && filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }
        return '0.0.0.0';
    }

    /**
     * 判断 $ip 是否匹配白名单中的任一规则。
     * 规则支持：单 IP（含 IPv6）与 CIDR。
     */
    private static function ipMatchesAny(string $ip, array $rules): bool
    {
        foreach ($rules as $rule) {
            $rule = trim((string)$rule);
            if ($rule === '') {
                continue;
            }
            if (strpos($rule, '/') === false) {
                if (filter_var($rule, FILTER_VALIDATE_IP) && $rule === $ip) {
                    return true;
                }
                continue;
            }
            // CIDR
            [$subnet, $mask] = explode('/', $rule, 2) + [null, null];
            $subnet = trim((string)$subnet);
            $bits = (int)$mask;
            if ($subnet === '' || $bits < 0 || $bits > 128) {
                continue;
            }
            if (!filter_var($subnet, FILTER_VALIDATE_IP) || !filter_var($ip, FILTER_VALIDATE_IP)) {
                continue;
            }
            $ipBin     = @inet_pton($ip);
            $subnetBin = @inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                continue;
            }
            if (strlen($ipBin) !== strlen($subnetBin)) {
                continue; // IPv4 vs IPv6 不匹配
            }
            $maxBits = strlen($ipBin) * 8;
            if ($bits === 0) {
                return true;
            }
            if ($bits > $maxBits) {
                $bits = $maxBits;
            }
            $fullBytes = intdiv($bits, 8);
            $remBits   = $bits % 8;
            if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
                continue;
            }
            if ($remBits === 0) {
                return true;
            }
            $maskByte = chr((0xFF << (8 - $remBits)) & 0xFF);
            if (($ipBin[$fullBytes] & $maskByte) === ($subnetBin[$fullBytes] & $maskByte)) {
                return true;
            }
        }
        return false;
    }
}
