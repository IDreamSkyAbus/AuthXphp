<?php
/**
 * 通用错误页模板
 * 不依赖 _bootstrap.php，可用于 CSRF 验证失败等场景
 *
 * 用法：error.php?code=403&msg=CSRF验证失败
 */

$code = (int)($_GET['code'] ?? 500);
$msg  = trim((string)($_GET['msg'] ?? '发生错误'));
// 限制长度，避免超长字符串破坏布局
if (function_exists('mb_substr')) {
    $msg = mb_substr($msg, 0, 200);
} else {
    $msg = substr($msg, 0, 200);
}

// 有效的 HTTP 状态码
$validCodes = [400, 401, 403, 404, 405, 500];
if (!in_array($code, $validCodes, true)) {
    $code = 500;
}

// 状态码描述
$titles = [
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    500 => 'Internal Server Error',
];
$title = $titles[$code] ?? 'Error';

http_response_code($code);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $code ?> <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 60px 80px;
            text-align: center;
            max-width: 500px;
        }
        .code {
            font-size: 120px;
            font-weight: 700;
            color: #764ba2;
            line-height: 1;
            margin-bottom: 10px;
        }
        .title {
            font-size: 24px;
            color: #555;
            margin-bottom: 20px;
        }
        .message {
            font-size: 16px;
            color: #888;
            padding: 15px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .back-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="code"><?= $code ?></div>
        <div class="title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="message"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
        <a href="javascript:history.back();" class="back-btn">返回上一页</a>
    </div>
</body>
</html>