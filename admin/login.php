<?php
/**
 * Admin 登录页
 */
require __DIR__ . '/_bootstrap.php';

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account  = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if ($account === '' || $password === '') {
        $err = '账号和密码不能为空';
    } else {
        $r = Auth::login($account, $password, config('app.admin_guard') ?: 'admin');
        if ($r) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['authxphp_admin_token'] = $r['token'];
            header('Location: ' . adminUrl('index.php'));
            exit;
        }
        $err = '账号或密码错误';
    }
}
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
  </form>
</div>
</body>
</html>
