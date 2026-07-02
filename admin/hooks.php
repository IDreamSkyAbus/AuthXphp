<?php
/**
 * Admin 事件钩子管理
 */
require __DIR__ . '/_bootstrap.php';
adminRenderHeader('事件钩子', 'hooks');
?>
<div class="layui-alert layui-alert-info">
  AuthXphp 在关键节点暴露 9 个事件。开发者可在 <code>hook/index.php</code> 或自定义脚本中通过 <code>Hook::on('event', $cb)</code> 注册回调。
</div>
<table class="layui-table">
  <thead>
    <tr>
      <th style="width:280px;">事件名</th>
      <th>触发时机</th>
      <th style="width:120px;">已注册回调</th>
    </tr>
  </thead>
  <tbody>
  <?php
  $events = [
    ['before.login',     '登录前（参数：account, guard）', Hook::count('before.login')],
    ['login.success',    '登录成功后（参数：uid, guard, role）', Hook::count('login.success')],
    ['login.failed',     '登录失败后（参数：account, guard, reason）', Hook::count('login.failed')],
    ['logout',           '登出后（参数：uid, guard）', Hook::count('logout')],
    ['token.verified',   'Token 验证通过（参数：jti, uid, guard）', Hook::count('token.verified')],
    ['token.expired',    'Token 过期（参数：reason）', Hook::count('token.expired')],
    ['token.revoked',    'Token 吊销（参数：jti, ttl）', Hook::count('token.revoked')],
    ['register.success', '注册成功（参数：uid, guard）', Hook::count('register.success')],
    ['password.changed', '密码已修改（参数：uid, guard）', Hook::count('password.changed')],
  ];
  foreach ($events as $e): ?>
    <tr>
      <td><code><?= h($e[0]) ?></code></td>
      <td><?= h($e[1]) ?></td>
      <td>
        <?php if ((int)$e[2] > 0): ?>
          <span class="layui-badge layui-bg-blue"><?= (int)$e[2] ?></span>
        <?php else: ?>
          <span class="layui-badge layui-bg-gray">0</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<div class="layui-card" style="margin-top:20px;">
  <div class="layui-card-header">编写自定义钩子示例</div>
  <div class="layui-card-body">
<pre class="code">// hook/index.php 末尾或自定义文件
Hook::on('login.success', function ($p) {
    Log::info('用户登录', $p);
    // 写 audit log / 发欢迎消息 / 更新最后登录时间
});

Hook::on('login.failed', function ($p) {
    Log::warn('登录失败', $p);
    // 防爆破：连续失败 N 次后封 IP
});</pre>
  </div>
</div>
<?php adminRenderFooter(); ?>
