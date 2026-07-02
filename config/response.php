<?php
/**
 * AuthXphp 统一响应规范
 *
 * code 字段语义：
 *   0           成功
 *   40000-40099 参数错误
 *   40100-40199 未登录 / Token 问题
 *   40300-40399 无权限
 *   40400-40499 资源不存在
 *   42300-42399 账号被锁定
 *   42900-42999 频率限制
 *   50000-50099 服务器内部错误
 *   50300-50399 系统未安装
 */

return [
    // 业务码
    'codes' => [
        'SUCCESS'              => 0,
        'BAD_REQUEST'          => 40000,
        'MISSING_PARAM'        => 40001,
        'INVALID_PARAM'        => 40002,
        'INVALID_CAPTCHA'      => 40003,
        'UNAUTHORIZED'         => 40100,
        'TOKEN_EXPIRED'        => 40101,
        'TOKEN_INVALID'        => 40102,
        'TOKEN_REVOKED'        => 40103,
        'GUARD_MISMATCH'       => 40104,
        'FORBIDDEN'            => 40300,
        'ROLE_REQUIRED'        => 40301,
        'PERMISSION_REQUIRED'  => 40302,
        'NOT_FOUND'            => 40400,
        'ACCOUNT_DISABLED'     => 42300,
        'ACCOUNT_NOT_FOUND'    => 42301,
        'PASSWORD_INCORRECT'   => 42302,
        'RATE_LIMITED'         => 42900,
        'SERVER_ERROR'         => 50000,
        'DB_ERROR'             => 50001,
        'CONFIG_ERROR'         => 50002,
        'NOT_INSTALLED'        => 50300,
    ],

    // 状态码映射
    'http_status' => [
        0       => 200,
        40000   => 400, 40001 => 400, 40002 => 400, 40003 => 400,
        40100   => 401, 40101 => 401, 40102 => 401, 40103 => 401, 40104 => 401,
        40300   => 403, 40301 => 403, 40302 => 403,
        40400   => 404,
        42300   => 423, 42301 => 401, 42302 => 401,
        42900   => 429,
        50000   => 500, 50001 => 500, 50002 => 500,
        50300   => 503,
    ],
];
