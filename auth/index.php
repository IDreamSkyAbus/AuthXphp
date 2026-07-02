<?php
/**
 * AuthXphp 核心门面（对外统一入口）
 *
 * 静态方法，调用方零依赖：
 *   Auth::attempt($account, $password, $guard = null)
 *   Auth::login($account, $password, $guard = null)
 *   Auth::user()
 *   Auth::id()
 *   Auth::guard()
 *   Auth::check()
 *   Auth::hasRole($role)
 *   Auth::can($permission)
 *   Auth::refresh($refreshToken)
 *   Auth::logout()
 *   Auth::issueById($userId, $guard)   // 第三方登录用
 *   Auth::register($data, $guard)
 *   Auth::changePassword($old, $new)
 *   Auth::on($event, $cb)              // 钩子代理
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../db/index.php';
require_once __DIR__ . '/../token/index.php';
require_once __DIR__ . '/../guard/index.php';
require_once __DIR__ . '/../hook/index.php';
require_once __DIR__ . '/../log/index.php';

class Auth
{
    /** @var array{user:array, jti:string, payload:array, guard:string}|null */
    private static $current = null;

    // ======================== 上下文注入 ========================

    public static function setCurrent(array $user, string $guard, string $jti, array $payload = []): void
    {
        self::$current = [
            'user'    => $user,
            'guard'   => $guard,
            'jti'     => $jti,
            'payload' => $payload,
        ];
    }

    public static function clearCurrent(): void
    {
        self::$current = null;
    }

    public static function user(): ?array
    {
        return self::$current['user'] ?? null;
    }

    public static function id()
    {
        if (!self::$current) {
            return null;
        }
        $cfg = config('guards.guards.' . self::$current['guard']);
        $pk = $cfg['primary_key'] ?? 'id';
        return self::$current['user'][$pk] ?? null;
    }

    public static function guard(): ?string
    {
        return self::$current['guard'] ?? null;
    }

    public static function check(): bool
    {
        return self::$current !== null;
    }

    public static function jti(): ?string
    {
        return self::$current['jti'] ?? null;
    }

    // ======================== 登录 ========================

    /**
     * 验证账号密码并签发 token。
     * 返回 ['token'=>..., 'refresh_token'=>..., 'expires_in'=>..., 'user'=>...]
     * 失败返回 null。
     */
    public static function attempt(string $account, string $password, ?string $guard = null): ?array
    {
        $guard = $guard ?: (config('guards.default') ?: 'app');

        Hook::trigger(Hook::EVENT_BEFORE_LOGIN, [
            'account' => $account,
            'guard'   => $guard,
        ]);

        $failedReason = 'bad_credentials';
        $disabledHandler = function ($payload) use (&$failedReason) {
            $failedReason = 'disabled';
        };
        Hook::on(Hook::EVENT_GUARD_ATTEMPT_DISABLED, $disabledHandler);

        try {
            try {
                $g   = Guard::driver($guard);
                $usr = $g->attempt($account, $password);
            } catch (\Throwable $e) {
                Hook::trigger(Hook::EVENT_LOGIN_FAILED, [
                    'account' => $account,
                    'guard'   => $guard,
                    'reason'  => 'guard_error',
                    'error'   => $e->getMessage(),
                ]);
                Log::error('Guard 异常：' . $e->getMessage(), ['guard' => $guard, 'account' => $account]);
                return null;
            }
        } finally {
            Hook::off(Hook::EVENT_GUARD_ATTEMPT_DISABLED, $disabledHandler);
        }

        if (!$usr) {
            Hook::trigger(Hook::EVENT_LOGIN_FAILED, [
                'account' => $account,
                'guard'   => $guard,
                'reason'  => $failedReason,
            ]);
            return null;
        }

        $cfg = $g->config();
        $pk  = $cfg['primary_key'];
        $role = $usr[$cfg['role_field']] ?? null;
        $perms = $usr[$cfg['permissions_field']] ?? null;
        if (is_string($perms)) {
            $perms = json_decode($perms, true) ?: [];
        }
        $tokenData = [
            'uid'         => $usr[$pk] ?? null,
            'guard'       => $guard,
            'role'        => $role,
            'permissions' => $perms,
            'account'     => $usr[$cfg['account_field']] ?? $account,
        ];
        $accessTtl  = config('jwt.access_ttl') ?: 3600;
        $refreshTtl = config('jwt.refresh_ttl') ?: 604800;

        $token        = Jwt::issue($tokenData, $accessTtl);
        $refreshToken = Jwt::issueRefresh($tokenData, $refreshTtl);

        Hook::trigger(Hook::EVENT_LOGIN_SUCCESS, [
            'uid'    => $tokenData['uid'],
            'guard'  => $guard,
            'role'   => $role,
        ]);

        return [
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'expires_in'    => $accessTtl,
            'token_type'    => 'Bearer',
            'user'          => $g->pickFields($usr),
        ];
    }

    /**
     * 登录（语义化包装，等同 attempt 但失败抛业务异常）
     */
    public static function login(string $account, string $password, ?string $guard = null): array
    {
        $r = self::attempt($account, $password, $guard);
        if ($r === null) {
            // 不暴露具体原因：账号不存在 / 密码错误 都返回同一文案
            Response::fail(42302, '账号或密码错误');
        }
        return $r;
    }

    /**
     * 第三方登录入口：根据 User ID 直接签发 Token
     */
    public static function issueById($userId, ?string $guard = null): ?array
    {
        $guard = $guard ?: (config('guards.default') ?: 'app');
        try {
            $g   = Guard::driver($guard);
            $usr = $g->byId($userId);
        } catch (\Throwable $e) {
            Log::error('issueById 失败', ['guard' => $guard, 'uid' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
        if (!$usr) {
            return null;
        }
        $cfg = $g->config();
        $pk  = $cfg['primary_key'];
        $role = $usr[$cfg['role_field']] ?? null;
        $perms = $usr[$cfg['permissions_field']] ?? null;
        if (is_string($perms)) {
            $perms = json_decode($perms, true) ?: [];
        }
        $tokenData = [
            'uid'         => $usr[$pk] ?? null,
            'guard'       => $guard,
            'role'        => $role,
            'permissions' => $perms,
            'account'     => $usr[$cfg['account_field']] ?? null,
        ];
        $accessTtl  = config('jwt.access_ttl') ?: 3600;
        $refreshTtl = config('jwt.refresh_ttl') ?: 604800;
        return [
            'token'         => Jwt::issue($tokenData, $accessTtl),
            'refresh_token' => Jwt::issueRefresh($tokenData, $refreshTtl),
            'expires_in'    => $accessTtl,
            'token_type'    => 'Bearer',
            'user'          => $g->pickFields($usr),
        ];
    }

    // ======================== 注册 ========================

    /**
     * 注册新用户（默认 guard.registerable = true 才允许）
     */
    public static function register(array $data, ?string $guard = null): int
    {
        $guard = $guard ?: (config('guards.default') ?: 'app');
        $g     = Guard::driver($guard);
        if (!($g->config('registerable') ?? false)) {
            Response::fail(40300, '该 Guard 不开放注册');
            return;
        }
        $cfg  = $g->config();
        $af   = $cfg['account_field'];
        if (empty($data[$af])) {
            Response::fail(40001, '账号不能为空');
            return;
        }
        if (empty($data[$cfg['password_field']])) {
            Response::fail(40001, '密码不能为空');
            return;
        }
        $exists = Db::table($cfg['table'])->where($af, $data[$af])->first();
        if ($exists) {
            Response::fail(40002, '账号已存在');
            return;
        }
        $id = $g->create($data);
        Hook::trigger(Hook::EVENT_REGISTER_SUCCESS, ['uid' => $id, 'guard' => $guard]);
        return (int)$id;
    }

    // ======================== 刷新与登出 ========================

    public static function refresh(string $refreshToken): array
    {
        return Jwt::refresh($refreshToken);
    }

    public static function logout(): void
    {
        if (self::$current && !empty(self::$current['jti'])) {
            $cfg = config('jwt');
            Jwt::revoke(self::$current['jti'], $cfg['access_ttl'] ?? 3600);
        }
        // 只在有效登录状态才触发 logout 钩子，避免未登录时产生无意义的审计日志
        if (self::$current !== null) {
            Hook::trigger(Hook::EVENT_LOGOUT, [
                'uid'   => self::id(),
                'guard' => self::guard(),
            ]);
        }
        self::clearCurrent();
    }

    // ======================== 角色 / 权限 ========================

    public static function hasRole($role): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $cfg = config('guards.guards.' . self::guard());
        $rf  = $cfg['role_field'] ?? 'role';
        $my  = $u[$rf] ?? null;
        if (is_array($role)) {
            return in_array($my, $role, true);
        }
        return (string)$my === (string)$role;
    }

    public static function can($permission): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        $cfg = config('guards.guards.' . self::guard());
        $pf  = $cfg['permissions_field'] ?? 'permissions';
        $my  = $u[$pf] ?? [];
        if (is_string($my)) {
            $my = json_decode($my, true) ?: [];
        }
        if (is_array($permission)) {
            foreach ($permission as $p) {
                if (!in_array($p, $my, true)) {
                    return false;
                }
            }
            return true;
        }
        return in_array($permission, $my, true);
    }

    // ======================== 改密 ========================

    public static function changePassword(string $old, string $new): bool
    {
        // BUG #10 修复：统一通过 return false 表达失败，避免在某些路径上 exit
        // （Response::unauthorized() 内部会 exit）导致调用方无法预测行为。
        if (!self::check()) {
            Log::warning('未登录用户尝试修改密码');
            return false;
        }
        $guard = self::guard();
        $cfg   = config('guards.guards.' . $guard);
        $af    = $cfg['account_field'];
        $pf    = $cfg['password_field'];
        $algo  = $cfg['password_algo'] ?? 'password_hash';
        $u     = self::user();
        if (!$u || empty($u[$af])) {
            return false;
        }
        $row = Db::table($cfg['table'])->where($cfg['primary_key'], self::id())->first();
        $stored = (string)($row[$pf] ?? '');
        if (!Guard::verifyPassword($old, $stored, $algo)) {
            return false;
        }
        $g = Guard::driver($guard);
        $g->update(self::id(), [$pf => $new]);
        Hook::trigger(Hook::EVENT_PASSWORD_CHANGED, ['uid' => self::id(), 'guard' => $guard]);
        // 吊销当前 jti，强制重新登录
        if (self::$current && !empty(self::$current['jti'])) {
            Jwt::revoke(self::$current['jti'], config('jwt.access_ttl') ?: 3600);
        }
        return true;
    }

    // ======================== 钩子代理 ========================

    public static function on(string $event, callable $cb, int $priority = 10): void
    {
        Hook::on($event, $cb, $priority);
    }
}
