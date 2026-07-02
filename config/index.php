<?php
/**
 * AuthXphp 配置中心
 *
 * 加载所有子配置并定义全局常量。
 * 推导 BASE_PATH 以支持嵌入式部署。
 *
 * 注意：本文件不要求在 install 完成前被加载，因此需要容错。
 */

if (defined('AUTHXPHP_CONFIG_LOADED')) {
    return;
}
define('AUTHXPHP_CONFIG_LOADED', true);

// ============================================================================
// 路径常量
// ============================================================================
if (!defined('AUTHXPHP_PATH')) {
    define('AUTHXPHP_PATH', dirname(__DIR__));
}
if (!defined('AUTHXPHP_CONFIG_PATH')) {
    define('AUTHXPHP_CONFIG_PATH', __DIR__);
}
if (!defined('AUTHXPHP_STORAGE_PATH')) {
    define('AUTHXPHP_STORAGE_PATH', AUTHXPHP_PATH . '/storage');
}
if (!defined('AUTHXPHP_LOG_PATH')) {
    define('AUTHXPHP_LOG_PATH', AUTHXPHP_STORAGE_PATH . '/logs');
}

// 加载各配置项（可能尚未安装，使用默认值）
$appConfig = file_exists(__DIR__ . '/app.php') ? require __DIR__ . '/app.php' : ['run_mode' => 'embedded', 'timezone' => 'Asia/Shanghai', 'name' => 'AuthXphp', 'version' => '1.0.0', 'admin_guard' => 'admin'];
$dbConfig  = file_exists(__DIR__ . '/database.php') ? require __DIR__ . '/database.php' : ['default' => 'mysql', 'connections' => []];
$jwtConfig = file_exists(__DIR__ . '/jwt.php') ? require __DIR__ . '/jwt.php' : ['secret' => '__AUTHXPHP_JWT_SECRET_NOT_CONFIGURED__', 'refresh_secret' => '__AUTHXPHP_JWT_REFRESH_SECRET_NOT_CONFIGURED__', 'algo' => 'HS256', 'access_ttl' => 3600, 'refresh_ttl' => 604800, 'issuer' => 'AuthXphp', 'blacklist' => ['enabled' => false, 'driver' => 'file']];
$guardsConfig = file_exists(__DIR__ . '/guards.php') ? require __DIR__ . '/guards.php' : ['default' => 'app', 'guards' => []];
$rlConfig  = file_exists(__DIR__ . '/ratelimit.php') ? require __DIR__ . '/ratelimit.php' : ['enabled' => true, 'driver' => 'file', 'defaults' => []];
$respConfig = file_exists(__DIR__ . '/response.php') ? require __DIR__ . '/response.php' : ['codes' => [], 'http_status' => []];

// ============================================================================
// 安全检测：JWT 密钥占位符 / 弱密钥
// ============================================================================
$jwtSecretPlaceholders = [
    '__AUTHXPHP_JWT_SECRET_NOT_CONFIGURED__',
    '__AUTHXPHP_JWT_REFRESH_SECRET_NOT_CONFIGURED__',
    'PLEASE-CHANGE-THIS-IN-PRODUCTION-AT-LEAST-64-CHARS-LONG-RANDOM-STR',
    'PLEASE-CHANGE-REFRESH-SECRET-IN-PRODUCTION-RANDOM-STR-64-CHARS-LONG',
];
if (in_array($jwtConfig['secret'] ?? '', $jwtSecretPlaceholders, true)
    || in_array($jwtConfig['refresh_secret'] ?? '', $jwtSecretPlaceholders, true)) {
    $lockFile = AUTHXPHP_PATH . '/install/install.lock';
    $isInstall = !empty($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/install/') !== false;
    if (!$isInstall) {
        if (is_file($lockFile)) {
            // 已"安装"但密钥是占位符 → 严重安全风险，强制报错
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'code' => 50001,
                'msg'  => 'JWT 密钥未配置或仍为占位符。出于安全考虑，系统拒绝服务。请删除 install/install.lock 后重新运行安装向导。',
                'data' => null,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        // 未安装且密钥是占位符 → 引导到 install
        $base = rtrim($appConfig['base_path'] ?? '', '/');
        header('Location: ' . ($base ?: '') . '/install/');
        exit;
    }
} elseif (strlen($jwtConfig['secret'] ?? '') < 32 || strlen($jwtConfig['refresh_secret'] ?? '') < 32) {
    // 密钥长度不足 32 字符（HS256 推荐 >= 32 字节）
    if (!empty($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], '/install/') === false) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code' => 50001,
            'msg'  => 'JWT 密钥长度不足 32 字符，存在安全风险。请重新生成后重启。',
            'data' => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ============================================================================
// 时区
// ============================================================================
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Shanghai');

// ============================================================================
// BASE_PATH 推导（嵌入式关键）
// ============================================================================
if (empty($appConfig['base_path'])) {
    if (($appConfig['run_mode'] ?? 'embedded') === 'standalone') {
        $appConfig['base_path'] = '';
    } else {
        // 嵌入式：从 SCRIPT_NAME 推导到 AuthXphp 根目录
        // 例 /authxphp/install/index.php -> /authxphp
        //     /authxphp/admin/login.php  -> /authxphp
        //     /authxphp/index.php        -> /authxphp
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        // 去掉子目录（install/admin/api）后剩下的就是 AuthXphp 根
        $base = preg_replace('#/(install|admin|api)(/.*)?$#', '', $script);
        // 去掉文件名（如 index.php）
        $base = preg_replace('#/[^/]*$#', '', $base);
        $appConfig['base_path'] = rtrim($base, '/');
    }
}

if (!defined('AUTHXPHP_BASE_PATH')) {
    define('AUTHXPHP_BASE_PATH', $appConfig['base_path']);
}

// 路径变量（带 BASE_PATH 前缀）
$pathVar = [
    'login'    => 'admin/login.php',
    'logout'   => 'admin/logout.php',
    'api'      => 'index.php',
];

// 把所有配置集中到 GLOBALS 方便访问
$GLOBALS['__authxphp_config'] = [
    'app'       => $appConfig,
    'db'        => $dbConfig,
    'jwt'       => $jwtConfig,
    'guards'    => $guardsConfig,
    'ratelimit' => $rlConfig,
    'response'  => $respConfig,
    'paths'     => $pathVar,
];

/**
 * 获取配置
 *
 * @param string $key 支持点号路径，如 'app.name' / 'guards.app'
 * @param mixed  $default
 * @return mixed
 */
function config($key = null, $default = null) {
    $cfg = $GLOBALS['__authxphp_config'] ?? null;
    if ($cfg === null) {
        return $default;
    }
    if ($key === null) {
        return $cfg;
    }
    $parts = explode('.', $key);
    $cur = $cfg;
    foreach ($parts as $p) {
        if (is_array($cur) && array_key_exists($p, $cur)) {
            $cur = $cur[$p];
        } else {
            return $default;
        }
    }
    return $cur;
}

/**
 * 取环境变量
 */
function env($key, $default = null) {
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $_ENV[$key] ?? $default;
    }
    return $v;
}
