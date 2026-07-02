<?php
/**
 * Admin 启停用户
 */
require __DIR__ . '/_bootstrap.php';

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appBase() . '/admin/error.php?code=405&msg=' . urlencode('仅支持POST请求'));
    exit;
}

// CSRF 验证
if (!csrfVerify($_POST['_csrf'] ?? '')) {
    header('Location: ' . appBase() . '/admin/error.php?code=403&msg=' . urlencode('CSRF验证失败'));
    exit;
}

// 从 POST body 取参数
$curGuard = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['guard'] ?? 'app'));
$g = Guard::driver($curGuard);
$id = (int)($_POST['id'] ?? 0);

// 直接查数据库获取用户（包括禁用的），绕过 byId() 的 status 过滤
$row = Db::table($g->config('table'))
    ->where($g->config('primary_key'), $id)
    ->first();

if (!$row) {
    header('Location: ' . appBase() . '/admin/error.php?code=404&msg=' . urlencode('用户不存在'));
    exit;
}

$cur = (int)($row[$g->config('status_field')] ?? 1);
$g->update($id, [$g->config('status_field') => $cur === 1 ? 0 : 1]);
header('Location: ' . adminUrl('users.php', ['guard' => $curGuard, 'msg' => 'ok']));
exit;