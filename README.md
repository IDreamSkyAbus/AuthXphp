# AuthXphp

> 配置驱动 · 无状态 · 即插即用的 PHP 认证授权组件

AuthXphp 是一款可作为网站子目录嵌入式运行，也可作为独立站运行的 PHP 认证授权微框架。

- **零依赖**：仅需 PHP 7.4+ / MySQL / PDO；JWT 通过 Composer 引入 `firebase/php-jwt`
- **配置驱动**：通过 `config/guards.php` 映射任意业务表 + 任意字段名
- **无状态**：JWT (HS256) 自包含校验，签名校验不查库
- **多 Guard**：同一套系统支持任意数量的用户体系（前端 / 后台 / 第三方）
- **自带 UI**：layuiadmin 风格 WEB 管理后台，开箱即用
- **可扩展**：生命周期钩子 + RBAC 预留 + 黑名单 + 第三方登录
- **不影响现有网站**：默认作为子目录运行，资源路径自动适配

## 快速开始

### 1. 拷贝到网站子目录

将整个 `AuthXphp/` 目录上传到你的网站任意子目录，例如：

```
/var/www/html/authxphp/    # Linux
D:\WebRoot\authxphp\       # Windows
```

或部署到独立站点根目录（安装时可切换运行模式）。

### 2. 安装 Composer 依赖

```bash
cd /path/to/AuthXphp
composer install
```

> 如果你无法使用 Composer，也可以直接将 `firebase/php-jwt/src/` 拷贝到 `vendor/firebase/php-jwt/src/`，并在 `vendor/autoload.php` 注册自动加载。

### 3. Web 向导式安装

浏览器访问：

```
http://your-host/authxphp/install/
```

按 6 步提示完成：

1. 环境检测
2. 数据库配置
3. 导入默认表（可跳过，使用自己的表）
4. Guard 字段映射
5. 运行模式 + 超级管理员
6. 完成

### 4. 登录后台

```
http://your-host/authxphp/admin/login.php
```

### 5. 接入业务

参考 [docs/api.md](docs/api.md) 了解 API 详情。

## 目录结构

```
AuthXphp/
├── index.php             # 统一入口
├── install/              # 6 步安装向导
├── config/               # 配置中心
├── auth/                 # 核心门面（Auth 类）
├── token/                # JWT 引擎
├── guard/                # Guard 抽象
├── db/                   # 数据层
├── middleware/           # 中间件
├── route/                # 路由
├── hook/                 # 事件钩子
├── captcha/              # 图形验证码
├── ratelimit/            # 频率限制
├── response/             # 统一响应
├── log/                  # 日志
├── api/                  # 公开 API
├── admin/                # WEB 后台
├── docs/                 # 文档
├── composer.json
└── .htaccess             # URL 重写
```

## 文档

- [API 完整文档](docs/api.md)
- [事件钩子文档](docs/hooks.md)
- [Guard 配置文档](docs/guards.md)

## 系统要求

| 项目    | 版本                                |
| ----- | --------------------------------- |
| PHP   | >= 7.4                            |
| MySQL | >= 5.7                            |
| 扩展    | PDO / pdo\_mysql / OpenSSL / JSON |
| 可选    | GD（图形验证码）/ Redis（黑名单 / 限流）        |

## 许可证

MIT
