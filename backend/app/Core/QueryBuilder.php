<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Small, predictable query builder.
 *
 * Design goals:
 *  - every value is bound (no string interpolation of user data, ever);
 *  - identifiers are whitelisted-shaped (letters, digits, _ . and aliases);
 *  - the generated SQL is valid on MySQL 8 and SQLite 3.
 *
 * Deliberately not an ORM: the schema stays explicit, joins stay readable and
 * the generated SQL can always be inspected with toSql().
 */
final class QueryBuilder
{
    private string $table;
    private PDO $connection;
    private array $columns = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private array $groups = [];
    private array $havings = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table, ?PDO $connection = null)
    {
        $this->table = trim($table);
        $this->connection = $connection ?? Database::connect();
    }

    public static function make(string $table): self
    {
        return new self($table);
    }

    /* --------------------------------------------------------------------- */
    /* Clauses                                                               */
    /* --------------------------------------------------------------------- */

    public function select(mixed ...$columns): self
    {
        $this->columns = [];

        foreach ($columns as $group) {
            foreach (is_array($group) ? $group : [$group] as $column) {
                $this->columns[] = $column;
            }
        }

        if ($this->columns === []) {
            $this->columns = ['*'];
        }

        return $this;
    }

    public function addSelect(mixed ...$columns): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }

        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    public function where(mixed $column, mixed $operator = null, mixed $value = null): self
    {
        // Nested group: where(function ($q) { $q->where('a', 1)->orWhere('b', 2); })
        if ($column instanceof \Closure) {
            return $this->whereGroup($column, 'AND');
        }

        // Two-argument form: where('id', 5)
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = is_array($value) ? 'in' : '=';
        }

        if (is_array($value) && in_array(strtolower((string) $operator), ['in', 'not in'], true)) {
            return $this->whereIn((string) $column, $value, strtolower((string) $operator) === 'not in');
        }

        if ($value === null && in_array(strtolower((string) $operator), ['=', 'is'], true)) {
            $this->wheres[] = ['type' => 'null', 'sql' => $this->wrap($column) . ' IS NULL'];

            return $this;
        }

        if ($value === null && strtolower((string) $operator) === '!=') {
            $this->wheres[] = ['type' => 'null', 'sql' => $this->wrap($column) . ' IS NOT NULL'];

            return $this;
        }

        $this->wheres[] = ['type' => 'basic', 'sql' => $this->wrap($column) . ' ' . $this->operator((string) $operator) . ' ?'];
        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(mixed $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof \Closure) {
            return $this->whereGroup($column, 'OR');
        }

        $before = count($this->wheres);
        $this->where($column, ...array_slice(func_get_args(), 1));

        for ($i = $before; $i < count($this->wheres); $i++) {
            $this->wheres[$i]['boolean'] = 'OR';
        }

        return $this;
    }

    /** @param array<int,mixed> $values */
    public function whereIn(string $column, array $values, bool $not = false): self
    {
        $values = array_values($values);

        if ($values === []) {
            // Empty IN () is never valid SQL; short-circuit the logic instead.
            $this->wheres[] = ['type' => 'raw', 'sql' => $not ? '1 = 1' : '1 = 0'];

            return $this;
        }

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = ['type' => 'basic', 'sql' => $this->wrap($column) . ($not ? ' NOT IN ' : ' IN ') . '(' . $placeholders . ')'];

        foreach ($values as $value) {
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, true);
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'sql' => $this->wrap($column) . ' IS NULL'];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['type' => 'null', 'sql' => $this->wrap($column) . ' IS NOT NULL'];

        return $this;
    }

    /** @param array<int,mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = ['type' => 'raw', 'sql' => '(' . $sql . ')'];

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /**
     * Wrap a set of conditions in parentheses. Used for OR groups so a filter
     * like "(email = ? OR ip = ?)" can never leak into the surrounding AND chain.
     */
    private function whereGroup(\Closure $callback, string $boolean = 'AND'): self
    {
        $nested = new self($this->table, $this->connection);
        $callback($nested);

        $sql = $nested->compileWheres();

        if ($sql === '') {
            return $this;
        }

        $this->wheres[] = ['type' => 'group', 'boolean' => $boolean, 'sql' => '(' . $sql . ')'];

        foreach ($nested->bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    /** @param array{0:mixed,1:mixed} $range */
    public function whereBetween(string $column, array $range): self
    {
        $this->wheres[] = ['type' => 'basic', 'sql' => $this->wrap($column) . ' BETWEEN ? AND ?'];
        $this->bindings[] = $range[0] ?? null;
        $this->bindings[] = $range[1] ?? null;

        return $this;
    }

    /**
     * Case-insensitive term search across several columns — the backbone of the
     * admin list search and the public site search.
     *
     * @param array<int,string> $columns
     */
    public function whereLikeAny(array $columns, string $term): self
    {
        $term = trim($term);

        if ($term === '' || $columns === []) {
            return $this;
        }

        $parts = [];

        foreach ($columns as $column) {
            $parts[] = 'LOWER(' . $this->wrap($column) . ') LIKE ?';
            $this->bindings[] = '%' . mb_strtolower($term, 'UTF-8') . '%';
        }

        $this->wheres[] = ['type' => 'raw', 'sql' => '(' . implode(' OR ', $parts) . ')'];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = strtoupper($type) . ' JOIN ' . $this->wrapTable($table) . ' ON '
            . $this->wrap($first) . ' ' . $this->operator($operator) . ' ' . $this->wrap($second);

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->wrap($column);
        }

        return $this;
    }

    /** @param array<int,mixed> $bindings */
    public function having(string $sql, array $bindings = []): self
    {
        $this->havings[] = $sql;

        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }

        return $this;
    }

    public function orderBy(mixed $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $column instanceof Raw
            ? $column->value . ' ' . $direction
            : $this->wrap($column) . ' ' . $direction;

        return $this;
    }

    public function orderByRaw(string $sql): self
    {
        $this->orders[] = $sql;

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    public function forPage(int $page, int $perPage): self
    {
        $page = max(1, $page);

        return $this->limit(max(1, $perPage))->offset(($page - 1) * max(1, $perPage));
    }

    /* --------------------------------------------------------------------- */
    /* Reads                                                                 */
    /* --------------------------------------------------------------------- */

    /** @return array<int,array<string,mixed>> */
    public function get(): array
    {
        [$sql, $bindings] = $this->compileSelect();

        return Database::select($sql, $bindings);
    }

    /** @return array<string,mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit = 1;
        $clone->offset = null;
        $rows = $clone->get();

        return $rows[0] ?? null;
    }

    public function value(string $column): mixed
    {
        $row = (clone $this)->select($column)->first();

        return $row === null ? null : array_values($row)[0];
    }

    /** @return array<int|string,mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        $rows = (clone $this)->get();
        $result = [];

        foreach ($rows as $row) {
            if ($key !== null && isset($row[$key])) {
                $result[$row[$key]] = $row[$column] ?? null;
            } else {
                $result[] = $row[$column] ?? null;
            }
        }

        return $result;
    }

    public function count(string $column = '*'): int
    {
        $query = clone $this;
        $query->columns = ['COUNT(' . ($column === '*' ? '*' : $this->wrap($column)) . ') AS aggregate'];
        $query->orders = [];
        $query->limit = null;
        $query->offset = null;

        $value = Database::scalar($query->toSql(), $query->bindings);

        return (int) ($value ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): float
    {
        $query = clone $this;
        $query->columns = ['COALESCE(SUM(' . $this->wrap($column) . '), 0) AS aggregate'];
        $query->orders = [];

        return (float) (Database::scalar($query->toSql(), $query->bindings) ?? 0);
    }

    public function max(string $column): mixed
    {
        $query = clone $this;
        $query->columns = ['MAX(' . $this->wrap($column) . ') AS aggregate'];
        $query->orders = [];

        return Database::scalar($query->toSql(), $query->bindings);
    }

    /** @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int,from:int,to:int} */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);
        $total = $this->count();

        $data = $this->forPage($page, $perPage)->get();

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
            'to' => min($total, $page * $perPage),
        ];
    }

    /* --------------------------------------------------------------------- */
    /* Writes                                                                */
    /* --------------------------------------------------------------------- */

    public function insert(array $values): int
    {
        return $this->insertGetId($values);
    }

    public function insertGetId(array $values): int
    {
        $columns = array_keys($values);
        $sql = 'INSERT INTO ' . $this->wrapTable($this->table)
            . ' (' . implode(', ', array_map(fn (string $column): string => $this->wrap($column), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $statement = $this->connection->prepare($sql);
        $statement->execute($this->normalise(array_values($values)));

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function insertMany(array $rows): int
    {
        $inserted = 0;

        foreach ($rows as $row) {
            if (is_array($row) && $row !== []) {
                $this->insertGetId($row);
                $inserted++;
            }
        }

        return $inserted;
    }

    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $assignments = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            if ($value instanceof Raw) {
                $assignments[] = $this->wrap($column) . ' = ' . $value->value;
                continue;
            }

            $assignments[] = $this->wrap($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = 'UPDATE ' . $this->wrapTable($this->table) . ' SET ' . implode(', ', $assignments);

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
            $bindings = array_merge($bindings, $this->bindings);
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($this->normalise($bindings));

        return $statement->rowCount();
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->wrapTable($this->table);

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($this->normalise($this->bindings));

        return $statement->rowCount();
    }

    public function increment(string $column, int|float $amount = 1): int
    {
        $sql = 'UPDATE ' . $this->wrapTable($this->table)
            . ' SET ' . $this->wrap($column) . ' = ' . $this->wrap($column) . ' + ?';

        $bindings = [$amount];

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
            $bindings = array_merge($bindings, $this->bindings);
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($this->normalise($bindings));

        return $statement->rowCount();
    }

    /** Insert or update on a unique key (MySQL + SQLite 3.24+). */
    public function upsert(array $values, array $uniqueBy, array $updateColumns = []): void
    {
        $columns = array_keys($values);
        $sql = 'INSERT INTO ' . $this->wrapTable($this->table)
            . ' (' . implode(', ', array_map(fn (string $column): string => $this->wrap($column), $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $update = $updateColumns !== [] ? $updateColumns : array_values(array_diff($columns, $uniqueBy));

        if (Database::isSqlite()) {
            $assignments = [];

            foreach ($update as $column) {
                $assignments[] = $this->wrap($column) . ' = excluded.' . $this->wrap($column);
            }

            $sql .= ' ON CONFLICT (' . implode(', ', array_map(fn (string $column): string => $this->wrap($column), $uniqueBy)) . ')'
                . ($assignments === [] ? ' DO NOTHING' : ' DO UPDATE SET ' . implode(', ', $assignments));
        } else {
            $assignments = [];

            foreach ($update as $column) {
                $assignments[] = $this->wrap($column) . ' = VALUES(' . $this->wrap($column) . ')';
            }

            $sql .= $assignments === []
                ? ' ON DUPLICATE KEY UPDATE ' . $this->wrap($uniqueBy[0] ?? $columns[0]) . ' = ' . $this->wrap($uniqueBy[0] ?? $columns[0])
                : ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
        }

        $statement = $this->connection->prepare($sql);
        $statement->execute($this->normalise(array_values($values)));
    }

    /* --------------------------------------------------------------------- */
    /* Introspection                                                         */
    /* --------------------------------------------------------------------- */

    public function toSql(): string
    {
        [$sql] = $this->compileSelect();

        return $sql;
    }

    public function tableName(): string
    {
        return $this->table;
    }

    private function compileSelect(): array
    {
        $columns = $this->columns === ['*']
            ? '*'
            : implode(', ', array_map(fn (mixed $column): string => $this->columnExpression($column), $this->columns));

        $sql = 'SELECT ' . $columns . ' FROM ' . $this->wrapTable($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }

        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if ($this->havings !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int) $this->limit;

            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . (int) $this->offset;
            }
        } elseif ($this->offset !== null) {
            // MySQL requires a LIMIT clause when OFFSET is used.
            $sql .= Database::isSqlite() ? ' LIMIT -1 OFFSET ' . (int) $this->offset : ' LIMIT 18446744073709551615 OFFSET ' . (int) $this->offset;
        }

        return [$sql, $this->bindings];
    }

    private function compileWheres(): string
    {
        $sql = '';

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : ' ' . ($where['boolean'] ?? 'AND') . ' ';
            $sql .= $boolean . $where['sql'];
        }

        return $sql;
    }

    /** @param array<int,mixed> $values */
    private function normalise(array $values): array
    {
        foreach ($values as $index => $value) {
            if (is_bool($value)) {
                $values[$index] = $value ? 1 : 0;
            } elseif (is_array($value)) {
                $values[$index] = Json::encode($value);
            } elseif ($value instanceof \DateTimeInterface) {
                $values[$index] = $value->format('Y-m-d H:i:s');
            }
        }

        return $values;
    }

    private function columnExpression(mixed $column): string
    {
        if ($column instanceof Raw) {
            return $column->value;
        }

        $column = (string) $column;

        // Expressions (COUNT(*), COALESCE(...), CASE ...) are passed through as-is.
        if (preg_match('/[()\'"]/', $column) === 1) {
            return $column;
        }

        return $this->wrap($column);
    }

    /** Quote an identifier, keeping table aliases and JSON paths intact. */
    public function wrap(mixed $value): string
    {
        if ($value instanceof Raw) {
            return $value->value;
        }

        $value = trim((string) $value);

        if ($value === '' || $value === '*') {
            return '*';
        }

        if (preg_match('/[()\'"`]/', $value) === 1) {
            return $value;
        }

        $alias = null;

        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $value, $matches) === 1) {
            $value = trim($matches[1]);
            $alias = trim($matches[2]);
        }

        $segments = explode('.', $value);
        $wrapped = implode('.', array_map(
            static fn (string $segment): string => $segment === '*' ? '*' : '`' . preg_replace('/[^A-Za-z0-9_$]/', '', $segment) . '`',
            $segments
        ));

        return $alias !== null ? $wrapped . ' AS `' . preg_replace('/[^A-Za-z0-9_$]/', '', $alias) . '`' : $wrapped;
    }

    private function wrapTable(string $table): string
    {
        if (preg_match('/[()\s]/', $table) === 1) {
            return $table;
        }

        return '`' . preg_replace('/[^A-Za-z0-9_$]/', '', $table) . '`';
    }

    private function operator(string $operator): string
    {
        $operator = strtolower(trim($operator));

        return match ($operator) {
            '=', 'eq' => '=',
            '!=', '<>', 'ne' => '<>',
            '<', 'lt' => '<',
            '>', 'gt' => '>',
            '<=', 'lte' => '<=',
            '>=', 'gte' => '>=',
            'like' => 'LIKE',
            'not like' => 'NOT LIKE',
            'is' => 'IS',
            'is not' => 'IS NOT',
            default => '=',
        };
    }
}
