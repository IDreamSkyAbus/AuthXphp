<?php
/**
 * 安装向导 —— 步骤 2：数据库配置 + 探测
 */
require __DIR__ . '/_bootstrap.php';

$err = null;
$cfg = installer('db', [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'authxphp',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'prefix'   => '',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cfg = [
        'host'     => trim($_POST['host'] ?? '127.0.0.1'),
        'port'     => (int)($_POST['port'] ?? 3306),
        'database' => trim($_POST['database'] ?? 'authxphp'),
        'username' => trim($_POST['username'] ?? 'root'),
        'password' => (string)($_POST['password'] ?? ''),
        'charset'  => 'utf8mb4',
        'prefix'   => trim($_POST['prefix'] ?? ''),
    ];

    // ============================================================================
    // 安全：严格白名单校验数据库名、表前缀（防止 SQL 注入）
    // ============================================================================
    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $cfg['database'])) {
        $err = '数据库名只能包含字母、数字和下划线，长度 1-64 字符';
    } elseif ($cfg['prefix'] !== '' && !preg_match('/^[a-zA-Z0-9_]{1,20}$/', $cfg['prefix'])) {
        $err = '表前缀只能包含字母、数字和下划线，长度 1-20 字符';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]{1,255}$/', $cfg['host'])) {
        $err = '主机名格式不正确';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $cfg['username'])) {
        $err = '用户名只能包含字母、数字和下划线';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{1,16}$/', $cfg['charset'])) {
        // 防御性：虽然当前 charset 在上方硬编码为 utf8mb4 不接受用户输入，
        // 但此处显式白名单校验可避免未来重构时被误改后导致 SQL 拼接注入。
        $err = '字符集格式不正确';
    } else {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $cfg['host'], $cfg['port'], $cfg['charset']);
            $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            // 检查/创建数据库（数据库名已白名单校验，安全）
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['database']}` CHARACTER SET {$cfg['charset']} COLLATE {$cfg['charset']}_unicode_ci");
            // 切换到目标库并 ping
            $pdo->exec("USE `{$cfg['database']}`");
            $pdo->query('SELECT 1');
            installerSet('db', $cfg);
            header('Location: ' . installerUrl('step3.php'));
            exit;
        } catch (PDOException $e) {
            $err = $e->getMessage();
        }
    }
}

renderHeader('数据库配置', 2);
?>
<h1>数据库配置</h1>
<p style="color:#4b5563; line-height:1.7;">请填写 MySQL 连接信息。系统会自动尝试创建数据库（如不存在）。</p>

<?php if ($err): ?>
    <div class="alert err">连接失败：<?= h($err) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <div class="row2">
        <div class="form-row">
            <label>数据库主机</label>
            <input type="text" name="host" value="<?= h($cfg['host']) ?>" required>
        </div>
        <div class="form-row">
            <label>端口</label>
            <input type="number" name="port" value="<?= h($cfg['port']) ?>" required>
        </div>
    </div>
    <div class="form-row">
        <label>数据库名</label>
        <input type="text" name="database" value="<?= h($cfg['database']) ?>" required>
        <div class="hint">不存在将自动创建</div>
    </div>
    <div class="row2">
        <div class="form-row">
            <label>用户名</label>
            <input type="text" name="username" value="<?= h($cfg['username']) ?>" required>
        </div>
        <div class="form-row">
            <label>密码</label>
            <input type="password" name="password" value="<?= h($cfg['password']) ?>">
        </div>
    </div>
    <div class="form-row">
        <label>表前缀（可选）</label>
        <input type="text" name="prefix" value="<?= h($cfg['prefix']) ?>">
    </div>
    <div class="actions">
        <a class="btn outline" href="<?= h(installerUrl('index.php')) ?>">← 上一步</a>
        <button class="btn" type="submit">测试连接并继续</button>
    </div>
</form>
<?php renderFooter(); ?>
