<?php
/**
 * 安装向导 —— 步骤 3：导入默认表结构
 */
require __DIR__ . '/_bootstrap.php';

$db = installer('db');
if (!$db) {
    header('Location: ' . installerUrl('step2.php'));
    exit;
}

$err = null;
$msg = null;
$imported = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'skip') {
        installerSet('schema_imported', false);
        header('Location: ' . installerUrl('step4.php'));
        exit;
    }
    if ($action === 'import') {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $db['host'], $db['port'], $db['database'], $db['charset']);
            $pdo = new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sql = file_get_contents(__DIR__ . '/schema/install.sql');
            $pdo->exec($sql);
            installerSet('schema_imported', true);
            $imported = true;
            $msg = '已成功导入 users / admins / auth_logs 表';
        } catch (Throwable $e) {
            $err = $e->getMessage();
        }
    }
    if ($action === 'next') {
        header('Location: ' . installerUrl('step4.php'));
        exit;
    }
}

renderHeader('导入默认表', 3);
?>
<h1>导入默认表结构</h1>
<p style="color:#4b5563; line-height:1.7;">
    AuthXphp 默认创建 <code>users</code>（前端用户）、<code>admins</code>（后台管理员）、<code>auth_logs</code>（审计日志）三张表。<br>
    如果您已有自己的业务表，可以跳过此步，在下一步配置 Guard 直接对接。
</p>

<?php if ($err): ?>
    <div class="alert err">导入失败：<?= h($err) ?></div>
<?php endif; ?>
<?php if ($msg): ?>
    <div class="alert ok"><?= h($msg) ?></div>
<?php endif; ?>

<?php if (!$imported): ?>
<div class="row2" style="margin-top:8px;">
    <form method="post" style="flex:1;">
        <input type="hidden" name="action" value="import">
        <button class="btn" type="submit" style="width:100%;">导入默认表（推荐新手）</button>
    </form>
    <form method="post" style="flex:1;">
        <input type="hidden" name="action" value="skip">
        <button class="btn outline" type="submit" style="width:100%;">跳过（使用我自己的表）</button>
    </form>
</div>
<?php else: ?>
    <form method="post">
        <input type="hidden" name="action" value="next">
        <div class="actions">
            <a class="btn outline" href="<?= h(installerUrl('step2.php')) ?>">← 上一步</a>
            <button class="btn" type="submit">下一步：Guard 配置 →</button>
        </div>
    </form>
<?php endif; ?>
<?php renderFooter(); ?>
