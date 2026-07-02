<?php
/**
 * AuthXphp 公开 API 路由
 *
 * 路由以 Route::get / Route::post 注册，由 index.php 统一调度。
 *
 * 元数据 meta：
 *   - public      公开访问（不需要 Token）
 *   - guard       期望的 Guard 名（与 token.guard 字段一致）
 *   - roles       允许的角色（数组）
 *   - permissions 必需的权限（数组）
 *   - captcha     true 表示需要图形验证码
 *   - rate_limit  ['key'=>'login','max'=>10,'window'=>60]
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../response/index.php';
require_once __DIR__ . '/../auth/index.php';
require_once __DIR__ . '/../route/index.php';
require_once __DIR__ . '/../captcha/index.php';

// 公共：不限流
Route::group('/api', function () {

    // 图形验证码
    Route::get('/captcha', function () {
        Response::ok(Captcha::generate());
    }, ['public' => true]);

    // 登录
    Route::post('/login', function () {
        $body = Route::jsonBody();
        $account  = trim((string)($body['account'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $guard    = $body['guard'] ?? null;

        if ($account === '' || $password === '') {
            Response::badRequest('账号和密码不能为空', 40001);
        }
        $r = Auth::login($account, $password, $guard);
        Response::ok($r);
    }, [
        'public'     => true,
        'rate_limit' => ['key' => 'login', 'max' => 10, 'window' => 60],
    ]);

    // 登录（带验证码）
    Route::post('/login-captcha', function () {
        $body = Route::jsonBody();
        $account  = trim((string)($body['account'] ?? ''));
        $password = (string)($body['password'] ?? '');
        $guard    = $body['guard'] ?? null;
        $ck       = (string)($body['captcha_key'] ?? '');
        $cc       = (string)($body['captcha_code'] ?? '');

        if ($ck === '' || $cc === '' || !Captcha::verify($ck, $cc)) {
            Response::badRequest('验证码错误或已过期', 40003);
        }
        if ($account === '' || $password === '') {
            Response::badRequest('账号和密码不能为空', 40001);
        }
        $r = Auth::login($account, $password, $guard);
        Response::ok($r);
    }, [
        'public'     => true,
        'rate_limit' => ['key' => 'login', 'max' => 5, 'window' => 60],
    ]);

    // 刷新 token
    Route::post('/refresh', function () {
        $body = Route::jsonBody();
        $rt = (string)($body['refresh_token'] ?? '');
        if ($rt === '') {
            Response::badRequest('refresh_token 不能为空', 40001);
        }
        $r = Auth::refresh($rt);
        Response::ok($r);
    }, [
        'public'     => true,
        'rate_limit' => ['key' => 'refresh', 'max' => 60, 'window' => 60],
    ]);

    // 登出
    Route::post('/logout', function () {
        Auth::logout();
        Response::ok(null, '已退出登录');
    }, [
        'public' => false, // 需要登录
    ]);

    // 当前用户信息
    Route::get('/me', function () {
        Response::ok([
            'user'  => Auth::user(),
            'guard' => Auth::guard(),
            'id'    => Auth::id(),
        ]);
    }, [
        'public'     => false,
        'rate_limit' => ['key' => 'me', 'max' => 120, 'window' => 60],
    ]);

    // 注册（默认 guard.registerable=true 时开放）
    Route::post('/register', function () {
        $body = Route::jsonBody();
        $guard = $body['guard'] ?? null;
        $id = Auth::register($body, $guard);
        Response::ok(['id' => $id], '注册成功');
    }, [
        'public'     => true,
        'rate_limit' => ['key' => 'register', 'max' => 5, 'window' => 60],
    ]);

    // 修改密码
    Route::post('/password/change', function () {
        $body = Route::jsonBody();
        $old = (string)($body['old_password'] ?? '');
        $new = (string)($body['new_password'] ?? '');
        if ($old === '' || $new === '') {
            Response::badRequest('原密码和新密码不能为空', 40001);
        }
        if (strlen($new) < 6) {
            Response::badRequest('新密码至少 6 位', 40002);
        }
        if (!Auth::changePassword($old, $new)) {
            Response::fail(42302, '原密码错误');
        }
        Response::ok(null, '密码已更新，请重新登录');
    }, [
        'public'     => false,
        'rate_limit' => ['key' => 'pwchange', 'max' => 10, 'window' => 60],
    ]);

    // 角色 / 权限校验示例接口
    Route::get('/rbac/check', function () {
        Response::ok([
            'has_role'   => Auth::hasRole(['super', 'admin']),
            'can'        => Auth::can('user.list'),
        ]);
    }, [
        'public' => false,
        'roles'  => ['super', 'admin', 'user'],
    ]);

});
