<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * PDO connection factory and query helpers.
 *
 * The application ships with two first-class drivers: MySQL (production, the
 * documented target) and SQLite (zero-config installs, CI, and this sandbox).
 * Everything above this class is dialect-agnostic — real prepared statements
 * are used in every single query, without exception.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = (array) Config::get('database', []);
        $driver = (string) ($config['driver'] ?? 'mysql');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        if ($driver === 'sqlite') {
            $path = (string) ($config['sqlite']['path'] ?? storage_path('database.sqlite'));
            $directory = dirname($path);

            if (!is_dir($directory)) {
                @mkdir($directory, 0775, true);
            }

            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            self::$driver = 'sqlite';
        } else {
            $mysql = (array) ($config['mysql'] ?? []);
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                (string) ($mysql['host'] ?? '127.0.0.1'),
                (string) ($mysql['port'] ?? '3306'),
                (string) ($mysql['database'] ?? ''),
                (string) ($mysql['charset'] ?? 'utf8mb4')
            );

            $pdo = new PDO($dsn, (string) ($mysql['username'] ?? ''), (string) ($mysql['password'] ?? ''), $options);
            $pdo->exec('SET SESSION sql_mode = "STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION"');
            $pdo->exec('SET NAMES ' . (string) ($mysql['charset'] ?? 'utf8mb4') . ' COLLATE ' . (string) ($mysql['collation'] ?? 'utf8mb4_unicode_ci'));
            self::$driver = 'mysql';
        }

        self::$pdo = $pdo;

        return self::$pdo;
    }

    public static function driver(): string
    {
        self::connect();

        return self::$driver;
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    /** @return array<int,string> */
    public static function availableDrivers(): array
    {
        return PDO::getAvailableDrivers();
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table, self::connect());
    }

    public static function raw(string $sql): Raw
    {
        return new Raw($sql);
    }

    /** Run a prepared statement. */
    public static function statement(string $sql, array $bindings = []): \PDOStatement
    {
        $statement = self::connect()->prepare($sql);
        $statement->execute(self::normaliseBindings($bindings));

        return $statement;
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::statement($sql, $bindings)->fetchAll() ?: [];
    }

    /** @return array<string,mixed>|null */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::statement($sql, $bindings)->fetch();

        return is_array($row) ? $row : null;
    }

    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $value = self::statement($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** Run a callback inside a transaction; rolls back on any exception. */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connect();
        $ownTransaction = !$pdo->inTransaction();

        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback($pdo);

            if ($ownTransaction) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /** @return array<int,string> */
    public static function tables(): array
    {
        $tables = [];

        if (self::isSqlite()) {
            $rows = self::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");

            foreach ($rows as $row) {
                $tables[] = (string) $row['name'];
            }
        } else {
            $rows = self::select('SHOW TABLES');

            foreach ($rows as $row) {
                $tables[] = (string) array_values($row)[0];
            }
        }

        return $tables;
    }

    public static function hasTable(string $table): bool
    {
        try {
            if (self::isSqlite()) {
                return (int) self::scalar(
                    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                    [$table]
                ) > 0;
            }

            return (int) self::scalar(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function lastInsertId(): int
    {
        return (int) self::connect()->lastInsertId();
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    /** Normalise booleans/arrays so PDO can bind them safely. */
    private static function normaliseBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof Raw) {
                continue;
            }

            if (is_bool($value)) {
                $bindings[$key] = $value ? 1 : 0;
            } elseif (is_array($value)) {
                $bindings[$key] = Json::encode($value);
            } elseif ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format('Y-m-d H:i:s');
            }
        }

        return $bindings;
    }
}
