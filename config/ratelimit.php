<?php
/**
 * AuthXphp 频率限制配置
 */

return [
    'enabled' => true,
    'driver'  => 'file', // file | redis
    'path'    => '',
    'redis'   => [
        'host'   => '127.0.0.1',
        'port'   => 6379,
        'db'     => 0,
        'prefix' => 'authxphp:rl:',
    ],
    // 默认规则
    'defaults' => [
        'login'  => ['max' => 10,  'window' => 60],    // 登录：10 次/分钟
        'api'    => ['max' => 600, 'window' => 60],    // 普通 API：600 次/分钟
        'admin'  => ['max' => 120, 'window' => 60],    // 后台：120 次/分钟
    ],
];
