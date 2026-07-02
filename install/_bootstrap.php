<?php
/**
 * AuthXphp 安装向导 —— 共享引导与工具
 *
 * 包含：
 *   - 引导 config / response
 *   - 锁定检测（已锁定则跳转主页）
 *   - 公共 HTML 渲染工具
 *   - Session 状态读写
 */

// 安装目录独立可用，所以要重新引导
require_once dirname(__DIR__) . '/config/index.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// install 子目录的 URL 路径（已包含 BASE_PATH）
$installBase = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$installBase = preg_replace('#/step\d+\.php$|/finish\.php$|/index\.php$#', '', $installBase);
$appBase     = rtrim(config('app.base_path') ?: '', '/');
// $installBase 已经是完整路径（含 BASE_PATH），不需要再 prepend $appBase
$installUrl  = rtrim($installBase, '/') . '/';

if (!function_exists('installer')) {
    function installer(string $key, $default = null) {
        return $_SESSION['authxphp_install'][$key] ?? $default;
    }
    function installerSet(string $key, $value): void {
        $_SESSION['authxphp_install'][$key] = $value;
    }
    function installerReset(): void {
        unset($_SESSION['authxphp_install']);
    }
    function installerUrl(string $file, array $params = []): string {
        global $installUrl;
        $u = $installUrl . $file;
        if ($params) {
            $u .= '?' . http_build_query($params);
        }
        return $u;
    }
    function appBase(): string {
        global $appBase;
        return $appBase;
    }
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
    function renderHeader(string $title, int $step, int $total = 6): void {
        $titles = [
            1 => '欢迎使用',
            2 => '数据库配置',
            3 => '导入默认表',
            4 => 'Guard 配置',
            5 => '运行模式 / 管理员',
            6 => '完成',
        ];
        $labels = [1 => '环境检测', 2 => '数据库', 3 => '数据表', 4 => 'Guard', 5 => '管理员', 6 => '完成'];
        ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> - AuthXphp 安装向导</title>
<style>
* { box-sizing: border-box; }
body { font-family: -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; background: #f5f7fa; margin: 0; color: #1f2937; }
.wrap { max-width: 920px; margin: 32px auto; padding: 0 16px; }
.card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 32px; }
.brand { font-size: 22px; font-weight: 600; color: #2563eb; margin-bottom: 4px; }
.sub { color: #6b7280; margin-bottom: 24px; }
.steps { display: flex; gap: 0; margin-bottom: 32px; counter-reset: s; }
.steps li { flex: 1; list-style: none; text-align: center; padding: 12px 8px; background: #f3f4f6; color: #6b7280; font-size: 14px; position: relative; }
.steps li.active { background: #2563eb; color: #fff; font-weight: 600; }
.steps li.done { background: #10b981; color: #fff; }
.steps li + li { margin-left: 2px; }
h1 { font-size: 20px; margin: 0 0 16px; }
.btn { display: inline-block; padding: 10px 24px; background: #2563eb; color: #fff; border: 0; border-radius: 6px; cursor: pointer; font-size: 14px; text-decoration: none; }
.btn:hover { background: #1d4ed8; }
.btn.gray { background: #6b7280; }
.btn.gray:hover { background: #4b5563; }
.btn.danger { background: #ef4444; }
.btn.outline { background: #fff; color: #2563eb; border: 1px solid #2563eb; }
.btn.outline:hover { background: #eff6ff; }
.form-row { margin-bottom: 16px; }
.form-row label { display: block; font-size: 13px; color: #374151; margin-bottom: 6px; font-weight: 500; }
.form-row input[type=text], .form-row input[type=password], .form-row input[type=number], .form-row select, .form-row textarea {
    width: 100%; padding: 9px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; transition: border .2s;
}
.form-row input:focus, .form-row select:focus { outline: none; border-color: #2563eb; }
.form-row .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
.row2 { display: flex; gap: 12px; }
.row2 > * { flex: 1; }
.actions { display: flex; gap: 12px; margin-top: 24px; }
.alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 16px; font-size: 13px; }
.alert.ok  { background: #d1fae5; color: #065f46; }
.alert.err { background: #fee2e2; color: #991b1b; }
.alert.warn { background: #fef3c7; color: #92400e; }
.alert.info { background: #dbeafe; color: #1e40af; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
table th, table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
table th { background: #f9fafb; font-weight: 600; color: #374151; }
.check { color: #10b981; font-weight: 600; }
.cross { color: #ef4444; font-weight: 600; }
.code { background: #f3f4f6; padding: 8px 12px; border-radius: 4px; font-family: Menlo, monospace; font-size: 12px; word-break: break-all; }
.footer { text-align: center; color: #9ca3af; font-size: 12px; margin-top: 24px; }
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<div class="brand">AuthXphp</div>
<div class="sub">v<?= h(config('app.version') ?? '1.0.0') ?> · 配置驱动 · 无状态 · 即插即用</div>
<ul class="steps">
<?php foreach ($labels as $i => $lab): ?>
    <li class="<?= $i < $step ? 'done' : ($i == $step ? 'active' : '') ?>"><?= $i ?>. <?= h($lab) ?></li>
<?php endforeach; ?>
</ul>
        <?php
    }
    function renderFooter(): void { ?>
</div>
<div class="footer">AuthXphp 安装向导 · 嵌入式部署不影响现有网站</div>
</div>
</body>
</html>
        <?php
    }
}

// 已安装则跳走
$lockFile = __DIR__ . '/install.lock';
if (is_file($lockFile) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'finish.php') {
    // 仅当不在 finish 页时拦截
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($cur, ['finish.php'], true)) {
        $appBase = rtrim(config('app.base_path') ?: '', '/');
        $url = ($appBase ?: '') . '/';
        header('Location: ' . $url);
        exit;
    }
}
