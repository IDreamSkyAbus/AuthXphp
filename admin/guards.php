<?php
/**
 * Admin Guard 列表
 */
require __DIR__ . '/_bootstrap.php';
$allGuards = config('guards.guards') ?: [];
adminRenderHeader('Guard 配置', 'guards');
?>
<div class="layui-alert layui-alert-info">
  Guard 是 AuthXphp 的核心抽象：每个 Guard 映射一张业务表。修改 Guard 配置请编辑 <code>config/guards.php</code> 后重启服务。
</div>
<?php foreach ($allGuards as $name => $g): ?>
<fieldset style="border:1px solid #e5e7eb; border-radius:8px; padding:20px; margin-bottom:16px;">
  <legend style="padding:0 12px; color:#2563eb; font-weight:600;">Guard: <?= h($name) ?> <?= ($name === config('guards.default')) ? '<span class="layui-badge layui-bg-orange">默认</span>' : '' ?></legend>
  <table class="layui-table">
    <tbody>
      <tr><th style="width:160px;">显示名</th><td><?= h($g['name'] ?? '-') ?></td></tr>
      <tr><th>表名</th><td><code><?= h($g['table']) ?></code></td></tr>
      <tr><th>主键</th><td><code><?= h($g['primary_key']) ?></code></td></tr>
      <tr><th>账号字段</th><td><code><?= h($g['account_field']) ?></code></td></tr>
      <tr><th>密码字段</th><td><code><?= h($g['password_field']) ?></code></td></tr>
      <tr><th>状态字段</th><td><code><?= h($g['status_field'] ?? '-') ?></code></td></tr>
      <tr><th>角色字段</th><td><code><?= h($g['role_field'] ?? '-') ?></code></td></tr>
      <tr><th>权限字段</th><td><code><?= h($g['permissions_field'] ?? '-') ?></code></td></tr>
      <tr><th>密码算法</th><td><code><?= h($g['password_algo'] ?? 'password_hash') ?></code></td></tr>
      <tr><th>开放注册</th><td><?= !empty($g['registerable']) ? '<span class="layui-badge layui-bg-green">是</span>' : '<span class="layui-badge">否</span>' ?></td></tr>
      <tr><th>返回字段</th><td><code><?= h(implode(', ', $g['extra_fields'] ?? [])) ?></code></td></tr>
    </tbody>
  </table>
  <div style="margin-top:8px;">
    <a class="layui-btn layui-btn-sm" href="<?= h(adminUrl('users.php', ['guard' => $name])) ?>">查看此 Guard 的用户</a>
  </div>
</fieldset>
<?php endforeach; ?>
<?php adminRenderFooter(); ?>
