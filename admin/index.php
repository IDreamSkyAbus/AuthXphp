<?php
/**
 * Admin 仪表盘
 */
require __DIR__ . '/_bootstrap.php';

// 统计
$stats = [
    'guards'    => 0,
    'users'     => 0,
    'admins'    => 0,
    'logs_today'=> 0,
];
$stats['guards'] = count(Guard::names());
foreach (Guard::names() as $gname) {
    try {
        $g = Guard::driver($gname);
        $count = Db::table($g->config('table'))->count();
        if ($gname === config('app.admin_guard')) {
            $stats['admins'] = $count;
        } else {
            $stats['users'] += $count;
        }
    } catch (Throwable $e) {
        // 忽略
    }
}
if (Db::tableExists('auth_logs')) {
    try {
        $stats['logs_today'] = (int)Db::raw("SELECT COUNT(*) AS c FROM auth_logs WHERE DATE(created_at) = CURDATE()")[0]['c'] ?? 0;
    } catch (Throwable $e) {
        $stats['logs_today'] = 0;
    }
}

adminRenderHeader('仪表盘', 'dashboard');
?>
<div class="layui-row layui-col-space16">
  <div class="layui-col-md3">
    <div class="stat-card stat-blue">
      <div class="stat-num"><?= (int)$stats['guards'] ?></div>
      <div class="stat-label">已注册 Guard</div>
    </div>
  </div>
  <div class="layui-col-md3">
    <div class="stat-card stat-green">
      <div class="stat-num"><?= (int)$stats['users'] ?></div>
      <div class="stat-label">前端用户</div>
    </div>
  </div>
  <div class="layui-col-md3">
    <div class="stat-card stat-orange">
      <div class="stat-num"><?= (int)$stats['admins'] ?></div>
      <div class="stat-label">管理员</div>
    </div>
  </div>
  <div class="layui-col-md3">
    <div class="stat-card stat-purple">
      <div class="stat-num"><?= (int)$stats['logs_today'] ?></div>
      <div class="stat-label">今日审计</div>
    </div>
  </div>
</div>

<div class="layui-row layui-col-space16" style="margin-top:16px;">
  <div class="layui-col-md6">
    <div class="layui-card">
      <div class="layui-card-header">系统信息</div>
      <div class="layui-card-body">
        <table class="layui-table">
          <tbody>
            <tr><th style="width:140px;">系统名称</th><td><?= h(config('app.name')) ?></td></tr>
            <tr><th>版本</th><td><?= h(config('app.version')) ?></td></tr>
            <tr><th>运行模式</th><td><span class="layui-badge layui-bg-blue"><?= h(config('app.run_mode')) ?></span></td></tr>
            <tr><th>BASE_PATH</th><td><code><?= h(appBase()) ?: '/' ?></code></td></tr>
            <tr><th>PHP 版本</th><td><?= h(PHP_VERSION) ?></td></tr>
            <tr><th>数据库</th><td><?= h(config('db.connections.mysql.host')) ?> / <?= h(config('db.connections.mysql.database')) ?></td></tr>
            <tr><th>JWT 算法</th><td><?= h(config('jwt.algo')) ?></td></tr>
            <tr><th>Access TTL</th><td><?= (int)config('jwt.access_ttl') ?> 秒</td></tr>
            <tr><th>Token 黑名单</th><td><?= (config('jwt.blacklist.enabled') ? '已开启' : '未开启') ?> (<?= h(config('jwt.blacklist.driver')) ?>)</td></tr>
            <tr><th>频率限制</th><td><?= (config('ratelimit.enabled') ? '已开启' : '未开启') ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="layui-col-md6">
    <div class="layui-card">
      <div class="layui-card-header">已注册 Guard</div>
      <div class="layui-card-body">
        <table class="layui-table">
          <thead><tr><th>名称</th><th>表</th><th>账号字段</th><th>状态</th></tr></thead>
          <tbody>
          <?php foreach (Guard::names() as $gname): ?>
            <?php $g = Guard::driver($gname); $c = $g->config(); ?>
            <tr>
              <td><strong><?= h($c['name'] ?? $gname) ?></strong> <span class="layui-badge layui-bg-gray"><?= h($gname) ?></span></td>
              <td><code><?= h($c['table']) ?></code></td>
              <td><?= h($c['account_field']) ?></td>
              <td>
                <?php if (!empty($c['registerable'])): ?>
                  <span class="layui-badge layui-bg-green">开放注册</span>
                <?php else: ?>
                  <span class="layui-badge">不开放注册</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="layui-card" style="margin-top:16px;">
  <div class="layui-card-header">快速开始</div>
  <div class="layui-card-body">
    <p>欢迎使用 AuthXphp！常用 API：</p>
    <pre class="code">POST   <?= h(apiUrl('login')) ?>         body: {account, password, guard?}
GET    <?= h(apiUrl('me')) ?>            Authorization: Bearer &lt;token&gt;
POST   <?= h(apiUrl('refresh')) ?>       body: {refresh_token}
POST   <?= h(apiUrl('logout')) ?>
POST   <?= h(apiUrl('register')) ?>      body: {...} （需 guard.registerable=true）</pre>
  </div>
</div>
<?php adminRenderFooter(); ?>
