<?php
/**
 * Admin 系统设置
 */
require __DIR__ . '/_bootstrap.php';
adminRenderHeader('系统设置', 'settings');
?>
<div class="layui-tab layui-tab-brief">
  <ul class="layui-tab-title">
    <li class="layui-this">基础信息</li>
    <li>JWT 配置</li>
    <li>限流配置</li>
    <li>黑名单</li>
  </ul>
  <div class="layui-tab-content">
    <div class="layui-tab-item layui-show">
      <table class="layui-table">
        <tbody>
          <tr><th style="width:160px;">系统名称</th><td><?= h(config('app.name')) ?></td></tr>
          <tr><th>版本</th><td><?= h(config('app.version')) ?></td></tr>
          <tr><th>运行模式</th><td><span class="layui-badge layui-bg-blue"><?= h(config('app.run_mode')) ?></span></td></tr>
          <tr><th>BASE_PATH</th><td><code><?= h(appBase()) ?: '/' ?></code></td></tr>
          <tr><th>时区</th><td><?= h(config('app.timezone')) ?></td></tr>
          <tr><th>默认 Guard</th><td><code><?= h(config('guards.default')) ?></code></td></tr>
          <tr><th>后台 Guard</th><td><code><?= h(config('app.admin_guard')) ?></code></td></tr>
        </tbody>
      </table>
    </div>
    <div class="layui-tab-item">
      <table class="layui-table">
        <tbody>
          <tr><th style="width:160px;">算法</th><td><code><?= h(config('jwt.algo')) ?></code></td></tr>
          <tr><th>Access TTL</th><td><?= (int)config('jwt.access_ttl') ?> 秒</td></tr>
          <tr><th>Refresh TTL</th><td><?= (int)config('jwt.refresh_ttl') ?> 秒</td></tr>
          <tr><th>Issuer</th><td><?= h(config('jwt.issuer')) ?></td></tr>
          <tr><th>Secret</th><td><code>*** 已隐藏 ***（共 <?= strlen(config('jwt.secret')) ?> 字符）</code></td></tr>
        </tbody>
      </table>
    </div>
    <div class="layui-tab-item">
      <table class="layui-table">
        <tbody>
          <tr><th style="width:160px;">启用</th><td><?= (config('ratelimit.enabled') ? '<span class="layui-badge layui-bg-green">已开启</span>' : '<span class="layui-badge">未开启</span>') ?></td></tr>
          <tr><th>驱动</th><td><code><?= h(config('ratelimit.driver')) ?></code></td></tr>
          <tr><th>默认规则</th><td>
            <pre class="code"><?= h(json_encode(config('ratelimit.defaults'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          </td></tr>
        </tbody>
      </table>
    </div>
    <div class="layui-tab-item">
      <table class="layui-table">
        <tbody>
          <tr><th style="width:160px;">黑名单</th><td><?= (config('jwt.blacklist.enabled') ? '<span class="layui-badge layui-bg-green">已开启</span>' : '<span class="layui-badge">未开启</span>') ?>（修改 <code>config/jwt.php</code> 中 blacklist.enabled）</td></tr>
          <tr><th>驱动</th><td><code><?= h(config('jwt.blacklist.driver')) ?></code></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php adminRenderFooter(); ?>
