<?php
/**
 * AuthXphp 频率限制
 *
 * 用法：
 *   $ok = RateLimiter::hit('login:1.2.3.4', 10, 60);
 *   if (!$ok) -> 触发 429
 *
 * Driver：
 *   - file（默认）：storage/limits/{hash}.json
 *   - redis：需要 PHP redis 扩展 + 配置
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../log/index.php';

class RateLimiter
{
    /**
     * 命中一次，返回 true 表示放行，false 表示超限
     */
    public static function hit(string $key, int $max, int $window): bool
    {
        $cfg = config('ratelimit');
        if (!($cfg['enabled'] ?? true)) {
            return true;
        }
        $driver = $cfg['driver'] ?? 'file';
        if ($driver === 'redis' && class_exists('Redis')) {
            return self::hitRedis($key, $max, $window, $cfg['redis'] ?? []);
        }
        return self::hitFile($key, $max, $window);
    }

    private static function dir(): string
    {
        $path = config('ratelimit.path') ?: (AUTHXPHP_STORAGE_PATH . '/limits');
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }

    /**
     * 文件式限流（使用 flock 独占锁防止 TOCTOU 竞态）
     *
     * TOCTOU 竞态：在高并发下，多个请求可能同时读取到相同计数（如 9），各自写入后实际超过限制。
     * 解决方案：使用 LOCK_EX 独占锁包裹整个 read-modify-write 周期。
     */
    private static function hitFile(string $key, int $max, int $window): bool
    {
        $file = self::dir() . '/' . md5($key) . '.json';
        $now  = time();

        // 获取独占锁（防止 TOCTOU 竞态）
        $fp = fopen($file, 'c+');
        if (!$fp) {
            // 无法打开文件，降级放行（避免阻塞业务）
            Log::warn('限流文件无法打开', ['file' => $file]);
            return true;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            Log::warn('限流文件无法获取锁', ['file' => $file]);
            return true;
        }

        // === 在锁内执行完整的 read-modify-write ===
        $content = stream_get_contents($fp);
        $data = [];
        if ($content !== '') {
            $data = (array)json_decode($content, true);
        }

        // 清理过期记录
        $data = array_values(array_filter($data, function ($ts) use ($now, $window) {
            return $ts > $now - $window;
        }));

        // 检查是否超限
        if (count($data) >= $max) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        // 添加本次记录
        $data[] = $now;

        // 清空文件并写入新内容（rewind + truncate）
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));

        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    private static function hitRedis(string $key, int $max, int $window, array $cfg): bool
    {
        try {
            $r = new \Redis();
            $r->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379));
            if (!empty($cfg['pass'])) {
                $r->auth($cfg['pass']);
            }
            $r->select((int)($cfg['db'] ?? 0));
            $fullKey = ($cfg['prefix'] ?? 'authxphp:rl:') . $key;
            $count = (int)$r->incr($fullKey);
            if ($count === 1) {
                $r->expire($fullKey, $window);
            }
            $r->close();
            return $count <= $max;
        } catch (\Throwable $e) {
            // 不回退到文件，避免 Redis 和文件两套存储数据不同步导致限流窗口错乱
            // Redis 失败时直接放行，避免因基础设施故障阻塞用户
            Log::error('Redis 限流失败', ['error' => $e->getMessage(), 'key' => $key]);
            return true;
        }
    }
}
