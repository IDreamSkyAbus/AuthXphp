<?php
/**
 * Admin 登录页
 *
 * 安全加固（BUG #4 / #5 / #13 / #14）：
 *  - BUG #4  速率限制：5 次/分钟（IP 维度，避开全球爆破）
 *                账号维度：连续 5 次失败 → 锁定 10 分钟
 *  - BUG #5  CSRF 保护：表单嵌入隐藏 token，POST 校验
 *  - BUG #13 会话固定防护：登录成功 session_regenerate_id(true)
 *  - BUG #14 双 Token：签发 access + refresh，session 保存两个 token，
 *                access 过期时 bootstrap 自动 refresh
 */
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/ratelimit/index.php';

$err = null;

// ----------------------------------------------------------------------------
// BUG #4 速率限制常量
// ----------------------------------------------------------------------------
const ADMIN_LOGIN_RL_MAX    = 5;     // 每窗口最大尝试次数
const ADMIN_LOGIN_RL_WINDOW = 60;    // 窗口（秒）
const ADMIN_LOCK_MAX        = 5;     // 失败次数阈值
const ADMIN_LOCK_TTL        = 600;   // 锁定 10 分钟

// 会话已在 _bootstrap 启动（line 26-28），直接使用
// 读取锁定信息（每账号）
$lockKey  = 'authxphp_admin_lock';
$lockData = $_SESSION[$lockKey] ?? ['attempts' => 0, 'locked_until' => 0, 'username' => ''];
$account  = trim($_POST['username'] ?? '');
$ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) CSRF 校验（BUG #5）
    if (!csrfVerify($_POST['_csrf'] ?? '')) {
        $err = '会话已过期，请刷新页面后重试';
    }

    // 2) 速率限制（IP 维度，BUG #4）
    if ($err === null) {
        $rlKey = 'admin_login:' . $ip;
        if (!RateLimiter::hit($rlKey, ADMIN_LOGIN_RL_MAX, ADMIN_LOGIN_RL_WINDOW)) {
            $err = '尝试过于频繁，请 1 分钟后再试';
        }
    }

    // 3) 账号锁定校验（BUG #4）
    if ($err === null && $lockData['locked_until'] > time()
        && $lockData['username'] === $account) {
        $remain = (int)ceil(($lockData['locked_until'] - time()) / 60);
        $err = "账号已被锁定，请 {$remain} 分钟后再试";
    }

    $password = (string)($_POST['password'] ?? '');

    if ($err === null) {
        if ($account === '' || $password === '') {
            $err = '账号和密码不能为空';
        } else {
            $r = Auth::login($account, $password, config('app.admin_guard') ?: 'admin');

            if ($r) {
                // BUG #13 会话固定防护：登录成功前重新生成 session id
                session_regenerate_id(true);

                // BUG #14 同时保存 access + refresh token，
                //            bootstrap 中 access 过期会用 refresh 自动续期
                $_SESSION['authxphp_admin_token']         = $r['token'];
                $_SESSION['authxphp_admin_refresh_token'] = $r['refresh_token'] ?? '';
                $_SESSION['authxphp_admin_token_iat']     = time();

                // 登录成功：清空失败计数
                unset($_SESSION[$lockKey]);

                header('Location: ' . adminUrl('index.php'));
                exit;
            }

            // 登录失败：累加失败次数
            $err = '账号或密码错误';

            // 切到当前账号，避免之前账号的失败计数污染
            if ($lockData['username'] !== $account) {
                $lockData = ['attempts' => 0, 'locked_until' => 0, 'username' => $account];
            }
            $lockData['attempts'] = (int)$lockData['attempts'] + 1;
            if ($lockData['attempts'] >= ADMIN_LOCK_MAX) {
                $lockData['locked_until'] = time() + ADMIN_LOCK_TTL;
                $err = '连续失败次数过多，账号已锁定 10 分钟';
            }
            $_SESSION[$lockKey] = $lockData;
        }
    }
}

// 生成 CSRF token（用于 GET 显示 / 下次 POST 提交）
$csrfToken = csrfToken();
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>登录 - AuthXphp 管理后台</title>
<link rel="stylesheet" href="<?= h(assetUrl('css/admin.css')) ?>">
</head>
<body class="login-body">
<div class="login-wrap">
  <form class="layui-form login-form" method="post" autocomplete="off">
    <div class="login-title">AuthXphp 管理后台</div>
    <div class="login-sub">v<?= h(config('app.version')) ?> · 配置驱动 · 无状态</div>
    <?php if ($err): ?>
      <div class="layui-form-mid layui-text-danger" style="display:block; text-align:center; margin-bottom:12px;">
        <?= h($err) ?>
      </div>
    <?php endif; ?>
    <div class="layui-form-item">
      <label class="layui-form-label"><i class="layui-icon layui-icon-username"></i></label>
      <div class="layui-input-block"><input type="text" name="username" required lay-verify="required" placeholder="账号" class="layui-input"></div>
    </div>
    <div class="layui-form-item">
      <label class="layui-form-label"><i class="layui-icon layui-icon-password"></i></label>
      <div class="layui-input-block"><input type="password" name="password" required lay-verify="required" placeholder="密码" class="layui-input"></div>
    </div>
    <div class="layui-form-item">
      <button class="layui-btn layui-btn-fluid" lay-submit>登 录</button>
    </div>
    <!-- BUG #5 CSRF 隐藏字段 -->
    <input type="hidden" name="_csrf" value="<?= h($csrfToken) ?>">
  </form>
</div>
</body>
</html>
