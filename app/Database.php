<?php
/**
 * Tiny PDO wrapper — supports MySQL (production) and SQLite (local preview).
 */
class Database
{
    private PDO $pdo;
    public string $driver;

    public function __construct(array $cfg)
    {
        $this->driver = $cfg['driver'] ?? 'mysql';
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if ($this->driver === 'sqlite') {
            $path = $cfg['sqlite'];
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
            $this->pdo = new PDO('sqlite:' . $path, null, null, $opts);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
        }
    }

    public function pdo(): PDO { return $this->pdo; }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function all(string $sql, array $params = []): array
    { return $this->run($sql, $params)->fetchAll(); }

    public function one(string $sql, array $params = []): ?array
    { $r = $this->run($sql, $params)->fetch(); return $r === false ? null : $r; }

    public function val(string $sql, array $params = [])
    { return $this->run($sql, $params)->fetchColumn(); }

    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table,
            implode(',', array_map(fn($c) => "`$c`", $cols)),
            implode(',', array_fill(0, count($cols), '?')));
        $this->run($sql, array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, int $id): void
    {
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
        $this->run("UPDATE $table SET $set WHERE id=?", [...array_values($data), $id]);
    }

    public function delete(string $table, int $id): void
    { $this->run("DELETE FROM $table WHERE id=?", [$id]); }

    public function tableExists(string $table): bool
    {
        try {
            if ($this->driver === 'sqlite') {
                return (bool)$this->val("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
            }
            return (bool)$this->val("SHOW TABLES LIKE ?", [$table]);
        } catch (Throwable $e) { return false; }
    }
}
