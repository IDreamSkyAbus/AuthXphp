# AuthXphp 完整 API 文档

> 版本：1.0.0
> 基础 URL：`<BASE_PATH>` 即部署子目录前缀（如 `/authxphp`，独立站模式下为 ``）

## 通用约定

### 响应格式
所有 API 响应均为 JSON，结构如下：

```json
{
  "code": 0,
  "msg": "ok",
  "data": null,
  "request_id": "abc123",
  "ts": 1700000000
}
```

### 业务错误码
| code    | 含义                  | HTTP |
|---------|----------------------|------|
| 0       | 成功                  | 200  |
| 40000   | 参数错误              | 400  |
| 40001   | 缺少必填参数          | 400  |
| 40002   | 参数值无效            | 400  |
| 40003   | 验证码错误或已过期     | 400  |
| 40100   | 未登录 / 缺少 Token    | 401  |
| 40101   | Token 已过期           | 401  |
| 40102   | Token 无效             | 401  |
| 40103   | Token 已被吊销         | 401  |
| 40104   | Guard 不匹配          | 401  |
| 40300   | 无权限                | 403  |
| 40301   | 角色不符              | 403  |
| 40302   | 权限不足              | 403  |
| 40400   | 资源不存在            | 404  |
| 42300   | 账号已禁用            | 423  |
| 42301   | 账号不存在            | 401  |
| 42302   | 账号或密码错误         | 401  |
| 42900   | 请求过于频繁          | 429  |
| 50000   | 服务器内部错误        | 500  |
| 50300   | 系统未安装            | 503  |

### 请求头
| Header                | 必填 | 说明                              |
|----------------------|----|----------------------------------|
| `Content-Type`        | 是  | 固定 `application/json`             |
| `Authorization`       | 受保护接口必填 | 格式 `Bearer <access_token>` |
| `X-Request-Id`        | 否  | 自定义 request id，便于日志追踪      |

---

## 1. 认证接口

### 1.1 登录

**POST** `<BASE>/api/login`

请求体：
```json
{
  "account":  "alice",
  "password": "secret123",
  "guard":    "app"      // 可选，缺省用 config('guards.default')
}
```

成功响应（200）：
```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 3600,
    "token_type": "Bearer",
    "user": {
      "id": 1,
      "username": "alice",
      "nickname": "小爱",
      "email": "alice@example.com",
      "role": "user",
      "status": 1,
      "created_at": "2026-07-01 10:00:00"
    }
  }
}
```

失败：
- `40001` 账号或密码为空
- `42302` 账号或密码错误
- `42900` 1 分钟内请求超过 10 次

cURL 示例：
```bash
curl -X POST http://localhost/authxphp/api/login \
  -H "Content-Type: application/json" \
  -d '{"account":"alice","password":"secret123"}'
```

### 1.2 登录（带验证码）

**POST** `<BASE>/api/login-captcha`

请求体：
```json
{
  "account":      "alice",
  "password":     "secret123",
  "guard":        "app",
  "captcha_key":  "5f4a...",
  "captcha_code": "A8B3"
}
```

### 1.3 刷新 Token

**POST** `<BASE>/api/refresh`

请求体：
```json
{ "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." }
```

成功响应：与登录一致（`token` + `refresh_token` + `expires_in` + `user`）。原 refresh_token 会被自动吊销。

### 1.4 登出

**POST** `<BASE>/api/logout` （需登录）

成功响应：`{ "code": 0, "msg": "已退出登录" }`

如开启 Token 黑名单（`config/jwt.php` 中 `blacklist.enabled = true`），当前 jti 会被加入黑名单。

### 1.5 当前用户信息

**GET** `<BASE>/api/me` （需登录）

```bash
curl http://localhost/authxphp/api/me \
  -H "Authorization: Bearer <token>"
```

成功响应：
```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "user": { "id": 1, "username": "alice", "role": "user", "...": "..." },
    "guard": "app",
    "id": 1
  }
}
```

### 1.6 修改密码

**POST** `<BASE>/api/password/change` （需登录）

请求体：
```json
{
  "old_password": "secret123",
  "new_password": "newpass456"
}
```

成功响应：`{ "code": 0, "msg": "密码已更新，请重新登录" }`

### 1.7 注册（需 guard.registerable=true）

**POST** `<BASE>/api/register`

请求体（字段取决于 Guard 配置中的 `account_field` / `password_field`）：
```json
{
  "guard":   "app",         // 可选
  "username": "newuser",
  "password": "secret123",
  "email":    "x@x.com"
}
```

成功响应：`{ "code": 0, "msg": "注册成功", "data": { "id": 42 } }`

---

## 2. 工具接口

### 2.1 获取图形验证码

**GET** `<BASE>/api/captcha`

成功响应：
```json
{
  "code": 0,
  "msg": "ok",
  "data": {
    "key":   "ab12cd34ef56ab12",
    "image": "data:image/png;base64,iVBORw0KGgo...",
    "ttl":   300
  }
}
```

### 2.2 RBAC 检查（示例）

**GET** `<BASE>/api/rbac/check` （需登录）

成功响应：
```json
{
  "code": 0,
  "data": {
    "has_role": true,
    "can":      true
  }
}
```

---

## 3. 后台管理

### 3.1 登录

访问 `<BASE>/admin/login.php` → 输入账号密码。

后台使用独立的 session 保存 Token；API Token 与后台 Token 互不影响。

### 3.2 路由表

| 路径 | 功能 | 权限 |
|------|------|------|
| `/admin/` | 仪表盘 | 登录 |
| `/admin/users.php?guard=app` | 用户管理（前端） | 登录 |
| `/admin/users.php?guard=admin` | 管理员 | super |
| `/admin/user_edit.php?id=1` | 编辑用户 | 登录 |
| `/admin/user_toggle.php?id=1` | 启停 | 登录 |
| `/admin/user_reset.php?id=1` | 重置密码为 123456 | super |
| `/admin/guards.php` | Guard 配置只读 | 登录 |
| `/admin/hooks.php` | 已注册钩子 | 登录 |
| `/admin/logs.php` | 审计日志 | 登录 |
| `/admin/settings.php` | 系统设置 | 登录 |
| `/admin/logout.php` | 退出 | - |

### 3.3 嵌入式部署

如 AuthXphp 部署在 `http://example.com/authxphp/`：

- API 基础 URL：`http://example.com/authxphp/api/...`
- 后台入口：`http://example.com/authxphp/admin/login.php`
- 静态资源：`http://example.com/authxphp/admin/assets/...`

路径在 `config/app.php` 的 `run_mode` 控制：
- `embedded`（默认）：从 `SCRIPT_NAME` 自动推导 BASE_PATH
- `standalone`：BASE_PATH 强制为 ``，适合独立站 / 专属子域

---

## 4. PHP SDK 用法（同一进程内）

如在业务代码中直接使用 SDK：

```php
require_once '/path/to/AuthXphp/index.php';

// 登录
$res = Auth::login('alice', 'secret123', 'app');
// 或
$res = Auth::attempt('alice', 'secret123');

// 校验 Token
try {
    $r = Jwt::verify($token, 'app');
    $payload = $r['payload'];
    $data    = $r['data'];  // ['uid'=>1, 'guard'=>'app', 'role'=>'user', ...]
} catch (TokenExpiredException $e) {
    // 40101
}

// 第三方登录（直接签发 Token，不走密码）
$res = Auth::issueById($userId, 'app');

// 注册
$id = Auth::register([
    'username' => 'bob',
    'password' => 'secret123',
    'email'    => 'bob@x.com',
], 'app');

// 角色 / 权限检查（需先 Jwt::verify 并 Auth::setCurrent）
Auth::setCurrent($user, 'app', $jti, $payload);
if (Auth::hasRole('admin')) { /* ... */ }
if (Auth::can('user.delete')) { /* ... */ }

// 注册事件钩子
Auth::on('login.success', function ($p) {
    Log::info('用户登录', $p);
});
```

---

## 5. 自定义路由

可在 `api/index.php` 末尾追加业务路由：

```php
Route::group('/api', function () {

    // 业务接口示例：受保护、要求 admin 角色
    Route::get('/order/list', function () {
        Response::ok(['orders' => Db::table('orders')->get()]);
    }, [
        'roles' => ['admin', 'super'],
        'rate_limit' => ['key' => 'order', 'max' => 60, 'window' => 60],
    ]);

    // 公开接口
    Route::get('/public/stats', function () {
        Response::ok(['users' => Db::table('users')->count()]);
    }, ['public' => true]);

});
```

---

## 6. 错误排查

| 现象 | 原因 | 解决 |
|------|------|------|
| 503 系统未安装 | 未生成 `install.lock` | 访问 `/install/` 完成安装 |
| 40101 Token 已过期 | access_token TTL 到期 | 用 refresh_token 换新 |
| 40104 Guard 不匹配 | 用 app 的 token 访问 admin 接口 | 用正确的 guard 重新登录 |
| 42302 账号或密码错误 | 账号不存在 / 密码错误 / 账号被禁用 | 确认账号状态 |
| 50001 数据库错误 | 找不到表 / 连接失败 | 检查 `config/database.php` + `config/guards.php` |
| 50002 配置错误 | Guard 配置缺失 | 检查 `config/guards.php` |

---

## 7. 安装

参见 [安装向导 README](../README.md) 或直接访问 `<BASE>/install/`。

最低要求：PHP 7.4+ / MySQL 5.7+ / PDO / OpenSSL / GD（验证码，可选）。
