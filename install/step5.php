<?php
/**
 * 安装向导 —— 步骤 5：运行模式 + 超级管理员账号
 */
require __DIR__ . '/_bootstrap.php';

$db     = installer('db');
$guards = installer('guards');
if (!$db || !$guards) {
    header('Location: ' . installerUrl('step2.php'));
    exit;
}

$err = null;
$admin = installer('admin', [
    'username' => 'admin',
    'password' => '',
    'realname' => '超级管理员',
    'email'    => '',
]);
$runMode = installer('run_mode', 'embedded');

/**
 * 校验密码强度
 * 规则：
 *   1. 至少 8 位
 *   2. 至少包含大写字母、小写字母、数字中的两类
 *   3. 不允许全部为相同字符
 *   4. 不允许为常见弱密码（不区分大小写）
 * 通过返回 null，失败返回错误信息。
 */
function validatePasswordStrength(string $password): ?string
{
    if (strlen($password) < 8) {
        return '密码至少 8 位（推荐）';
    }

    $hasUpper = (int)preg_match('/[A-Z]/', $password);
    $hasLower = (int)preg_match('/[a-z]/', $password);
    $hasDigit = (int)preg_match('/\d/', $password);
    if ($hasUpper + $hasLower + $hasDigit < 2) {
        return '密码需至少包含大写字母、小写字母、数字中的两类';
    }

    if (preg_match('/^(.)\1+$/', $password)) {
        return '密码不能全部为相同字符';
    }

    static $weak = [
        'password', 'passw0rd', 'p@ssword', 'p@ssw0rd',
        '12345678', '123456789', '1234567890', '123456',
        'qwerty', 'qwerty123', 'qwertyuio', 'asdfgh', 'asdfghjkl', 'zxcvbn', 'zxcvbnm',
        'admin', 'admin123', 'admin1234', 'administrator', 'root', 'root123',
        'letmein', 'welcome', 'welcome1', 'abc123', 'abc1234', 'abcd1234',
        'iloveyou', 'monkey', 'dragon', 'master', 'login', 'passw0rd1',
        'sunshine', 'princess', 'football', 'shadow', 'starwars',
        'trustno1', 'baseball', 'superman', 'batman', 'michael',
        '11111111', '00000000', '22222222', 'aaaaaaaa', 'ffffffff',
    ];
    if (in_array(strtolower($password), $weak, true)) {
        return '密码过于简单，请更换为更复杂的密码';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin = [
        'username' => trim($_POST['username'] ?? 'admin'),
        'password' => (string)($_POST['password'] ?? ''),
        'realname' => trim($_POST['realname'] ?? '超级管理员'),
        'email'    => trim($_POST['email'] ?? ''),
    ];
    $runMode = $_POST['run_mode'] ?? 'embedded';
    if ($admin['username'] === '' || $admin['password'] === '') {
        $err = '账号和密码不能为空';
    } elseif (($passwordErr = validatePasswordStrength($admin['password'])) !== null) {
        $err = $passwordErr;
    } else {
        installerSet('admin', $admin);
        installerSet('run_mode', $runMode);
        header('Location: ' . installerUrl('finish.php'));
        exit;
    }
}

renderHeader('管理员 + 运行模式', 5);
?>
<h1>超级管理员 & 运行模式</h1>
<p style="color:#4b5563; line-height:1.7;">创建初始超级管理员账号，并选择本系统的运行模式。</p>

<?php if ($err): ?>
    <div class="alert err"><?= h($err) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">
    <h2 style="font-size:16px; margin:0 0 12px;">运行模式</h2>
    <div style="display:flex; gap:16px; margin-bottom:24px;">
        <label style="flex:1; padding:16px; border:2px solid <?= $runMode==='embedded'?'#2563eb':'#e5e7eb' ?>; border-radius:8px; cursor:pointer;">
            <input type="radio" name="run_mode" value="embedded" <?= $runMode==='embedded'?'checked':'' ?>>
            <strong style="display:block; margin-top:6px;">嵌入式（推荐）</strong>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">作为网站子目录运行，不影响现有网站。所有 URL 路径自动适配子目录。</div>
        </label>
        <label style="flex:1; padding:16px; border:2px solid <?= $runMode==='standalone'?'#2563eb':'#e5e7eb' ?>; border-radius:8px; cursor:pointer;">
            <input type="radio" name="run_mode" value="standalone" <?= $runMode==='standalone'?'checked':'' ?>>
            <strong style="display:block; margin-top:6px;">独立站</strong>
            <div style="font-size:12px; color:#6b7280; margin-top:4px;">AuthXphp 作为独立站点运行（部署到域名根或专属子域）。</div>
        </label>
    </div>

    <h2 style="font-size:16px; margin:0 0 12px;">超级管理员账号</h2>
    <div class="row2">
        <div class="form-row">
            <label>账号</label>
            <input type="text" name="username" value="<?= h($admin['username']) ?>" required>
        </div>
        <div class="form-row">
            <label>密码</label>
            <input type="password" name="password" value="" placeholder="至少 8 位，含大小写字母和数字" required>
            <small class="hint">密码强度要求：至少 8 位、同时包含大写字母/小写字母/数字中的至少两类，且不能为常见弱密码（如 password、12345678、admin、qwerty 等）。</small>
        </div>
    </div>
    <div class="row2">
        <div class="form-row">
            <label>姓名</label>
            <input type="text" name="realname" value="<?= h($admin['realname']) ?>">
        </div>
        <div class="form-row">
            <label>邮箱</label>
            <input type="text" name="email" value="<?= h($admin['email']) ?>">
        </div>
    </div>

    <div class="actions">
        <a class="btn outline" href="<?= h(installerUrl('step4.php')) ?>">← 上一步</a>
        <button class="btn" type="submit">下一步：完成安装 →</button>
    </div>
</form>
<?php renderFooter(); ?>
