<?php
/**
 * AuthXphp 多 Guard 配置模板
 *
 * 支持任意数量的 Guard（app / admin / api / 自定义）。
 * 每个 Guard 映射到任意自定义表和字段，Auth 系统对此完全无感。
 */

return [
    // 默认 Guard 名称（不传 guard 参数时使用）
    'default' => 'app',

    'guards' => [

        'app' => [
            'name'           => '前端用户',
            'table'          => 'users',
            'primary_key'    => 'id',
            'account_field'  => 'username',     // 账号字段（手机号/邮箱/用户名均可）
            'password_field' => 'password',     // 密码字段
            'status_field'   => 'status',       // 状态字段（1=正常 0=禁用，可选）
            'extra_fields'   => ['id', 'username', 'nickname', 'email', 'role', 'status', 'created_at'],
            'role_field'     => 'role',         // 角色字段（RBAC 用）
            'permissions_field' => 'permissions', // 权限字段（数组，JSON 存储）
            'password_algo'  => 'password_hash', // password_hash | md5 | sha1
            'registerable'   => true,           // 是否开放注册 API
        ],

        'admin' => [
            'name'           => '后台管理员',
            'table'          => 'admins',
            'primary_key'    => 'id',
            'account_field'  => 'username',
            'password_field' => 'password',
            'status_field'   => 'status',
            'extra_fields'   => ['id', 'username', 'realname', 'role', 'status', 'last_login_at', 'created_at'],
            'role_field'     => 'role',
            'permissions_field' => 'permissions',
            'password_algo'  => 'password_hash',
            'registerable'   => false,
        ],

    ],
];