<?php
/**
 * AuthXphp Token 引擎
 *
 * 封装 firebase/php-jwt，提供：
 *   - issue()     签发 access token
 *   - verify()    校验 token，返回 payload
 *   - refresh()   用 refresh_token 换新 access_token
 *   - revoke()    吊销（写入黑名单 Adapter）
 *   - isRevoked() 检查黑名单
 *
 * Payload 格式：
 *   {
 *     iss: 'AuthXphp',
 *     iat: 时间戳,
 *     exp: 时间戳,
 *     jti: 唯一 ID,
 *     data: { uid, guard, role, permissions, ... }
 *   }
 *
 * 依赖：firebase/php-jwt
 * 路径约定：vendor/autoload.php 在 AuthXphp 根目录的 vendor/ 下。
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../log/index.php';
require_once __DIR__ . '/../hook/index.php';

class TokenExpiredException extends RuntimeException
{
    public function __construct(string $msg = 'Token已过期')
    {
        parent::__construct($msg, 40101);
    }
}

class TokenInvalidException extends RuntimeException
{
    public function __construct(string $msg = 'Token无效')
    {
        parent::__construct($msg, 40102);
    }
}

class TokenRevokedException extends RuntimeException
{
    public function __construct(string $msg = 'Token已吊销')
    {
        parent::__construct($msg, 40103);
    }
}

class TokenGuardMismatchException extends RuntimeException
{
    public function __construct(string $msg = 'Guard 不匹配')
    {
        parent::__construct($msg, 40104);
    }
}

class Jwt
{
    private static $vendorLoaded = false;

    /**
     * 加载 vendor/autoload.php（firebase/php-jwt）
     */
    private static function loadVendor(): void
    {
        if (self::$vendorLoaded) {
            return;
        }
        $autoload = AUTHXPHP_PATH . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
        self::$vendorLoaded = true;
    }

    /**
     * 签发 access token
     */
    public static function issue(array $data, ?int $ttl = null): string
    {
        self::loadVendor();
        if (!class_exists('Firebase\\JWT\\JWT')) {
            throw new RuntimeException('firebase/php-jwt 未安装，请先 composer install');
        }
        $cfg = config('jwt');
        $now = time();
        $payload = [
            'iss'  => $cfg['issuer'] ?? 'AuthXphp',
            'iat'  => $now,
            'exp'  => $now + ($ttl ?? ($cfg['access_ttl'] ?? 3600)),
            'jti'  => bin2hex(random_bytes(16)),
            'data' => $data,
        ];
        return \Firebase\JWT\JWT::encode($payload, $cfg['secret'], $cfg['algo'] ?? 'HS256');
    }

    /**
     * 签发 refresh token（与 access 不同的 secret）
     */
    public static function issueRefresh(array $data, ?int $ttl = null): string
    {
        self::loadVendor();
        $cfg = config('jwt');
        $now = time();
        $payload = [
            'iss'  => ($cfg['issuer'] ?? 'AuthXphp') . '-refresh',
            'iat'  => $now,
            'exp'  => $now + ($ttl ?? ($cfg['refresh_ttl'] ?? 604800)),
            'jti'  => bin2hex(random_bytes(16)),
            'data' => $data,
        ];
        return \Firebase\JWT\JWT::encode($payload, $cfg['refresh_secret'] ?? $cfg['secret'] . '-refresh', $cfg['algo'] ?? 'HS256');
    }

    /**
     * 校验 token，返回 payload
     */
    public static function verify(string $token, ?string $expectedGuard = null): array
    {
        self::loadVendor();
        $cfg = config('jwt');

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($cfg['secret'], $cfg['algo'] ?? 'HS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            Hook::trigger(Hook::EVENT_TOKEN_EXPIRED, ['reason' => 'expired']);
            throw new TokenExpiredException('Token已过期');
        } catch (\Throwable $e) {
            // 仅在 debug 模式下记录异常消息，避免生产环境泄露敏感信息
            $debug = config('app.debug', false);
            $logData = $debug
                ? ['reason' => $e->getMessage(), 'exception' => get_class($e)]
                : ['exception' => get_class($e)];
            Log::error('Token 无效', $logData);
            throw new TokenInvalidException('Token 无效');
        }

        $payload = (array)$decoded;
        $data    = (array)($payload['data'] ?? []);
        $jti     = $payload['jti'] ?? '';

        // 黑名单检查
        if ($jti && self::isRevoked($jti)) {
            Hook::trigger(Hook::EVENT_TOKEN_REVOKED, ['jti' => $jti]);
            throw new TokenRevokedException('Token已被吊销');
        }

        // Guard 校验（防止用 A 表的 Token 访问 B 表的资源）
        if ($expectedGuard !== null && $expectedGuard !== '' && ($data['guard'] ?? null) !== $expectedGuard) {
            throw new TokenGuardMismatchException('Token 与当前 Guard 不匹配：期望 ' . $expectedGuard . '，实际 ' . ($data['guard'] ?? 'null'));
        }

        Hook::trigger(Hook::EVENT_TOKEN_VERIFIED, ['jti' => $jti, 'uid' => $data['uid'] ?? null, 'guard' => $data['guard'] ?? null]);

        return ['payload' => $payload, 'data' => $data, 'jti' => $jti];
    }

    /**
     * 用 refresh_token 换新 access_token + 新 refresh_token
     */
    public static function refresh(string $refreshToken): array
    {
        self::loadVendor();
        $cfg = config('jwt');

        try {
            $decoded = \Firebase\JWT\JWT::decode($refreshToken, new \Firebase\JWT\Key($cfg['refresh_secret'] ?? $cfg['secret'] . '-refresh', $cfg['algo'] ?? 'HS256'));
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new TokenExpiredException('Refresh Token已过期');
        } catch (\Throwable $e) {
            throw new TokenInvalidException('Refresh Token无效');
        }

        $data = (array)($decoded->data ?? []);
        $jti  = $decoded->jti ?? '';
        if ($jti && self::isRevoked($jti)) {
            throw new TokenRevokedException('Refresh Token已被吊销');
        }

        // 重新签发 access + refresh
        $newAccess  = self::issue($data, null);
        $newRefresh = self::issueRefresh($data, null);

        // 吊销旧 refresh（失败时仅记录日志，不中断流程）
        try {
            self::revoke($jti, ($cfg['refresh_ttl'] ?? 604800));
        } catch (\Throwable $e) {
            Log::warn('Revoke refresh token failed', [
                'jti'  => $jti,
                'error'=> $e->getMessage(),
            ]);
        }

        return [
            'token'         => $newAccess,
            'refresh_token' => $newRefresh,
            'expires_in'    => $cfg['access_ttl'] ?? 3600,
            'token_type'    => 'Bearer',
            'user'          => $data,
        ];
    }

    /**
     * 吊销 token（写入黑名单）
     */
    public static function revoke(string $jti, int $ttl = 0): void
    {
        $cfg = config('jwt.blacklist');
        if (!($cfg['enabled'] ?? false)) {
            return;
        }
        $ttl = $ttl > 0 ? $ttl : ($cfg['default_ttl'] ?? 3600);
        $driver = $cfg['driver'] ?? 'file';
        if ($driver === 'redis') {
            self::revokeRedis($jti, $ttl, $cfg['redis'] ?? []);
        } else {
            self::revokeFile($jti, $ttl);
        }
        Hook::trigger(Hook::EVENT_TOKEN_REVOKED, ['jti' => $jti, 'ttl' => $ttl]);
    }

    public static function isRevoked(string $jti): bool
    {
        $cfg = config('jwt.blacklist');
        if (!($cfg['enabled'] ?? false)) {
            return false;
        }
        $driver = $cfg['driver'] ?? 'file';
        if ($driver === 'redis') {
            return self::isRevokedRedis($jti, $cfg['redis'] ?? []);
        }
        return self::isRevokedFile($jti);
    }

    // ======================== 文件黑名单实现 ========================

    private static function blacklistFile(): string
    {
        $cfg = config('jwt.blacklist');
        $path = $cfg['path'] ?: (AUTHXPHP_STORAGE_PATH . '/blacklist');
        if (!is_dir($path)) {
            @mkdir($path, 0755, true);
        }
        return $path;
    }

    private static function revokeFile(string $jti, int $ttl): void
    {
        $file = self::blacklistFile() . '/' . substr($jti, 0, 2) . '.json';
        $data = [];
        if (is_file($file)) {
            $data = (array)json_decode((string)file_get_contents($file), true);
        }
        $data[$jti] = time() + $ttl;
        // 清理过期
        $now = time();
        foreach ($data as $k => $exp) {
            if ($exp < $now) {
                unset($data[$k]);
            }
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private static function isRevokedFile(string $jti): bool
    {
        $file = self::blacklistFile() . '/' . substr($jti, 0, 2) . '.json';
        if (!is_file($file)) {
            return false;
        }
        $data = (array)json_decode((string)file_get_contents($file), true);
        $exp  = $data[$jti] ?? 0;
        return $exp > 0 && $exp > time();
    }

    // ======================== Redis 黑名单实现（按需扩展） ========================

    private static function revokeRedis(string $jti, int $ttl, array $cfg): void
    {
        if (!class_exists('Redis')) {
            Log::warn('Redis 扩展未安装，回退到文件黑名单');
            self::revokeFile($jti, $ttl);
            return;
        }
        try {
            $r = new \Redis();
            $r->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379));
            if (!empty($cfg['pass'])) {
                $r->auth($cfg['pass']);
            }
            $r->select((int)($cfg['db'] ?? 0));
            $r->setex(($cfg['prefix'] ?? 'authxphp:') . 'bl:' . $jti, $ttl, '1');
            $r->close();
        } catch (\Throwable $e) {
            Log::error('Redis 黑名单写入失败', ['error' => $e->getMessage()]);
            self::revokeFile($jti, $ttl);
        }
    }

    private static function isRevokedRedis(string $jti, array $cfg): bool
    {
        if (!class_exists('Redis')) {
            return self::isRevokedFile($jti);
        }
        try {
            $r = new \Redis();
            $r->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379));
            if (!empty($cfg['pass'])) {
                $r->auth($cfg['pass']);
            }
            $r->select((int)($cfg['db'] ?? 0));
            $exists = (bool)$r->exists(($cfg['prefix'] ?? 'authxphp:') . 'bl:' . $jti);
            $r->close();
            return $exists;
        } catch (\Throwable $e) {
            return self::isRevokedFile($jti);
        }
    }
}
