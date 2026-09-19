<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thin active-record base class.
 *
 * Models describe the table, the casts and the translatable columns; all actual
 * querying goes through the QueryBuilder so SQL stays reviewable. Repositories
 * and Services build the multi-table, multilingual reads on top of this.
 */
abstract class Model
{
    /** Database table name. */
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    protected static bool $timestamps = true;

    /** Columns that should always be cast when a row is read. */
    protected static array $casts = [];

    /** Columns accepted from request payloads (whitelist — never mass-assign blindly). */
    protected static array $fillable = [];

    /** Translatable columns, stored in <table>_translations. */
    protected static array $translatable = [];

    protected static string $translationTable = '';

    protected static string $translationForeignKey = '';

    public static function table(): string
    {
        return static::$table;
    }

    public static function translationTable(): ?string
    {
        return static::$translationTable !== '' ? static::$translationTable : null;
    }

    public static function translationForeignKey(): string
    {
        return static::$translationForeignKey !== '' ? static::$translationForeignKey : static::$table . '_id';
    }

    /** @return array<int,string> */
    public static function translatable(): array
    {
        return static::$translatable;
    }

    /** @return array<int,string> */
    public static function fillable(): array
    {
        return static::$fillable;
    }

    public static function query(): QueryBuilder
    {
        return Database::table(static::$table);
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $row = static::query()->where(static::$primaryKey, $id)->first();

        return $row === null ? null : static::cast($row);
    }

    /** @return array<string,mixed>|null */
    public static function findBy(string $column, mixed $value): ?array
    {
        $row = static::query()->where($column, $value)->first();

        return $row === null ? null : static::cast($row);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $orderBy = 'id', string $direction = 'ASC'): array
    {
        return static::castMany(static::query()->orderBy($orderBy, $direction)->get());
    }

    /** @param array<string,mixed> $columns */
    public static function create(array $columns): int
    {
        $values = static::filter($columns);

        if (static::$timestamps) {
            $now = self::now();
            $values['created_at'] = $values['created_at'] ?? $now;
            $values['updated_at'] = $values['updated_at'] ?? $now;
        }

        return static::query()->insertGetId($values);
    }

    /** @param array<string,mixed> $columns */
    public static function update(int $id, array $columns): int
    {
        $values = static::filter($columns);

        if (static::$timestamps) {
            $values['updated_at'] = self::now();
        }

        return $values === [] ? 0 : static::query()->where(static::$primaryKey, $id)->update($values);
    }

    public static function delete(int $id): bool
    {
        return static::query()->where(static::$primaryKey, $id)->delete() > 0;
    }

    public static function count(array $where = []): int
    {
        $query = static::query();

        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }

        return $query->count();
    }

    /**
     * Keep only fillable columns (with the primary key and timestamp columns
     * handled explicitly).
     *
     * @param array<string,mixed> $columns
     * @return array<string,mixed>
     */
    public static function filter(array $columns): array
    {
        if (static::$fillable === []) {
            return $columns;
        }

        $allowed = array_flip(array_merge(static::$fillable, [static::$primaryKey, 'created_at', 'updated_at']));

        return array_intersect_key($columns, $allowed);
    }

    /** @param array<string,mixed> $row */
    public static function cast(array $row): array
    {
        foreach (static::$casts as $column => $type) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                continue;
            }

            $row[$column] = match ($type) {
                'int' => (int) $row[$column],
                'float' => (float) $row[$column],
                'bool' => in_array($row[$column], [1, '1', true, 'true'], true),
                'json' => Json::decode((string) $row[$column], []),
                default => $row[$column],
            };
        }

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function castMany(array $rows): array
    {
        return array_map(static fn (array $row): array => static::cast($row), $rows);
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return gmdate('Y-m-d');
    }

    protected static function storage(): string
    {
        return (string) Config::get('app.storage_path', sys_get_temp_dir());
    }

    /** Next sort_order for a table, used by every reorderable list. */
    public static function nextSortOrder(string $scopeColumn = '', mixed $scopeValue = null): int
    {
        $query = static::query();

        if ($scopeColumn !== '' && $scopeValue !== null) {
            $query->where($scopeColumn, $scopeValue);
        }

        $max = $query->max('sort_order');

        return (int) $max + 1;
    }
}
