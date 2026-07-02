<?php
/**
 * AuthXphp 数据库层
 *
 * 提供 PDO 单例 + 轻量 QueryBuilder。
 * 强制使用预处理语句，零 ORM 依赖。
 *
 * 使用：
 *   Db::table('users')->where('id', 1)->first();
 *   Db::table('users')->where('status', 1)->whereLike('username', 'adm')->get();
 *   Db::table('users')->insert(['username'=>'x','password'=>'...']);
 *   Db::table('users')->where('id', 1)->update(['status'=>0]);
 *   Db::table('users')->where('id', 1)->delete();
 */

require_once __DIR__ . '/../config/index.php';
require_once __DIR__ . '/../response/index.php';
require_once __DIR__ . '/../log/index.php';

class AuthXphpDbException extends RuntimeException
{
    public function __construct(string $message, int $code = 50001, ?Throwable $prev = null)
    {
        parent::__construct($message, $code, $prev);
    }
}

class QueryBuilder
{
    private $pdo;
    private $table;
    private $wheres = [];
    private $bindings = [];
    private $orderBy = '';
    private $limit = '';
    private $offset = '';
    private $selects = '*';

    public function __construct(PDO $pdo, string $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function select(string ...$cols): self
    {
        if (!empty($cols)) {
            $this->selects = implode(',', array_map(function ($c) {
                return strpos($c, '(') !== false || strpos($c, '.') !== false ? $c : '`' . $c . '`';
            }, $cols));
        }
        return $this;
    }

    public function where($col, $op = null, $val = null): self
    {
        if (is_array($col)) {
            foreach ($col as $k => $v) {
                $this->wheres[] = "`$k` = ?";
                $this->bindings[] = $v;
            }
            return $this;
        }
        if ($val === null && $op !== null) {
            $val = $op;
            $op = '=';
        }
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>='];
        if (!in_array($op, $allowed, true)) {
            throw new AuthXphpDbException('不支持的 WHERE 操作符：' . $op);
        }
        $this->wheres[] = "`$col` $op ?";
        $this->bindings[] = $val;
        return $this;
    }

    public function whereIn(string $col, array $vals): self
    {
        if (empty($vals)) {
            $this->wheres[] = '1=0';
            return $this;
        }
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $this->wheres[] = "`$col` IN ($placeholders)";
        foreach ($vals as $v) {
            $this->bindings[] = $v;
        }
        return $this;
    }

    public function whereLike(string $col, string $val): self
    {
        $this->wheres[] = "`$col` LIKE ?";
        $this->bindings[] = $val;
        return $this;
    }

    public function whereNull(string $col): self
    {
        $this->wheres[] = "`$col` IS NULL";
        return $this;
    }

    public function whereNotNull(string $col): self
    {
        $this->wheres[] = "`$col` IS NOT NULL";
        return $this;
    }

    /**
     * 原生 SQL 片段（直接拼接到 WHERE 子句）
     * 警告：调用方需自行处理 SQL 注入风险
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = "($sql)";
        foreach ($bindings as $b) {
            $this->bindings[] = $b;
        }
        return $this;
    }

    public function orderBy(string $col, string $dir = 'ASC'): self
    {
        $dir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy = "ORDER BY `$col` $dir";
        return $this;
    }

    public function limit(int $n): self
    {
        $this->limit = "LIMIT " . (int)$n;
        return $this;
    }

    public function offset(int $n): self
    {
        $this->offset = "OFFSET " . (int)$n;
        return $this;
    }

    public function page(int $page, int $pageSize = 20): self
    {
        $page = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));
        $this->limit($pageSize)->offset(($page - 1) * $pageSize);
        return $this;
    }

    public function toSql(): string
    {
        $sql = "SELECT {$this->selects} FROM `{$this->table}`";
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->orderBy) {
            $sql .= ' ' . $this->orderBy;
        }
        if ($this->limit) {
            $sql .= ' ' . $this->limit;
        }
        if ($this->offset) {
            $sql .= ' ' . $this->offset;
        }
        return $sql;
    }

    public function get(): array
    {
        $rows = $this->pdo->prepare($this->toSql());
        $rows->execute($this->bindings);
        return $rows->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        // 创建副本以避免修改自身状态（防止链式调用污染）
        $clone = clone $this;
        $clone->limit(1);
        $rows = $clone->get();
        return $rows[0] ?? null;
    }

    public function value(string $col)
    {
        $row = $this->select($col)->first();
        return $row[$col] ?? null;
    }

    public function count(): int
    {
        $row = $this->select('COUNT(*) AS c')->first();
        return (int)($row['c'] ?? 0);
    }

    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new AuthXphpDbException('insert 数据不能为空');
        }
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $colList = implode(',', array_map(function ($c) { return "`$c`"; }, $cols));
        $sql = "INSERT INTO `{$this->table}` ($colList) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $sets = [];
        $vals = [];
        foreach ($data as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $sql = "UPDATE `{$this->table}` SET " . implode(',', $sets);
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($vals, $this->bindings));
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $sql = "DELETE FROM `{$this->table}`";
        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        return $stmt->rowCount();
    }
}

class Db
{
    private static $instances = [];

    /**
     * 获取连接（懒加载单例）
     */
    public static function connection(string $name = null): PDO
    {
        $name = $name ?: (config('db.default') ?: 'mysql');
        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }
        $cfg = config('db.connections.' . $name);
        if (!$cfg) {
            throw new AuthXphpDbException('数据库连接配置不存在：' . $name, 50002);
        }
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int)($cfg['port'] ?? 3306),
                $cfg['database'] ?? '',
                $cfg['charset'] ?? 'utf8mb4'
            );
            $pdo = new PDO($dsn, $cfg['username'] ?? '', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '+08:00'");
            self::$instances[$name] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            Log::error('数据库连接失败', ['name' => $name, 'error' => $e->getMessage()]);
            throw new AuthXphpDbException('Auth 组件初始化失败：无法连接数据库（' . $e->getMessage() . '）', 50001, $e);
        }
    }

    /**
     * 取得查询构造器
     */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder(self::connection(), $table);
    }

    /**
     * 检测表是否存在
     */
    public static function tableExists(string $table): bool
    {
        // 安全考虑：先用白名单正则校验表名,避免动态配置注入的非法字符(如单引号)导致 SQL 语法错误或注入风险
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $row = self::connection()->query("SHOW TABLES LIKE " . self::connection()->quote($table))->fetch();
            return (bool)$row;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 原生查询（绑定参数）
     */
    public static function raw(string $sql, array $bindings = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
