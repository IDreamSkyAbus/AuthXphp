<?php
/**
 * Admin 审计日志
 */
require __DIR__ . '/_bootstrap.php';
$msg = $_GET['msg'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    if (Db::tableExists('auth_logs')) {
        Db::table('auth_logs')->delete();
    }
    header('Location: ' . adminUrl('logs.php', ['msg' => 'cleared']));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 20;
$rows = [];
$total = 0;
if (Db::tableExists('auth_logs')) {
    $total = Db::table('auth_logs')->count();
    $rows = Db::table('auth_logs')->orderBy('id', 'DESC')->page($page, $pageSize)->get();
}
$pages = max(1, (int)ceil($total / $pageSize));

adminRenderHeader('审计日志', 'logs');
?>
<?php if ($msg === 'cleared'): ?>
  <div class="layui-alert layui-alert-success">已清空</div>
<?php endif; ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
  <div>共 <strong><?= (int)$total ?></strong> 条记录</div>
  <form method="post" onsubmit="return confirm('确定清空所有日志？')">
    <input type="hidden" name="action" value="clear">
    <button class="layui-btn layui-btn-sm layui-btn-danger" type="submit">清空日志</button>
  </form>
</div>

<table class="layui-table">
  <thead>
    <tr>
      <th style="width:60px;">ID</th>
      <th style="width:80px;">UID</th>
      <th style="width:80px;">Guard</th>
      <th style="width:160px;">事件</th>
      <th style="width:120px;">IP</th>
      <th>User-Agent</th>
      <th style="width:160px;">时间</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= h($r['id']) ?></td>
        <td><?= h($r['uid'] ?? '-') ?></td>
        <td><span class="layui-badge layui-bg-blue"><?= h($r['guard'] ?? '-') ?></span></td>
        <td><code><?= h($r['event'] ?? '-') ?></code></td>
        <td><?= h($r['ip'] ?? '-') ?></td>
        <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= h($r['user_agent'] ?? '-') ?></td>
        <td><?= h($r['created_at'] ?? '-') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?>
      <tr><td colspan="7" style="text-align:center; color:#9ca3af;">暂无日志</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<?php if ($pages > 1): ?>
<div style="margin-top:16px; text-align:center;">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a class="layui-btn layui-btn-xs <?= $i === $page ? 'layui-btn-normal' : 'layui-btn-primary' ?>" href="<?= h(adminUrl('logs.php', ['page' => $i])) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php adminRenderFooter(); ?>
