<?php
/**
 * 安装向导 —— 步骤 3：导入默认表结构
 */
require __DIR__ . '/_bootstrap.php';

/**
 * 将多语句 SQL 拆分为单条 SQL 的数组。
 *  - 去除 /* ... *​/ 块注释
 *  - 去除 -- 与 # 行注释（仅在字符串外生效）
 *  - 按 ; 分隔（仅在字符串外生效，正确处理 ' " ` 字符串字面量与反斜杠转义）
 *  - 去除空语句
 */
function splitSqlStatements(string $sql): array
{
    // 1. 去除 /* ... */ 块注释（非贪婪，跨行）
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql);

    $statements = [];
    $current    = '';
    $len        = strlen($sql);
    $inString   = false;
    $stringChar = '';
    $i          = 0;

    while ($i < $len) {
        $char = $sql[$i];

        // 在字符串内部：等待结束引号；处理反斜杠转义
        if ($inString) {
            $current .= $char;
            if ($char === '\\' && $i + 1 < $len) {
                $current .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            if ($char === $stringChar) {
                $inString = false;
            }
            $i++;
            continue;
        }

        // 进入字符串（单引号、双引号、反引号）
        if ($char === "'" || $char === '"' || $char === '`') {
            $inString   = true;
            $stringChar = $char;
            $current .= $char;
            $i++;
            continue;
        }

        // -- 行注释：跳到行尾
        if ($char === '-' && $i + 1 < $len && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // # 行注释（MySQL）：跳到行尾
        if ($char === '#') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }

        // 语句结束
        if ($char === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            $i++;
            continue;
        }

        $current .= $char;
        $i++;
    }

    $stmt = trim($current);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

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
            // BUG #1 修复：PDO::exec() 不会执行多条 SQL，需要按 ; 拆分后逐条执行。
            $statements = splitSqlStatements($sql);
            foreach ($statements as $stmt) {
                $pdo->exec($stmt);
            }
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
