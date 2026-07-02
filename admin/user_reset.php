<?php
/**
 * Admin 重置用户密码
 *
 * 安全方案：生成 12 位强随机密码（含大小写字母+数字），仅本次向管理员显示一次。
 * 用户下次登录后应通过"修改密码"流程立即修改。
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

// 从 POST body 获取参数
$curGuard = preg_replace('/[^a-z0-9_]/', '', (string)($_POST['guard'] ?? 'app'));
$g = Guard::driver($curGuard);
$id = (int)($_POST['id'] ?? 0);
$username = (string)($_POST['username'] ?? '');

// 生成强随机密码：12 位，包含大小写字母+数字+特殊字符，避免易混淆字符
function generateStrongPassword(int $length = 12): string
{
    $upper   = 'ABCDEFGHJKLMNPQRSTUVWXYZ';   // 去掉 I, O
    $lower   = 'abcdefghjkmnpqrstuvwxyz';   // 去掉 i, l
    $digits  = '23456789';                  // 去掉 0, 1
    $special = '!@#$%^&*';
    $all     = $upper . $lower . $digits . $special;

    // 确保至少包含 1 个大写、1 个小写、1 个数字、1 个特殊字符
    $pwd  = $upper[random_int(0, strlen($upper)   - 1)];
    $pwd .= $lower[random_int(0, strlen($lower)   - 1)];
    $pwd .= $digits[random_int(0, strlen($digits) - 1)];
    $pwd .= $special[random_int(0, strlen($special) - 1)];

    // 剩余长度随机填充
    for ($i = 4; $i < $length; $i++) {
        $pwd .= $all[random_int(0, strlen($all) - 1)];
    }

    // 打乱顺序
    $pwd = str_shuffle($pwd);

    // 防御性校验：确保包含所有类别
    if (!preg_match('/[A-Z]/', $pwd) || !preg_match('/[a-z]/', $pwd) ||
        !preg_match('/[0-9]/', $pwd) || !preg_match('/[!@#$%^&*]/', $pwd)) {
        return generateStrongPassword($length); // 递归重新生成
    }

    return $pwd;
}

$newPassword = generateStrongPassword(12);
$g->update($id, [$g->config('password_field') => $newPassword]);

// 触发钩子（密码被管理员重置）
if (class_exists('Hook')) {
    Hook::trigger('admin.password_reset', [
        'uid'          => $id,
        'guard'        => $curGuard,
        'by_admin'     => Auth::id(),
        'by_guard'     => Auth::guard(),
    ]);
}

// 将新密码存到 session 中，PRG 模式在 users.php 显示一次
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$_SESSION['authxphp_reset_pwd'] = [
    'uid'         => $id,
    'guard'       => $curGuard,
    'username'    => $username,
    'new_password'=> $newPassword,
    'expires_at'  => time() + 300, // 300 秒（5 分钟）后失效，平衡可用性与安全性
];

header('Location: ' . adminUrl('users.php', ['guard' => $curGuard, 'show_reset' => 1]));
exit;
