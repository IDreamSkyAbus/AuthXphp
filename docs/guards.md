# AuthXphp Guard 配置文档

Guard 是 AuthXphp 的核心抽象：一个 Guard 映射到一张业务表，Auth 系统对业务表结构完全无感。

## 配置文件位置

`config/guards.php`

## 完整字段说明

```php
return [
    'default' => 'app',  // 默认 Guard 名称

    'guards' => [

        'app' => [
            'name'              => '前端用户',     // 显示名
            'table'             => 'users',         // 表名（必填）
            'primary_key'       => 'id',            // 主键（必填）
            'account_field'     => 'username',      // 账号字段（必填）
            'password_field'    => 'password',      // 密码字段（必填）
            'status_field'      => 'status',        // 状态字段（1=正常 0=禁用，可选）
            'role_field'        => 'role',          // 角色字段（RBAC，可选）
            'permissions_field' => 'permissions',   // 权限字段（JSON 数组，可选）
            'password_algo'     => 'password_hash', // 密码算法
            'registerable'      => true,            // 是否开放注册 API
            'extra_fields'      => ['id','username','nickname','email','role','status','created_at'],
        ],

    ],
];
```

### 字段详解

| 字段 | 必填 | 说明 |
|------|------|------|
| `name` | 否 | 后台显示用，便于区分 |
| `table` | 是 | 表名（不带前缀），必须真实存在 |
| `primary_key` | 是 | 主键字段名，通常 `id` |
| `account_field` | 是 | 登录账号字段，可以是 username / email / phone / 任意字段 |
| `password_field` | 是 | 密码字段。**注意：传出 user 时该字段会被剔除** |
| `status_field` | 否 | 状态字段。若存在且值为 0，则视为账号禁用（登录会被拒） |
| `role_field` | 否 | 角色字段。Token 中会写入 `data.role` |
| `permissions_field` | 否 | 权限字段。字符串 JSON 也会被自动 decode |
| `password_algo` | 否 | 见下方 |
| `registerable` | 否 | `true` 时 `/api/register` 接受注册 |
| `extra_fields` | 否 | 限制登录返回的 user 字段，避免泄露敏感数据 |

### 密码算法 `password_algo`

| 值 | 说明 | 兼容性 |
|----|------|--------|
| `password_hash`（默认） | PHP `password_hash` (bcrypt) | PHP 5.5+ |
| `bcrypt` | 同 `password_hash` | 同上 |
| `md5` | `md5($plain)` | 老系统 |
| `sha1` | `sha1($plain)` | 老系统（不推荐） |
| `plain` | 明文比对 | 不推荐 |

## 多 Guard 实战

### 场景 1：手机号登录

```php
'app_mobile' => [
    'table'          => 'users',
    'primary_key'    => 'id',
    'account_field'  => 'phone',  // 用手机号作为账号
    'password_field' => 'password',
    'password_algo'  => 'password_hash',
],
```

调用：`Auth::login('13800000000', 'pwd', 'app_mobile')`

### 场景 2：邮箱登录

```php
'app_email' => [
    'table'          => 'users',
    'account_field'  => 'email',
    'password_field' => 'password',
    'password_algo'  => 'password_hash',
],
```

### 场景 3：后台 + 前端隔离

```php
'guards' => [
    'app' => [
        'table' => 'users', 'account_field' => 'username',
        // ...
    ],
    'admin' => [
        'table' => 'admins', 'account_field' => 'username',
        'extra_fields' => ['id','username','realname','role','last_login_at'],
        'role_field' => 'role',
        'password_algo' => 'password_hash',
    ],
    'api_partner' => [
        'table' => 'partners', 'account_field' => 'api_key',
        'password_field' => 'api_secret',
        'password_algo' => 'plain', // 合作伙伴密钥一般是明文
    ],
],
```

### 场景 4：对接老系统（MD5）

```php
'old_user' => [
    'table'          => 'old_members',
    'account_field'  => 'login_name',
    'password_field' => 'passwd',
    'password_algo'  => 'md5',  // 兼容老系统
    'status_field'   => 'is_active',
],
```

## 多表关联查询

Guard 仅负责"鉴权三要素"，业务查询仍由业务层处理。如需 JOIN，**不要**写死在 Guard 配置里——直接在业务代码中：

```php
// 受保护 API 中
Route::get('/api/order/list', function () {
    $uid = Auth::id();
    $orders = Db::table('orders')
        ->where('user_id', $uid)
        ->orderBy('id', 'DESC')
        ->get();
    Response::ok(['orders' => $orders]);
}, ['roles' => ['user', 'admin']]);
```

## 自定义 password_algo

如需自定义算法（如 `argon2id`、双因素），在 Guard 源码中扩展：

```php
// guard/index.php 中追加 case
case 'argon2id':
    return password_verify($plain, $stored);
// 配合 password_hash($p, PASSWORD_ARGON2ID)
```

## 校验 / 调试

```php
// 列出所有 Guard
print_r(Guard::names());

// 取 Guard 配置
$g = Guard::driver('app');
print_r($g->config());

// 测试登录（不签 token）
$user = Guard::driver('app')->attempt('alice', 'pwd');
print_r($user);
```

## 错误提示

当 Guard 配置错误时，系统抛出友好异常而非 SQL 错误：

| 错误 | 提示文案 |
|------|---------|
| Guard 不存在 | `Auth 组件初始化失败：未找到 Guard 配置 'xxx'` |
| 表不存在 | `Auth 组件初始化失败：找不到配置中指定的用户表 my_users`（Guard: app） |
| 必填字段缺失 | `Auth 组件初始化失败：Guard 'xxx' 缺少配置项 'account_field'` |
| 数据库连接失败 | `Auth 组件初始化失败：无法连接数据库（...）` |

## 嵌入式部署下的 Guard

**AuthXphp 嵌入式部署不影响现有网站**。Guard 引用的表既可以是 AuthXphp 自己创建的（`install/schema/install.sql`），也可以是网站已有业务表。

例如：网站已有 `member` 表要复用：

```php
'member' => [
    'table'          => 'member',           // 复用现有表
    'primary_key'    => 'member_id',
    'account_field'  => 'mobile',
    'password_field' => 'pass',
    'password_algo'  => 'md5',              // 兼容老算法
    'role_field'     => 'group_id',         // 把 group_id 视作 role
],
```

无需修改原有表结构。
