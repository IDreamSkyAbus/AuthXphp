<?php
/**
 * AuthXphp 自定义钩子注册示例
 *
 * 把本文件改名（如 custom_hooks.php）后在 index.php 入口 require 即可。
 * 也可以在 hook/index.php 末尾追加 Hook::on(...) 调用。
 */

require_once __DIR__ . '/../auth/index.php';

// ---------------------------------------------------------------------------
// 1. 登录成功后：更新最后登录时间 + 写审计日志
// ---------------------------------------------------------------------------
Hook::on(Hook::EVENT_LOGIN_SUCCESS, function ($p) {
    $guard = $p['guard'] ?? '';
    $cfg   = config("guards.guards.$guard");
    if (!$cfg) {
        return;
    }
    $table = $cfg['table'];
    $pk    = $cfg['primary_key'];

    $update = ['last_login_at' => date('Y-m-d H:i:s')];
    if (isset($cfg['extra_fields']) && in_array('last_login_ip', $cfg['extra_fields'])) {
        $update['last_login_ip'] = Route::clientIp();
    }
    try {
        Db::table($table)->where($pk, $p['uid'])->update($update);
    } catch (Throwable $e) {
        Log::warn('更新 last_login 失败', ['error' => $e->getMessage()]);
    }

    if (Db::tableExists('auth_logs')) {
        Db::table('auth_logs')->insert([
            'uid'        => $p['uid'],
            'guard'      => $guard,
            'event'      => 'login.success',
            'ip'         => Route::clientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            'payload'    => json_encode(['role' => $p['role'] ?? null], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}, 100); // 高优先级

// ---------------------------------------------------------------------------
// 2. 登录失败后：审计 + 失败次数统计
// ---------------------------------------------------------------------------
Hook::on(Hook::EVENT_LOGIN_FAILED, function ($p) {
    Log::info('登录失败', $p);

    if (Db::tableExists('auth_logs')) {
        Db::table('auth_logs')->insert([
            'uid'        => null,
            'guard'      => $p['guard'] ?? 'app',
            'event'      => 'login.failed',
            'ip'         => Route::clientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            'payload'    => json_encode([
                'account' => $p['account'] ?? '',
                'reason'  => $p['reason'] ?? '',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}, 100);

// ---------------------------------------------------------------------------
// 3. 密码修改后：吊销当前 Token，强制重新登录
// ---------------------------------------------------------------------------
Hook::on(Hook::EVENT_PASSWORD_CHANGED, function ($p) {
    Log::info('密码已修改，强制重新登录', $p);
    if (Db::tableExists('auth_logs')) {
        Db::table('auth_logs')->insert([
            'uid'        => $p['uid'],
            'guard'      => $p['guard'] ?? 'app',
            'event'      => 'password.changed',
            'ip'         => Route::clientIp(),
            'user_agent' => '',
            'payload'    => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}, 100);

// ---------------------------------------------------------------------------
// 4. Token 过期 / 吊销：埋点（用于将来接入统计系统）
// ---------------------------------------------------------------------------
Hook::on(Hook::EVENT_TOKEN_EXPIRED, function ($p) {
    Log::info('Token 过期', $p);
}, 5);
Hook::on(Hook::EVENT_TOKEN_REVOKED, function ($p) {
    Log::info('Token 吊销', $p);
}, 5);
