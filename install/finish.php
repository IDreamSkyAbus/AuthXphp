<?php
/**
 * 安装向导 —— 步骤 6：完成安装（落盘 + 写 install.lock）
 */
require __DIR__ . '/_bootstrap.php';

$db     = installer('db');
$guards = installer('guards');
$admin  = installer('admin');
$runMode = installer('run_mode', 'embedded');

if (!$db || !$guards || !$admin) {
    header('Location: ' . installerUrl('step2.php'));
    exit;
}

$err = null;
$done = false;
$secret = bin2hex(random_bytes(32));
$refreshSecret = bin2hex(random_bytes(32));

try {
    // 1. 写 config/app.php
    writePhpFile(dirname(__DIR__) . '/config/app.php', [
        'name'         => 'AuthXphp',
        'version'      => '1.0.0',
        'run_mode'     => $runMode,
        'base_path'    => '',
        'timezone'     => 'Asia/Shanghai',
        'debug'        => false,
        'admin_guard'  => 'admin',
        // CORS 白名单（嵌入式默认同源；独立站默认通配；如需限定后请在管理后台修改）
        'cors_origins' => ['*'],
        'cors_allow_credentials' => false,
        'cors_allow_methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
        'cors_allow_headers'     => 'Content-Type, Authorization, X-Requested-With, X-Request-Id',
    ]);

    // 2. 写 config/database.php
    writePhpFile(dirname(__DIR__) . '/config/database.php', [
        'default' => 'mysql',
        'connections' => [
            'mysql' => $db,
        ],
    ]);

    // 3. 写 config/jwt.php
    writePhpFile(dirname(__DIR__) . '/config/jwt.php', [
        'secret'         => $secret,
        'refresh_secret' => $refreshSecret,
        'algo'           => 'HS256',
        'access_ttl'     => 3600,
        'refresh_ttl'    => 604800,
        'issuer'         => 'AuthXphp',
        'blacklist'      => [
            'enabled'  => false,
            'driver'   => 'file',
            'path'     => AUTHXPHP_STORAGE_PATH . '/blacklist',
            'redis'    => [
                'host' => '127.0.0.1', 'port' => 6379, 'db' => 0,
                'prefix' => 'authxphp:',
            ],
        ],
    ]);

    // 4. 写 config/guards.php
    writePhpFile(dirname(__DIR__) . '/config/guards.php', $guards);

    // 5. 创建超级管理员账号
    require_once dirname(__DIR__) . '/db/index.php';
    require_once dirname(__DIR__) . '/guard/index.php';
    $g = Guard::driver('admin');
    // 检查账号是否存在
    $exist = Db::table($g->config('table'))
        ->where($g->config('account_field'), $admin['username'])
        ->first();
    if (!$exist) {
        $g->create([
            $g->config('account_field')  => $admin['username'],
            $g->config('password_field') => $admin['password'],
            'realname'   => $admin['realname'],
            'email'      => $admin['email'],
            'role'       => 'super',
            'status'     => 1,
        ]);
    }

    // 6. 写 install.lock
    file_put_contents(__DIR__ . '/install.lock', json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version'      => '1.0.0',
        'run_mode'     => $runMode,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    installerReset();
    $done = true;
} catch (Throwable $e) {
    $err = $e->getMessage();
}

function writePhpFile(string $path, array $data): void
{
    $content = "<?php\nreturn " . var_export($data, true) . ";\n";
    if (!file_put_contents($path, $content)) {
        throw new RuntimeException('无法写入文件：' . $path);
    }
}

renderHeader('完成', 6);
?>
<h1><?= $done ? '安装完成' : '安装失败' ?></h1>

<?php if ($err): ?>
    <div class="alert err"><?= h($err) ?></div>
<?php endif; ?>

<?php if ($done): ?>
    <div class="alert ok">AuthXphp 已成功安装。所有配置文件已生成，超级管理员账号已创建。</div>
    <p style="color:#4b5563; line-height:1.7;">
        请妥善保管 JWT 密钥（已写入 <code>config/jwt.php</code>），泄露后请重新生成。
    </p>
    <h2 style="font-size:16px; margin:20px 0 8px;">后续操作</h2>
    <ul style="color:#4b5563; line-height:1.9;">
        <li>访问 <code>POST <?= h(appBase()) ?>/api/login</code> 用刚创建的账号登录获取 Token</li>
        <li>访问 <code>GET <?= h(appBase()) ?>/api/me</code> 验证 Token 鉴权</li>
        <li>进入 <a href="<?= h(appBase()) ?>/admin/login.php">后台管理</a> 管理用户 / Guard / 钩子 / 日志</li>
    </ul>
    <div class="actions">
        <a class="btn" href="<?= h(appBase()) ?>/admin/login.php">进入后台</a>
        <a class="btn outline" href="<?= h(appBase()) ?>/api/login">API 登录</a>
    </div>
<?php else: ?>
    <div class="actions">
        <a class="btn outline" href="<?= h(installerUrl('step5.php')) ?>">← 返回上一步</a>
    </div>
<?php endif; ?>
<?php renderFooter(); ?>
