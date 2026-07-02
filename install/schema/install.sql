-- AuthXphp 默认表结构
-- 仅在用户选择"创建默认表"时执行；若用户要对接已有业务表，可跳过本脚本。

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- users（前端用户）
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(64)  NOT NULL COMMENT '账号（可改为 email/phone）',
    `password`    VARCHAR(255) NOT NULL COMMENT '密码（password_hash / bcrypt）',
    `nickname`    VARCHAR(64)  DEFAULT NULL,
    `email`       VARCHAR(128) DEFAULT NULL,
    `phone`       VARCHAR(20)  DEFAULT NULL,
    `avatar`      VARCHAR(255) DEFAULT NULL,
    `role`        VARCHAR(32)  DEFAULT 'user' COMMENT '角色',
    `permissions` TEXT         DEFAULT NULL COMMENT '权限（JSON 数组）',
    `status`      TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用',
    `last_login_at` DATETIME   DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='前端用户表';

-- ----------------------------------------------------------------------------
-- admins（后台管理员）
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username`    VARCHAR(64)  NOT NULL,
    `password`    VARCHAR(255) NOT NULL,
    `realname`    VARCHAR(64)  DEFAULT NULL,
    `email`       VARCHAR(128) DEFAULT NULL,
    `avatar`      VARCHAR(255) DEFAULT NULL,
    `role`        VARCHAR(32)  NOT NULL DEFAULT 'admin' COMMENT 'admin / super',
    `permissions` TEXT         DEFAULT NULL,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at` DATETIME   DEFAULT NULL,
    `last_login_ip` VARCHAR(64) DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员表';

-- ----------------------------------------------------------------------------
-- auth_logs（可选：登录 / 登出 / 错误审计）
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uid`        INT UNSIGNED    DEFAULT NULL,
    `guard`      VARCHAR(32)     NOT NULL DEFAULT 'app',
    `event`      VARCHAR(32)     NOT NULL COMMENT 'login.success / login.failed / logout / token.expired ...',
    `ip`         VARCHAR(64)     DEFAULT NULL,
    `user_agent` VARCHAR(255)    DEFAULT NULL,
    `payload`    TEXT            DEFAULT NULL COMMENT 'JSON',
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_guard_event` (`guard`, `event`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='认证审计日志';

SET FOREIGN_KEY_CHECKS = 1;
