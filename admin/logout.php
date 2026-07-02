<?php
/**
 * Admin 退出登录
 */
require __DIR__ . '/_bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
unset($_SESSION['authxphp_admin_token']);
Auth::logout();
header('Location: ' . adminUrl('login.php'));
exit;
