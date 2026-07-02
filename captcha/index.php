<?php
/**
 * AuthXphp 图形验证码
 *
 * 用法：
 *   - GET /api/captcha  返回 ['key'=>..., 'image'=>'data:image/png;base64,...']
 *   - POST 时携带 captcha_key + captcha_code
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../response/index.php';

class Captcha
{
    private static function session(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    /**
     * 生成 4 位图形验证码
     */
    public static function generate(): array
    {
        self::session();
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('GD extension required');
        }
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code  = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $key = bin2hex(random_bytes(8));
        $_SESSION['authxphp_captcha'][$key] = [
            'code'  => $code,
            'exp'   => time() + 300,
        ];

        // 绘制图片
        $w = 120; $h = 40;
        $im = imagecreatetruecolor($w, $h);
        $bg = imagecolorallocate($im, 245, 247, 250);
        $tx = imagecolorallocate($im, 30, 41, 59);
        $ln = imagecolorallocate($im, 200, 210, 220);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            imageline($im, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $ln);
        }
        // 字符（每个字符随机 X/Y）
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $x = 18 + $i * 22;
            $y = random_int(10, 22);
            imagestring($im, 5, $x, $y, $code[$i], $tx);
        }
        // 干扰点
        for ($i = 0; $i < 30; $i++) {
            imagesetpixel($im, random_int(0, $w), random_int(0, $h), $ln);
        }
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return [
            'key'   => $key,
            'image' => 'data:image/png;base64,' . base64_encode($png),
            'ttl'   => 300,
        ];
    }

    /**
     * 校验（一次性，校验后销毁）
     */
    public static function verify(string $key, string $code): bool
    {
        self::session();
        $bucket = $_SESSION['authxphp_captcha'] ?? [];
        if (!isset($bucket[$key])) {
            return false;
        }
        $row = $bucket[$key];
        unset($_SESSION['authxphp_captcha'][$key]);
        if (($row['exp'] ?? 0) < time()) {
            return false;
        }
        // 使用 strcmp（大小写敏感）而非 strcasecmp：字符集 $chars 只含大写字母，
        // 因此不做大小写折叠；用户提交时统一 strtoupper + trim 规范化以保证一致。
        return strcmp(trim((string)$row['code']), strtoupper(trim((string)$code))) === 0;
    }

    public static function clear(): void
    {
        self::session();
        unset($_SESSION['authxphp_captcha']);
    }
}
