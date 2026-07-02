# AuthXphp 事件钩子文档

AuthXphp 在关键节点暴露事件，开发者可注册回调实现业务解耦。所有事件通过 `Hook::on($event, $cb, $priority = 10)` 注册，`Hook::trigger()` 触发，异常不会影响主流程。

## 事件清单

| 事件名 | 触发时机 | payload 字段 |
|--------|----------|------------|
| `before.login` | 登录验证之前 | `account`, `guard` |
| `login.success` | 登录成功、token 已签发 | `uid`, `guard`, `role` |
| `login.failed` | 登录失败（账号不存在/密码错/Guard 异常） | `account`, `guard`, `reason`, `error?` |
| `logout` | 登出后 | `uid`, `guard` |
| `token.verified` | Token 校验通过 | `jti`, `uid`, `guard` |
| `token.expired` | Token 过期 | `reason` |
| `token.revoked` | Token 被吊销 | `jti`, `ttl` |
| `register.success` | 新用户注册成功 | `uid`, `guard` |
| `password.changed` | 用户修改密码 | `uid`, `guard` |

`payload` 还会自动附加：
- `_event`: 事件名
- `_ts`: 触发时间戳

## 优先级

`Hook::on($event, $cb, $priority = 10)`：数字越大越先执行。

## 注册位置

推荐在 `auth/index.php` 加载完成后注册（即 `require __DIR__.'/auth/index.php'` 之后），或写在独立的 `bootstrap.php` 中。

## 完整示例

### 示例 1：登录成功时写审计日志 + 更新最后登录时间

```php
// 在业务入口文件（如 index.php）require auth 后追加：
Hook::on('login.success', function ($p) {
    $guard = $p['guard'];
    $cfg   = config("guards.guards.$guard");
    $table = $cfg['table'];
    $pk    = $cfg['primary_key'];
    $laf   = 'last_login_at';
    $lip   = 'last_login_ip';

    Db::table($table)
        ->where($pk, $p['uid'])
        ->update([
            $laf => date('Y-m-d H:i:s'),
            $lip => Route::clientIp(),
        ]);

    // 写审计日志
    if (Db::tableExists('auth_logs')) {
        Db::table('auth_logs')->insert([
            'uid'        => $p['uid'],
            'guard'      => $p['guard'],
            'event'      => 'login.success',
            'ip'         => Route::clientIp(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
});
```

### 示例 2：登录失败告警（连续 5 次后告警）

```php
// 用文件计数器（无需 Redis）
Hook::on('login.failed', function ($p) {
    $key  = 'login_fail_' . $p['account'];
    $file = AUTHXPHP_STORAGE_PATH . '/limits/' . md5($key) . '.json';
    $data = is_file($file) ? (array)json_decode(file_get_contents($file), true) : [];
    $data = array_filter($data, fn($t) => $t > time() - 600);
    $data[] = time();
    @file_put_contents($file, json_encode(array_values($data)));
    if (count($data) >= 5) {
        Log::warn('账号连续登录失败 ≥ 5 次', $p);
        // 此处可对接短信 / 邮件 / 企业微信通知
    }
});
```

### 示例 3：注册成功后自动分配默认角色

```php
Hook::on('register.success', function ($p) {
    $guard = $p['guard'];
    $cfg   = config("guards.guards.$guard");
    Db::table($cfg['table'])
        ->where($cfg['primary_key'], $p['uid'])
        ->update(['role' => 'user', 'status' => 1]);
});
```

### 示例 4：Token 失效时清理资源

```php
Hook::on('token.expired', function ($p) {
    Log::info('Token 过期', $p);
});
Hook::on('token.revoked', function ($p) {
    Log::info('Token 吊销', $p);
});
```

### 示例 5：监听多个事件 + 优先级

```php
// 优先级 100 > 10，先执行
Hook::on('login.success', function ($p) {
    // 关键：审计 + 安全
    audit_log($p);
}, 100);

Hook::on('login.success', function ($p) {
    // 次要：发欢迎邮件
    send_welcome_email($p['uid']);
}, 10);
```

## 注销事件

```php
$cb = function ($p) { /* ... */ };
Hook::on('login.success', $cb);
// 注销单个回调
Hook::off('login.success', $cb);
// 注销该事件所有回调
Hook::off('login.success');
```

## 异常安全

回调内部异常会被捕获并写入 `storage/logs/`，**不会**影响主流程。

```php
Hook::on('login.success', function ($p) {
    throw new \Exception('demo');
    // 主流程（响应）不受影响；错误会出现在日志
});
```

## 查看已注册钩子

登录后台 → "事件钩子" 页面查看实时注册数量；也可在代码中：

```php
print_r(Hook::events());
// 或
echo Hook::count('login.success');
```
