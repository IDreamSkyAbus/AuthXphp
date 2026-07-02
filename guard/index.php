<?php
/**
 * AuthXphp Guard 抽象
 *
 * 一个 Guard 映射到一张用户表，对应一种登录场景（前端/后台/API 等）。
 * Guard 自身不知道 Token，只负责"账号+密码→用户记录"和"用户 ID→用户记录"。
 *
 * 使用：
 *   $user = Guard::driver('app')->attempt('alice', 'pwd123');
 *   $user = Guard::driver('admin')->byId(1);
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../db/index.php';
require_once __DIR__ . '/../log/index.php';
require_once __DIR__ . '/../hook/index.php';

class GuardException extends RuntimeException
{
    public function __construct(string $msg, int $code = 50002)
    {
        parent::__construct($msg, $code);
    }
}

class Guard
{
    /** @var array<string, Guard> */
    private static $instances = [];

    private $name;
    private $config;

    private function __construct(string $name, array $config)
    {
        $this->name   = $name;
        $this->config = $config;
    }

    /**
     * 获取 Guard 实例（按 name 缓存）
     */
    public static function driver(string $name): self
    {
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }
        $all = config('guards.guards') ?: [];
        if (!isset($all[$name])) {
            throw new GuardException("Auth 组件初始化失败：未找到 Guard 配置 '{$name}'");
        }
        $cfg = $all[$name];
        $required = ['table', 'primary_key', 'account_field', 'password_field'];
        foreach ($required as $f) {
            if (empty($cfg[$f])) {
                throw new GuardException("Auth 组件初始化失败：Guard '{$name}' 缺少配置项 '{$f}'");
            }
        }
        // 检测表是否存在（友好提示）
        if (!Db::tableExists($cfg['table'])) {
            throw new GuardException("Auth 组件初始化失败：找不到配置中指定的用户表 `{$cfg['table']}`（Guard: {$name}）");
        }
        // 安全警告：md5/sha1 已被 NIST 等机构认定为不安全
        if (in_array($cfg['password_algo'] ?? '', ['md5', 'sha1'], true)) {
            Log::warning('Guard 使用了不安全的密码算法 ' . $cfg['password_algo'] . '（Guard: ' . $name . '），建议升级到 password_hash(bcrypt)');
        }
        $inst = new self($name, $cfg);
        self::$instances[$name] = $inst;
        return $inst;
    }

    /**
     * 列出所有 Guard 名
     */
    public static function names(): array
    {
        return array_keys(config('guards.guards') ?: []);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function config(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }

    /**
     * 账号+密码登录
     * 成功返回用户记录（不包含 password 字段）；失败返回 null
     */
    public function attempt(string $account, string $password): ?array
    {
        $accountField  = $this->config['account_field'];
        $passwordField = $this->config['password_field'];
        $statusField   = $this->config['status_field'] ?? null;

        $row = Db::table($this->config['table'])
            ->where($accountField, $account)
            ->first();

        if (!$row) {
            return null;
        }

        // 状态校验
        if ($statusField && isset($row[$statusField]) && (int)$row[$statusField] === 0) {
            return null; // 视为账号不存在，避免泄露存在性
        }

        $stored = (string)($row[$passwordField] ?? '');
        if (!self::verifyPassword($password, $stored, $this->config['password_algo'] ?? 'password_hash')) {
            return null;
        }

        unset($row[$passwordField]);
        return $row;
    }

    /**
     * 按主键取用户
     *
     * ⚠️ 安全检查 status_field：禁用用户视为不存在（与 attempt 行为一致）。
     */
    public function byId($id): ?array
    {
        $row = Db::table($this->config['table'])
            ->where($this->config['primary_key'], $id)
            ->first();
        if (!$row) {
            return null;
        }
        // 检查账号状态（与 attempt 保持一致）
        if ($this->config['status_field'] && isset($row[$this->config['status_field']]) && (int)$row[$this->config['status_field']] === 0) {
            return null; // 禁用用户视为不存在
        }
        if (isset($row[$this->config['password_field']])) {
            unset($row[$this->config['password_field']]);
        }
        return $row;
    }

    /**
     * 取出 extra_fields 配置的子集
     */
    public function pickFields(array $user): array
    {
        $fields = $this->config['extra_fields'] ?? array_keys($user);
        $out = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $user)) {
                $out[$f] = $user[$f];
            }
        }
        if (!isset($out[$this->config['primary_key']])) {
            $out[$this->config['primary_key']] = $user[$this->config['primary_key']] ?? null;
        }
        return $out;
    }

    /**
     * 创建用户（注册用）
     */
    public function create(array $data): int
    {
        $pf = $this->config['password_field'];
        if (isset($data[$pf])) {
            $data[$pf] = self::hashPassword($data[$pf], $this->config['password_algo'] ?? 'password_hash');
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (empty($data[$this->config['account_field']])) {
            throw new GuardException('账号字段不能为空：' . $this->config['account_field']);
        }
        return Db::table($this->config['table'])->insert($data);
    }

    /**
     * 更新用户
     */
    public function update($id, array $data): int
    {
        $pf = $this->config['password_field'];
        if (isset($data[$pf])) {
            $data[$pf] = self::hashPassword($data[$pf], $this->config['password_algo'] ?? 'password_hash');
        }
        $data['updated_at'] = date('Y-m-d H:i:s');
        return Db::table($this->config['table'])
            ->where($this->config['primary_key'], $id)
            ->update($data);
    }

    // ======================== 密码处理 ========================

    public static function hashPassword(string $plain, string $algo = 'password_hash'): string
    {
        switch ($algo) {
            case 'password_hash':
            case 'bcrypt':
                return password_hash($plain, PASSWORD_BCRYPT);
            case 'md5':
                return md5($plain);
            case 'sha1':
                return sha1($plain);
            case 'plain':
                return $plain;
            default:
                return password_hash($plain, PASSWORD_BCRYPT);
        }
    }

    public static function verifyPassword(string $plain, string $stored, string $algo = 'password_hash'): bool
    {
        switch ($algo) {
            case 'password_hash':
            case 'bcrypt':
                return password_verify($plain, $stored);
            case 'md5':
                // 时序安全比较
                return hash_equals((string)$stored, md5($plain));
            case 'sha1':
                return hash_equals((string)$stored, sha1($plain));
            case 'plain':
                return hash_equals($stored, $plain);
            default:
                return password_verify($plain, $stored);
        }
    }
}
