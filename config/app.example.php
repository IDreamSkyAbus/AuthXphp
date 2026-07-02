<?php
/**
 * AuthXphp 应用配置模板
 */

return [
    'name'         => 'AuthXphp',
    'version'      => '1.0.0',
    // 运行模式：embedded（嵌入式，作为网站子目录运行） | standalone（独立站）
    'run_mode'     => 'embedded',
    // BASE_PATH 自动推导（运行时由 config/index.php 计算并覆盖）
    'base_path'    => '',
    'timezone'     => 'Asia/Shanghai',
    'debug'        => false,
    // 超级管理员 Guard 名（用于后台默认登录）
    'admin_guard'  => 'admin',
    // ============================================================================
    // CORS Origin 白名单
    //   - ['*']          允许任意来源（不推荐）
    //   - ['https://a.com', 'https://b.com']  仅允许指定域名
    //   - ['*.example.com']  支持通配符
    // 当 Allow-Credentials=true 时，浏览器要求不能使用 '*'，必须明确列出域名
    // ============================================================================
    'cors_origins' => ['*'],
    // 是否允许带 Cookie（credentials）。true 时必须配合明确的 Origin 白名单
    'cors_allow_credentials' => false,
    // 允许的 HTTP 方法
    'cors_allow_methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
    // 允许的请求头
    'cors_allow_headers'     => 'Content-Type, Authorization, X-Requested-With, X-Request-Id',
];