<?php
/**
 * Admin 用户管理
 */
require __DIR__ . '/_bootstrap.php';

if (empty($_GET['guard'])) {
    $_GET['guard'] = 'app';
}
$curGuard = preg_replace('/[^a-z0-9_]/', '', (string)$_GET['guard']);
$guardList = Guard::names();
if (!in_array($curGuard, $guardList, true)) {
    $curGuard = $guardList[0] ?? 'app';
}
$g = Guard::driver($curGuard);

$msg = $_GET['msg'] ?? null;

// 读取一次性显示的重置密码（来自 user_reset.php）
$showReset = null;
if (!empty($_GET['show_reset']) && !empty($_SESSION['authxphp_reset_pwd'])) {
    $rp = $_SESSION['authxphp_reset_pwd'];
    if (($rp['expires_at'] ?? 0) >= time() && ($rp['guard'] ?? '') === $curGuard) {
        $showReset = $rp;
    }
    unset($_SESSION['authxphp_reset_pwd']); // 一次性消费
}

adminRenderHeader('用户管理（Guard: ' . $curGuard . '）', 'users');
?>
<div class="layui-tab layui-tab-brief" lay-filter="guard-tab">
  <ul class="layui-tab-title">
    <?php foreach ($guardList as $gn): ?>
      <li class="<?= $gn === $curGuard ? 'layui-this' : '' ?>"><a href="<?= h(adminUrl('users.php', ['guard' => $gn])) ?>"><?= h(Guard::driver($gn)->config('name') ?: $gn) ?></a></li>
    <?php endforeach; ?>
  </ul>
  <div class="layui-tab-content">
    <?php if ($showReset): ?>
      <div class="layui-alert layui-alert-warning" style="margin-bottom:16px;">
        <strong>密码已重置</strong>（请妥善保管，本次仅显示一次）：
        <div style="margin-top:8px; padding:10px 12px; background:#fffbe6; border:1px solid #ffe58f; border-radius:4px;">
          <div>账号：<code><?= h($showReset['username'] ?: '-') ?></code></div>
          <div>新密码：<code style="font-size:16px; color:#d4380d; user-select:all;"><?= h($showReset['new_password']) ?></code></div>
          <div style="color:#9ca3af; font-size:12px; margin-top:4px;">
            提示：用户下次登录后应立即通过"修改密码"流程修改为个人密码。
          </div>
        </div>
      </div>
    <?php endif; ?>
    <?php if ($msg === 'ok'): ?>
      <div class="layui-alert layui-alert-success">操作成功</div>
    <?php elseif ($msg === 'err'): ?>
      <div class="layui-alert layui-alert-danger">操作失败</div>
    <?php endif; ?>

    <table class="layui-table">
      <thead>
        <tr>
          <th style="width:60px;">ID</th>
          <th>账号</th>
          <th>角色</th>
          <th>状态</th>
          <th>最后登录</th>
          <th>创建时间</th>
          <th style="width:200px;">操作</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $pk = $g->config('primary_key');
        $sf = $g->config('status_field');
        $rf = $g->config('role_field');
        $selectCols = array_values(array_unique(array_filter([
            $pk,
            $g->config('account_field'),
            $rf,
            $sf,
            'last_login_at',
            'created_at',
        ])));
        $rows = Db::table($g->config('table'))
            ->select(...$selectCols)
            ->orderBy($pk, 'DESC')
            ->limit(100)
            ->get();
        foreach ($rows as $r):
        ?>
        <tr>
          <td><?= h($r[$pk] ?? '') ?></td>
          <td><strong><?= h($r[$g->config('account_field')] ?? '') ?></strong></td>
          <td><span class="layui-badge layui-bg-blue"><?= h($r[$rf] ?? '-') ?></span></td>
          <td>
            <?php if ((int)($r[$sf] ?? 1) === 1): ?>
              <span class="layui-badge layui-bg-green">正常</span>
            <?php else: ?>
              <span class="layui-badge layui-bg-gray">禁用</span>
            <?php endif; ?>
          </td>
          <td><?= h($r['last_login_at'] ?? '-') ?></td>
          <td><?= h($r['created_at'] ?? '-') ?></td>
          <td>
            <a class="layui-btn layui-btn-xs" href="<?= h(adminUrl('user_edit.php', ['guard' => $curGuard, 'id' => $r[$pk]])) ?>">编辑</a>
            <form id="toggle-<?= $r[$pk] ?>" method="post" action="<?= h(adminUrl('user_toggle.php')) ?>" style="display:none;">
              <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="guard" value="<?= h($curGuard) ?>">
              <input type="hidden" name="id" value="<?= h($r[$pk]) ?>">
            </form>
            <a class="layui-btn layui-btn-xs layui-btn-warm" href="javascript:;" onclick="if(confirm('确定切换状态？'))document.getElementById('toggle-<?= $r[$pk] ?>').submit()">启停</a>

            <form id="reset-<?= $r[$pk] ?>" method="post" action="<?= h(adminUrl('user_reset.php')) ?>" style="display:none;">
              <input type="hidden" name="_csrf" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="guard" value="<?= h($curGuard) ?>">
              <input type="hidden" name="id" value="<?= h($r[$pk]) ?>">
              <input type="hidden" name="username" value="<?= h($r[$g->config('account_field')]) ?>">
            </form>
            <a class="layui-btn layui-btn-xs layui-btn-danger" href="javascript:;" onclick="if(confirm('确定要重置该用户的密码？\n\n系统将生成一个 12 位强随机密码，仅本次显示一次。'))document.getElementById('reset-<?= $r[$pk] ?>').submit()">重置密码</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="7" style="text-align:center; color:#9ca3af;">暂无数据</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php adminRenderFooter(); ?>
