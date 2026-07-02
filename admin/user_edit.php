<?php
/**
 * Admin 编辑用户
 */
require __DIR__ . '/_bootstrap.php';
$curGuard = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['guard'] ?? 'app'));
$g = Guard::driver($curGuard);
$pk = $g->config('primary_key');
$id = (int)($_GET['id'] ?? 0);

// 管理后台需要查询所有用户（包括被禁用的），因此直接使用 Db::table() 而非 byId()
// byId() 会过滤掉被禁用的用户，导致无法编辑禁用用户
$row = Db::table($g->config('table'))
    ->where($pk, $id)
    ->first();
if (!$row) {
    header('Location: ' . adminUrl('users.php', ['guard' => $curGuard, 'msg' => 'err']));
    exit;
}
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [];

        // ============================================================================
        // 安全：字段值类型过滤（防止注入额外字段或 XSS）
        // ============================================================================
        // 字符串字段（限制长度，HTML 转义）
        $stringFields = [
            'realname'  => 64,
            'nickname'  => 64,
            'role'      => 32,
            'avatar'    => 255,
        ];
        foreach ($stringFields as $f => $maxLen) {
            if (isset($_POST[$f])) {
                $val = trim((string)$_POST[$f]);
                // 长度限制
                if (strlen($val) > $maxLen) {
                    $val = substr($val, 0, $maxLen);
                }
                $data[$f] = $val;
            }
        }

        // email 字段（格式校验）
        if (isset($_POST['email']) && $_POST['email'] !== '') {
            $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
            if ($email === false) {
                throw new RuntimeException('邮箱格式不正确');
            }
            $data['email'] = $email;
        }

        // phone 字段（仅数字+常见符号）
        if (isset($_POST['phone']) && $_POST['phone'] !== '') {
            $phone = preg_replace('/[^0-9+\-]/', '', trim($_POST['phone']));
            if (strlen($phone) < 6 || strlen($phone) > 20) {
                throw new RuntimeException('手机号长度不正确（6-20 位）');
            }
            $data['phone'] = $phone;
        }

        // status 字段（整数 0/1）
        $data['status'] = isset($_POST['status']) ? ((int)$_POST['status'] === 1 ? 1 : 0) : 1;

        // 确保只更新 Guard 配置中 extra_fields 允许的字段
        $allowedFields = $g->config('extra_fields') ?? [];
        $allowedFields[] = $g->config('status_field') ?? 'status'; // status 始终允许
        $allowedFields[] = $g->config('password_field'); // 密码字段单独处理
        // 去重
        $allowedFields = array_unique($allowedFields);
        $data = array_intersect_key($data, array_flip($allowedFields));

        $g->update($id, $data);
        header('Location: ' . adminUrl('users.php', ['guard' => $curGuard, 'msg' => 'ok']));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
adminRenderHeader('编辑用户 #' . $id, 'users');
?>
<?php if ($err): ?><div class="layui-alert layui-alert-danger"><?= h($err) ?></div><?php endif; ?>
<form class="layui-form" method="post" style="max-width:560px;">
  <div class="layui-form-item">
    <label class="layui-form-label">账号</label>
    <div class="layui-input-block"><input type="text" disabled value="<?= h($row[$g->config('account_field')] ?? '') ?>" class="layui-input"></div>
  </div>
  <div class="layui-form-item">
    <label class="layui-form-label">角色</label>
    <div class="layui-input-block"><input type="text" name="role" value="<?= h($row['role'] ?? '') ?>" class="layui-input"></div>
  </div>
  <?php foreach (['realname','nickname','email','phone','avatar'] as $f): ?>
    <div class="layui-form-item">
      <label class="layui-form-label"><?= h($f) ?></label>
      <div class="layui-input-block"><input type="text" name="<?= h($f) ?>" value="<?= h($row[$f] ?? '') ?>" class="layui-input"></div>
    </div>
  <?php endforeach; ?>
  <div class="layui-form-item">
    <label class="layui-form-label">状态</label>
    <div class="layui-input-block">
      <input type="radio" name="status" value="1" title="正常" <?= (int)($row['status'] ?? 1) === 1 ? 'checked' : '' ?>>
      <input type="radio" name="status" value="0" title="禁用" <?= (int)($row['status'] ?? 1) === 0 ? 'checked' : '' ?>>
    </div>
  </div>
  <div class="layui-form-item">
    <div class="layui-input-block">
      <button class="layui-btn" lay-submit>保存</button>
      <a class="layui-btn layui-btn-primary" href="<?= h(adminUrl('users.php', ['guard' => $curGuard])) ?>">返回</a>
    </div>
  </div>
</form>
<?php adminRenderFooter(); ?>
