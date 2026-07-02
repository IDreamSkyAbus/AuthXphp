<?php
/**
 * Admin 后台共享引导
 * - 加载配置、db、guard、auth
 * - 已安装检测
 * - 登录态校验（除 login.php 外）
 * - 暴露公共工具
 */
require_once dirname(__DIR__) . '/config/index.php';
require_once dirname(__DIR__) . '/db/index.php';
require_once dirname(__DIR__) . '/response/index.php';
require_once dirname(__DIR__) . '/log/index.php';
require_once dirname(__DIR__) . '/token/index.php';
require_once dirname(__DIR__) . '/guard/index.php';
require_once dirname(__DIR__) . '/hook/index.php';
require_once dirname(__DIR__) . '/auth/index.php';

// 已安装检测
if (!is_file(dirname(__DIR__) . '/install/install.lock')) {
    $base = rtrim(config('app.base_path') ?: '', '/');
    header('Location: ' . ($base ?: '') . '/install/');
    exit;
}

// 确保 session 已启动（---CSRF 等功能依赖---）
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
$publicPages = ['login.php'];

// 登录态校验
if (!in_array($cur, $publicPages, true)) {
    $adminToken = $_SESSION['authxphp_admin_token'] ?? null;
    if (!$adminToken) {
        $base = rtrim(config('app.base_path') ?: '', '/');
        header('Location: ' . ($base ?: '') . '/admin/login.php');
        exit;
    }
    try {
        $r = Jwt::verify($adminToken, config('app.admin_guard') ?: 'admin');
        $data = $r['data'];
        $g    = Guard::driver($data['guard']);
        $user = $g->byId($data['uid']);
        if (!$user) {
            throw new RuntimeException('用户不存在');
        }
        Auth::setCurrent($user, $data['guard'], $r['jti'], $r['payload']);
    } catch (Throwable $e) {
        unset($_SESSION['authxphp_admin_token']);
        $base = rtrim(config('app.base_path') ?: '', '/');
        header('Location: ' . ($base ?: '') . '/admin/login.php?msg=expired');
        exit;
    }
}

// 公共：BASE_PATH、URL 拼接工具
if (!function_exists('appBase')) {
    function appBase(): string {
        return rtrim(config('app.base_path') ?: '', '/');
    }
    function adminUrl(string $file = 'index.php', array $params = []): string {
        $u = appBase() . '/admin/' . ltrim($file, '/');
        if ($params) {
            $u .= '?' . http_build_query($params);
        }
        return $u;
    }
    function assetUrl(string $p): string {
        return appBase() . '/admin/assets/' . ltrim($p, '/');
    }
    function apiUrl(string $path = '', array $params = []): string {
        $u = appBase() . '/api/' . ltrim($path, '/');
        if ($params) {
            $u .= '?' . http_build_query($params);
        }
        return $u;
    }
    function h($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

function adminRenderHeader(string $title, string $active = ''): void {
    $cur = Auth::user();
    $navs = [
        'dashboard' => ['仪表盘', 'index.php', 'layui-icon-home'],
        'users'     => ['用户管理', 'users.php', 'layui-icon-user'],
        'guards'    => ['Guard 配置', 'guards.php', 'layui-icon-template'],
        'hooks'     => ['事件钩子', 'hooks.php', 'layui-icon-link'],
        'logs'      => ['审计日志', 'logs.php', 'layui-icon-log'],
        'settings'  => ['系统设置', 'settings.php', 'layui-icon-set'],
    ];
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> - AuthXphp 管理后台</title>
<link rel="stylesheet" href="<?= h(assetUrl('layui/css/layui.css')) ?>">
<link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
</head>
<body>
<div class="layui-layout layui-layout-admin">
  <div class="layui-header">
    <div class="layui-logo layui-hide-xs" style="color:#fff; font-weight:600;">AuthXphp</div>
    <ul class="layui-nav layui-layout-left" style="left:200px;">
      <li class="layui-nav-item"><a href="javascript:;" style="color:#fff;"><?= h(config('app.name')) ?> v<?= h(config('app.version')) ?></a></li>
    </ul>
    <ul class="layui-nav layui-layout-right">
      <li class="layui-nav-item">
        <a href="javascript:;">
          <i class="layui-icon layui-icon-username" style="color:#fff;"></i>
          <?= h($cur['realname'] ?? ($cur['username'] ?? '管理员')) ?>
          <span class="layui-badge-dot<?= ($cur['role'] ?? '') === 'super' ? ' layui-bg-orange' : '' ?>"></span>
        </a>
        <dl class="layui-nav-child">
          <dd><a href="<?= h(adminUrl('settings.php')) ?>">系统设置</a></dd>
          <dd><a href="<?= h(adminUrl('logout.php')) ?>">退出登录</a></dd>
        </dl>
      </li>
    </ul>
  </div>

  <div class="layui-side layui-bg-black">
    <div class="layui-side-scroll">
      <ul class="layui-nav layui-nav-tree" lay-filter="admin-side">
        <?php foreach ($navs as $k => $n): ?>
          <li class="layui-nav-item <?= $active === $k ? 'layui-this' : '' ?>">
            <a href="<?= h(adminUrl($n[1])) ?>"><i class="layui-icon <?= h($n[2]) ?>"></i>&nbsp;<?= h($n[0]) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <div class="layui-body">
    <div class="layui-card">
      <div class="layui-card-header" style="font-size:16px; font-weight:600;"><?= h($title) ?></div>
      <div class="layui-card-body">
    <?php
}

function adminRenderFooter(): void { ?>
      </div>
    </div>
  </div>
  <div class="layui-footer" style="text-align:center; color:#9ca3af;">
    © AuthXphp v<?= h(config('app.version')) ?> · 配置驱动 · 无状态
  </div>
</div>
<script src="<?= h(assetUrl('js/admin.js')) ?>"></script>
</body>
</html>
<?php }

/**
 * 生成并存储 CSRF token 到 session
 * 每个 session 一个 token，30 分钟有效期
 */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    // 检查是否已有有效 token
    if (isset($_SESSION['authxphp_csrf'])) {
        $csrf = $_SESSION['authxphp_csrf'];
        if ($csrf['expires'] > time()) {
            return $csrf['token'];
        }
    }

    // 生成新的 32 字符随机 token
    $token = bin2hex(random_bytes(16));
    $_SESSION['authxphp_csrf'] = [
        'token'   => $token,
        'expires' => time() + 1800, // 30 分钟有效期
    ];

    return $token;
}

/**
 * 验证 CSRF token
 * 使用 hash_equals 防止时序攻击
 */
function csrfVerify(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    if (!isset($_SESSION['authxphp_csrf'])) {
        return false;
    }

    $csrf = $_SESSION['authxphp_csrf'];

    // 检查是否过期
    if ($csrf['expires'] <= time()) {
        return false;
    }

    // 使用 hash_equals 防止时序攻击
    return hash_equals($csrf['token'], $token);
}

/**
 * 生成 CSRF 隐藏字段 HTML
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . h(csrfToken()) . '">';
}
