<?php
/**
 * AuthXphp JWT 配置模板
 *
 * 安全提示：
 *  1. 本文件是模板，安装后会被 install/finish.php 覆写。
 *  2. secret 必须是占位符或随机生成的 64 字符，不能是固定值。
 *  3. 安装前检测占位符会拒绝服务，请通过 /install/ 完成安装。
 */

return [
    'secret'         => '__AUTHXPHP_JWT_SECRET_NOT_CONFIGURED__',
    'refresh_secret' => '__AUTHXPHP_JWT_REFRESH_SECRET_NOT_CONFIGURED__',
    'algo'           => 'HS256',
    'access_ttl'     => 3600,       // 1 小时
    'refresh_ttl'    => 604800,     // 7 天
    'issuer'         => 'AuthXphp',
    'blacklist'      => [
        'enabled'      => false,
        'driver'       => 'file', // file | redis
        'path'         => '',
        'default_ttl'  => 3600, // 黑名单条目默认 TTL
        'redis'        => [
            'host'   => '127.0.0.1',
            'port'   => 6379,
            'db'     => 0,
            'prefix' => 'authxphp:',
        ],
    ],
];