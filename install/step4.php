<?php
/**
 * 安装向导 —— 步骤 4：Guard 配置（多 Guard 字段映射）
 */
require __DIR__ . '/_bootstrap.php';

$db = installer('db');
if (!$db) {
    header('Location: ' . installerUrl('step2.php'));
    exit;
}

$guards = installer('guards', [
    'default' => 'app',
    'guards' => [
        'app' => [
            'name' => '前端用户', 'table' => 'users', 'primary_key' => 'id',
            'account_field' => 'username', 'password_field' => 'password',
            'status_field' => 'status', 'role_field' => 'role', 'permissions_field' => 'permissions',
            'password_algo' => 'password_hash', 'registerable' => true,
            'extra_fields' => ['id','username','nickname','email','role','status','created_at'],
        ],
        'admin' => [
            'name' => '后台管理员', 'table' => 'admins', 'primary_key' => 'id',
            'account_field' => 'username', 'password_field' => 'password',
            'status_field' => 'status', 'role_field' => 'role', 'permissions_field' => 'permissions',
            'password_algo' => 'password_hash', 'registerable' => false,
            'extra_fields' => ['id','username','realname','role','status','last_login_at','created_at'],
        ],
    ],
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDefault = $_POST['default'] ?? 'app';
    $rows = $_POST['guard'] ?? [];
    $out  = ['default' => $newDefault, 'guards' => []];
    foreach ($rows as $name => $g) {
        $name = preg_replace('/[^a-z0-9_]/', '', strtolower($name));
        if ($name === '') continue;
        $out['guards'][$name] = [
            'name'             => trim($g['name'] ?? $name),
            'table'            => trim($g['table'] ?? ''),
            'primary_key'      => trim($g['primary_key'] ?? 'id'),
            'account_field'    => trim($g['account_field'] ?? 'username'),
            'password_field'   => trim($g['password_field'] ?? 'password'),
            'status_field'     => trim($g['status_field'] ?? 'status'),
            'role_field'       => trim($g['role_field'] ?? 'role'),
            'permissions_field'=> trim($g['permissions_field'] ?? 'permissions'),
            'password_algo'    => in_array($g['password_algo'] ?? '', ['password_hash','md5','sha1']) ? $g['password_algo'] : 'password_hash',
            'registerable'     => isset($g['registerable']) ? true : false,
            'extra_fields'     => array_filter(array_map('trim', explode(',', $g['extra_fields'] ?? ''))),
        ];
    }
    installerSet('guards', $out);
    header('Location: ' . installerUrl('step5.php'));
    exit;
}

renderHeader('Guard 配置', 4);
?>
<h1>Guard 字段映射</h1>
<p style="color:#4b5563; line-height:1.7;">
    Guard 是 AuthXphp 的核心抽象：每个 Guard 映射一张业务表。您可以增删 Guard、修改表名与字段名，系统对此完全无感。
</p>

<form method="post">
    <div class="form-row">
        <label>默认 Guard</label>
        <select name="default">
            <?php foreach (array_keys($guards['guards']) as $g): ?>
                <option value="<?= h($g) ?>" <?= $guards['default'] === $g ? 'selected' : '' ?>><?= h($g) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="hint">调用 <code>Auth::login(...)</code> 不传 guard 参数时使用此默认值</div>
    </div>

    <h2 style="font-size:16px; margin:20px 0 8px;">Guard 列表</h2>

    <?php foreach ($guards['guards'] as $gname => $g): ?>
    <fieldset style="border:1px solid #e5e7eb; border-radius:8px; padding:16px; margin-bottom:14px;">
        <legend style="padding:0 8px; color:#2563eb; font-weight:600;">Guard: <?= h($gname) ?></legend>
        <input type="hidden" name="guard[<?= h($gname) ?>][_name]" value="<?= h($gname) ?>">
        <div class="row2">
            <div class="form-row">
                <label>显示名</label>
                <input type="text" name="guard[<?= h($gname) ?>][name]" value="<?= h($g['name']) ?>">
            </div>
            <div class="form-row">
                <label>表名</label>
                <input type="text" name="guard[<?= h($gname) ?>][table]" value="<?= h($g['table']) ?>" required>
            </div>
        </div>
        <div class="row3" style="display:flex; gap:12px;">
            <div class="form-row" style="flex:1;">
                <label>主键</label>
                <input type="text" name="guard[<?= h($gname) ?>][primary_key]" value="<?= h($g['primary_key']) ?>">
            </div>
            <div class="form-row" style="flex:1;">
                <label>账号字段</label>
                <input type="text" name="guard[<?= h($gname) ?>][account_field]" value="<?= h($g['account_field']) ?>">
            </div>
            <div class="form-row" style="flex:1;">
                <label>密码字段</label>
                <input type="text" name="guard[<?= h($gname) ?>][password_field]" value="<?= h($g['password_field']) ?>">
            </div>
        </div>
        <div class="row3" style="display:flex; gap:12px;">
            <div class="form-row" style="flex:1;">
                <label>状态字段</label>
                <input type="text" name="guard[<?= h($gname) ?>][status_field]" value="<?= h($g['status_field']) ?>">
            </div>
            <div class="form-row" style="flex:1;">
                <label>角色字段</label>
                <input type="text" name="guard[<?= h($gname) ?>][role_field]" value="<?= h($g['role_field']) ?>">
            </div>
            <div class="form-row" style="flex:1;">
                <label>权限字段</label>
                <input type="text" name="guard[<?= h($gname) ?>][permissions_field]" value="<?= h($g['permissions_field']) ?>">
            </div>
        </div>
        <div class="row2">
            <div class="form-row">
                <label>密码算法</label>
                <select name="guard[<?= h($gname) ?>][password_algo]">
                    <option value="password_hash" <?= $g['password_algo']==='password_hash'?'selected':'' ?>>password_hash (bcrypt，推荐)</option>
                    <option value="md5" <?= $g['password_algo']==='md5'?'selected':'' ?>>md5（仅兼容旧系统）</option>
                    <option value="sha1" <?= $g['password_algo']==='sha1'?'selected':'' ?>>sha1（仅兼容旧系统）</option>
                </select>
                <div class="hint" style="color:#dc2626;">⚠️ 出于安全考虑，明文存储密码已被禁用</div>
            </div>
            <div class="form-row">
                <label>返回字段（逗号分隔）</label>
                <input type="text" name="guard[<?= h($gname) ?>][extra_fields]" value="<?= h(implode(',', $g['extra_fields'])) ?>">
            </div>
        </div>
        <label style="display:flex; gap:6px; align-items:center; font-size:13px;">
            <input type="checkbox" name="guard[<?= h($gname) ?>][registerable]" value="1" <?= !empty($g['registerable']) ? 'checked' : '' ?>>
            开放注册接口（/api/register）
        </label>
    </fieldset>
    <?php endforeach; ?>

    <div class="actions">
        <a class="btn outline" href="<?= h(installerUrl('step3.php')) ?>">← 上一步</a>
        <button class="btn" type="submit">下一步：管理员账号 →</button>
    </div>
</form>
<?php renderFooter(); ?>
