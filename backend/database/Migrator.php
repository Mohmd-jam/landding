<?php

declare(strict_types=1);

namespace Database;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use PDO;

/**
 * Emits and executes DDL from the portable schema declaration.
 *
 * MySQL  : CREATE TABLE (columns only) → ALTER TABLE for unique keys, indexes
 *          and foreign keys (allows circular references such as media⇄admins).
 * SQLite : CREATE TABLE with inline keys + separate CREATE INDEX statements.
 */
final class Migrator
{
    private array $log = [];

    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::connect();
    }

    /** @return array<string,array<string,mixed>> */
    public static function schema(): array
    {
        /** @var array<string,array<string,mixed>> $schema */
        $schema = require __DIR__ . '/schema.php';

        return $schema;
    }

    public function isSqlite(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function hasTable(string $table): bool
    {
        if ($this->isSqlite()) {
            $row = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $row->execute([$table]);

            return $row->fetch() !== false;
        }

        $row = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $row->execute([$table]);

        return (int) $row->fetchColumn() > 0;
    }

    /** @return array<int,string> */
    public function createAll(bool $fresh = false): array
    {
        $schema = self::schema();

        if ($fresh) {
            $this->dropAll();
        }

        if ($this->isSqlite()) {
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
        }

        foreach ($schema as $table => $definition) {
            if ($this->hasTable($table)) {
                $this->log[] = 'skip  ' . $table . ' (exists)';
                continue;
            }

            $this->createTable($table, $definition);
            $this->log[] = 'create ' . $table;
        }

        if (!$this->isSqlite()) {
            foreach ($schema as $table => $definition) {
                $this->createKeys($table, $definition);
            }
        }

        if ($this->isSqlite()) {
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $this->log;
    }

    public function dropAll(): array
    {
        $schema = array_reverse(array_keys(self::schema()), true);

        if ($this->isSqlite()) {
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            foreach ($schema as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
                $this->log[] = 'drop  ' . $table;
            }
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            return $this->log;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($schema as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            $this->log[] = 'drop  ' . $table;
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        return $this->log;
    }

    /* --------------------------------------------------------------------- */
    /* Creation                                                              */
    /* --------------------------------------------------------------------- */

    private function createTable(string $table, array $definition): void
    {
        $columnSql = [];
        $foreigns = [];

        foreach ($definition['columns'] as $column => $spec) {
            $columnSql[] = $this->columnSql($column, $spec, $definition);
        }

        if (!isset($definition['primary']) && $this->isSqlite()) {
            // handled inline by the `id` type
        }

        if (isset($definition['primary'])) {
            $primarySpec = (string) ($definition['columns'][$definition['primary']] ?? '');

            // `id` columns declare their primary key inline (SQLite requires it
            // for AUTOINCREMENT); everything else gets a table-level clause.
            if (!$this->columnIsAutoId($primarySpec)) {
                $columnSql[] = 'PRIMARY KEY (`' . $definition['primary'] . '`)';
            }
        }

        if ($this->isSqlite()) {
            // Inline unique constraints + foreign keys (SQLite cannot ALTER them in)
            foreach ($definition['unique'] ?? [] as $columns) {
                $columnSql[] = 'UNIQUE (' . implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns)) . ')';
            }

            foreach ($definition['foreigns'] ?? [] as [$column, $refTable, $refColumn, $onDelete]) {
                $columnSql[] = sprintf(
                    'FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                    $column,
                    $refTable,
                    $refColumn,
                    $onDelete
                );
            }
        }

        $sql = 'CREATE TABLE `' . $table . '` (' . implode(', ', $columnSql) . ')';

        if (!$this->isSqlite()) {
            $engine = (string) (Config::get('database.mysql.engine') ?? 'InnoDB');
            $charset = (string) (Config::get('database.mysql.charset') ?? 'utf8mb4');
            $collation = (string) (Config::get('database.mysql.collation') ?? 'utf8mb4_unicode_ci');
            $sql .= ' ENGINE=' . $engine . ' DEFAULT CHARSET=' . $charset . ' COLLATE=' . $collation;
        }

        $this->pdo->exec($sql);
    }

    private function createKeys(string $table, array $definition): void
    {
        if ($this->isSqlite()) {
            // SQLite: keys are declared inline in CREATE TABLE (see createTable),
            // so only the standalone index objects are created here.
            foreach ($definition['indexes'] ?? [] as $columns) {
                $name = $table . '_' . implode('_', $columns) . '_index';
                $this->pdo->exec(sprintf(
                    'CREATE INDEX IF NOT EXISTS `%s` ON `%s` (%s)',
                    $name,
                    $table,
                    implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns))
                ));
            }

            return;
        }

        foreach ($definition['unique'] ?? [] as $columns) {
            $name = $table . '_' . implode('_', $columns) . '_unique';
            $this->pdo->exec(sprintf(
                'ALTER TABLE `%s` ADD UNIQUE KEY `%s` (%s)',
                $table,
                $name,
                implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns))
            ));
        }

        foreach ($definition['indexes'] ?? [] as $columns) {
            $name = $table . '_' . implode('_', $columns) . '_index';
            $this->pdo->exec(sprintf(
                'ALTER TABLE `%s` ADD KEY `%s` (%s)',
                $table,
                $name,
                implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns))
            ));
        }

        foreach ($definition['foreigns'] ?? [] as [$column, $refTable, $refColumn, $onDelete]) {
            $name = $table . '_' . $column . '_fk';
            $this->pdo->exec(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE CASCADE',
                $table,
                $name,
                $column,
                $refTable,
                $refColumn,
                $onDelete
            ));
        }
    }

    /** SQLite needs its indexes created in a second pass as well. */
    public function createSqliteIndexes(): void
    {
        foreach (self::schema() as $table => $definition) {
            $this->createKeys($table, $definition);
        }
    }

    private function columnIsAutoId(string $spec): bool
    {
        return str_starts_with($spec, 'id');
    }

    private function columnSql(string $column, string $spec, array $definition): string
    {
        [$type, $params, $default, $nullable] = $this->parse($spec);

        $sqlite = $this->isSqlite();
        $typeSql = $this->typeSql($type, $params, $sqlite);

        $isPrimary = ($definition['primary'] ?? null) === $column;

        // The `id` type is always the auto-increment primary key — declared
        // inline because SQLite requires that exact form for AUTOINCREMENT.
        if ($type === 'id') {
            return $sqlite
                ? '`' . $column . '` INTEGER PRIMARY KEY AUTOINCREMENT'
                : '`' . $column . '` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        }

        $parts = ['`' . $column . '`', $typeSql];

        // The PRIMARY KEY clause for the column itself is added by createTable().
        $parts[] = ($nullable && !$isPrimary) ? 'NULL' : 'NOT NULL';

        if ($default !== null) {
            $parts[] = 'DEFAULT ' . $this->defaultSql($default, $type);
        }

        return implode(' ', $parts);
    }

    /**
     * @return array{0:string,1:array<int,string>,2:?string,3:bool}
     */
    private function parse(string $spec): array
    {
        $segments = explode(':', $spec);
        $type = array_shift($segments) ?? 'string';
        $params = [];
        $default = null;
        $nullable = false;

        // A trailing `?` may sit on the type itself (e.g. `mediumtext?`, `json?`)
        if (str_ends_with($type, '?')) {
            $nullable = true;
            $type = rtrim($type, '?');
        }

        foreach ($segments as $segment) {
            if (str_starts_with($segment, 'default=')) {
                $default = substr($segment, 8);
                continue;
            }
            if (str_ends_with($segment, '?')) {
                $nullable = true;
                $segment = rtrim($segment, '?');
            }
            if ($segment !== '') {
                $params[] = $segment;
            }
        }

        return [$type, $params, $default, $nullable];
    }

    private function typeSql(string $type, array $params, bool $sqlite): string
    {
        return match ($type) {
            'id', 'bigint' => $sqlite ? 'INTEGER' : 'BIGINT UNSIGNED',
            'int' => $sqlite ? 'INTEGER' : 'INT',
            'tinyint' => $sqlite ? 'INTEGER' : 'TINYINT',
            'bool' => $sqlite ? 'INTEGER' : 'TINYINT(1)',
            'decimal' => $sqlite ? 'REAL' : 'DECIMAL(' . implode(',', $params) . ')',
            'string' => $sqlite ? 'TEXT' : 'VARCHAR(' . ($params[0] ?? '255') . ')',
            'text' => 'TEXT',
            'mediumtext' => $sqlite ? 'TEXT' : 'MEDIUMTEXT',
            'longtext' => $sqlite ? 'TEXT' : 'LONGTEXT',
            'json' => $sqlite ? 'TEXT' : 'JSON',
            'date' => $sqlite ? 'TEXT' : 'DATE',
            'datetime' => $sqlite ? 'TEXT' : 'DATETIME',
            'time' => $sqlite ? 'TEXT' : 'TIME',
            'enum' => $sqlite
                ? 'TEXT'
                : "ENUM(" . implode(', ', array_map(fn ($v) => "'" . str_replace("'", "", trim($v)) . "'", explode(',', $params[0] ?? ''))) . ")",
            default => $sqlite ? 'TEXT' : 'VARCHAR(255)',
        };
    }

    private function defaultSql(string $default, string $type): string
    {
        if ($type === 'bool') {
            return in_array(strtolower($default), ['1', 'true', 'yes'], true) ? '1' : '0';
        }

        if (in_array($type, ['id', 'int', 'bigint', 'tinyint', 'decimal'], true)) {
            return is_numeric($default) ? $default : '0';
        }

        return "'" . str_replace("'", "''", $default) . "'";
    }

    /* --------------------------------------------------------------------- */
    /* Dump                                                                  */
    /* --------------------------------------------------------------------- */

    /** Generate a standalone .sql file for DBAs / manual deployment. */
    public function dumpSql(bool $sqlite = false): string
    {
        $schema = self::schema();
        $statements = [];

        if ($sqlite) {
            $statements[] = 'PRAGMA foreign_keys = ON;';
        } else {
            $statements[] = 'SET FOREIGN_KEY_CHECKS = 0;';
            $statements[] = 'SET NAMES utf8mb4;';
        }

        foreach ($schema as $table => $definition) {
            $columns = [];
            $inlineConstraints = [];

            foreach ($definition['columns'] as $column => $spec) {
                $columns[] = '  ' . $this->columnSql($column, $spec, $definition);
            }

            if ($sqlite) {
                foreach ($definition['unique'] ?? [] as $uniqueColumns) {
                    $inlineConstraints[] = '  UNIQUE (' . implode(', ', array_map(fn ($c) => '`' . $c . '`', $uniqueColumns)) . ')';
                }
                foreach ($definition['foreigns'] ?? [] as [$column, $refTable, $refColumn, $onDelete]) {
                    $inlineConstraints[] = sprintf(
                        '  FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
                        $column,
                        $refTable,
                        $refColumn,
                        $onDelete
                    );
                }
            }

            $body = implode(",\n", array_merge($columns, $inlineConstraints));
            $sql = "CREATE TABLE `{$table}` (\n{$body}\n)";

            if (!$sqlite) {
                $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            }

            $statements[] = $sql . ';';

            if ($sqlite) {
                foreach ($definition['unique'] ?? [] as $uniqueColumns) {
                    $statements[] = sprintf(
                        'CREATE UNIQUE INDEX `%s` ON `%s` (%s);',
                        $table . '_' . implode('_', $uniqueColumns) . '_unique',
                        $table,
                        implode(', ', array_map(fn ($c) => '`' . $c . '`', $uniqueColumns))
                    );
                }
                foreach ($definition['indexes'] ?? [] as $indexColumns) {
                    $statements[] = sprintf(
                        'CREATE INDEX `%s` ON `%s` (%s);',
                        $table . '_' . implode('_', $indexColumns) . '_index',
                        $table,
                        implode(', ', array_map(fn ($c) => '`' . $c . '`', $indexColumns))
                    );
                }
            } else {
                foreach ($definition['unique'] ?? [] as $uniqueColumns) {
                    $statements[] = sprintf(
                        'ALTER TABLE `%s` ADD UNIQUE KEY `%s` (%s);',
                        $table,
                        $table . '_' . implode('_', $uniqueColumns) . '_unique',
                        implode(', ', array_map(fn ($c) => '`' . $c . '`', $uniqueColumns))
                    );
                }
                foreach ($definition['indexes'] ?? [] as $indexColumns) {
                    $statements[] = sprintf(
                        'ALTER TABLE `%s` ADD KEY `%s` (%s);',
                        $table,
                        $table . '_' . implode('_', $indexColumns) . '_index',
                        implode(', ', array_map(fn ($c) => '`' . $c . '`', $indexColumns))
                    );
                }
                foreach ($definition['foreigns'] ?? [] as [$column, $refTable, $refColumn, $onDelete]) {
                    $statements[] = sprintf(
                        'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE CASCADE;',
                        $table,
                        $table . '_' . $column . '_fk',
                        $column,
                        $refTable,
                        $refColumn,
                        $onDelete
                    );
                }
            }
        }

        $statements[] = $sqlite ? '' : 'SET FOREIGN_KEY_CHECKS = 1;';

        return implode("\n", $statements) . "\n";
    }

    /** @return array<int,string> */
    public function log(): array
    {
        return $this->log;
    }

    public function writeDumps(): void
    {
        $directory = dirname(__DIR__) . '/database';
        file_put_contents($directory . '/schema.mysql.sql', $this->dumpSql(false));
        file_put_contents($directory . '/schema.sqlite.sql', $this->dumpSql(true));
        Logger::info('Schema dumps written');
    }
}
