<?php
/**
 * 安装向导 —— 步骤 1：环境检测 + 欢迎
 */
require __DIR__ . '/_bootstrap.php';

$checks = [
    'PHP 版本 >= 7.4'         => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO 扩展'                => extension_loaded('pdo'),
    'PDO MySQL 驱动'          => extension_loaded('pdo_mysql'),
    'JSON 扩展'               => extension_loaded('json'),
    'OpenSSL 扩展（JWT）'     => extension_loaded('openssl'),
    'mbstring 扩展'           => extension_loaded('mbstring'),
    'GD 扩展（验证码）'        => extension_loaded('gd'),
    'session 扩展'            => extension_loaded('session'),
];

$writables = [
    'config/'                  => is_writable(dirname(__DIR__) . '/config'),
    'storage/'                 => is_writable(dirname(__DIR__) . '/storage') || @mkdir(dirname(__DIR__) . '/storage', 0755, true),
    'install/'                 => is_writable(__DIR__),
];
$allDirsOk = !in_array(false, $writables, true);
$allExtOk  = !in_array(false, $checks, true);
$canContinue = $allExtOk && $allDirsOk;

renderHeader('环境检测', 1);
?>
<h1>欢迎使用 AuthXphp</h1>
<p style="color:#4b5563; line-height:1.7;">
    AuthXphp 是一款配置驱动、无状态、自带 WEB UI 的 PHP 认证授权组件。本向导将在 6 步内完成部署。
    整个过程不会修改您网站根目录下的其他文件，您可以放心将本组件安装到任意子目录下。
</p>

<h2 style="font-size:16px; margin:24px 0 12px;">1. 扩展检测</h2>
<table>
    <thead><tr><th>项目</th><th>状态</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $name => $ok): ?>
        <tr><td><?= h($name) ?></td><td><?= $ok ? '<span class="check">✓ 通过</span>' : '<span class="cross">✗ 不满足</span>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h2 style="font-size:16px; margin:24px 0 12px;">2. 目录写权限</h2>
<table>
    <thead><tr><th>目录</th><th>状态</th></tr></thead>
    <tbody>
    <?php foreach ($writables as $name => $ok): ?>
        <tr><td><?= h($name) ?></td><td><?= $ok ? '<span class="check">✓ 可写</span>' : '<span class="cross">✗ 不可写</span>' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if (!$canContinue): ?>
    <div class="alert err" style="margin-top:16px;">
        环境不满足，请先解决上述问题（通常是缺少 PHP 扩展或目录不可写），然后<a href="<?= h(installerUrl('index.php')) ?>">刷新此页</a>。
    </div>
<?php else: ?>
    <div class="alert ok" style="margin-top:16px;">环境检测通过，可以继续安装。</div>
<?php endif; ?>

<div class="actions">
    <a class="btn <?= $canContinue ? '' : 'gray' ?>" href="<?= h(installerUrl('step2.php')) ?>">下一步：数据库配置 →</a>
</div>
<?php renderFooter(); ?>
