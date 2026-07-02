<?php
/**
 * AuthXphp 数据库配置模板
 * 安装时由 install/finish.php 覆写
 */

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'host'     => '127.0.0.1',
            'port'     => 3306,
            'database' => 'authxphp',
            'username' => 'root',
            'password' => '',
            'charset'  => 'utf8mb4',
            'prefix'   => '',
        ],
    ],
];