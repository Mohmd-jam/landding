<?php

declare(strict_types=1);

/**
 * Console entry point (executed by tools/cli.mjs through the PHP-WASM runtime).
 *
 * Commands arrive through the CLI_COMMAND environment variable and their options
 * through CLI_ARGS (JSON), because the WebAssembly runtime has no argv.
 *
 *   install   → migrate + seed + default admin
 *   migrate   → schema only (--fresh drops first)
 *   seed      → demo content only (--fresh clears content tables)
 *   stats     → row counts per table
 *   dump      → write schema.mysql.sql / schema.sqlite.sql
 *   sql       → read-only query runner
 *   user:create / user:password / cache:clear
 */

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Security;
use App\Models\Admin;
use Database\Migrator;
use Database\Seeder;

// PHP-WASM exposes php://stdout/php://stderr but not the STD* constants.
foreach (['STDOUT' => 'php://stdout', 'STDERR' => 'php://stderr'] as $constant => $stream) {
    if (!defined($constant)) {
        define($constant, fopen($stream, 'w'));
    }
}

require dirname(__DIR__) . '/bootstrap/app.php';

$command = (string) (getenv('CLI_COMMAND') ?: 'install');
$payload = json_decode((string) (getenv('CLI_ARGS') ?: '{}'), true);

// Accept the legacy ["command", "{...}"] list form as well.
if (isset($payload[0]) && is_string($payload[0])) {
    $command = $payload[0];
    $payload = json_decode((string) ($payload[1] ?? '{}'), true) ?: [];
}

if (!is_array($payload)) {
    $payload = [];
}

$fresh = (bool) ($payload['fresh'] ?? false);
$exit = 0;

$line = static function (string $text = ''): void {
    fwrite(STDOUT, $text . PHP_EOL);
};

$head = static function (string $title) use ($line): void {
    $line('');
    $line('────────────────────────────────────────────────────────────');
    $line('  ' . $title);
    $line('────────────────────────────────────────────────────────────');
};

try {
    $migrator = new Migrator();

    switch ($command) {
        case 'install':
            $head('Installing Portfolio CMS');
            $line('Driver   : ' . Database::driver());
            $line('Time zone: ' . Config::get('app.timezone', 'UTC'));

            foreach ($migrator->createAll($fresh) as $entry) {
                $line('  ' . $entry);
            }

            $seeder = new Seeder();
            foreach ($seeder->run(['fresh' => true]) as $entry) {
                $line('  ' . $entry);
            }

            $migrator->createSqliteIndexes();
            $migrator->writeDumps();
            $line('  schema dumps written to backend/database/');

            $head('Ready');
            $line('Admin URL : /admin');
            $line('Email     : ' . Env::get('ADMIN_EMAIL', 'admin@example.com'));
            $line('Password  : ' . Env::get('ADMIN_PASSWORD', 'Admin@12345'));
            $line('Next      : npm run build && npm run serve');
            break;

        case 'migrate':
            $head('Migrating database');
            foreach ($migrator->createAll($fresh) as $entry) {
                $line('  ' . $entry);
            }

            if ($migrator->isSqlite()) {
                $migrator->createSqliteIndexes();
            }

            $line('  done (' . Database::driver() . ')');
            break;

        case 'seed':
            $head('Seeding demo content');
            $seeder = new Seeder();

            foreach ($seeder->run(['fresh' => $fresh]) as $entry) {
                $line('  ' . $entry);
            }

            $line('  done');
            break;

        case 'stats':
            $head('Database statistics');
            $line(str_pad('Table', 34) . 'Rows');
            $line(str_repeat('─', 42));

            $total = 0;

            foreach (Database::tables() as $table) {
                $count = (int) Database::table($table)->count();
                $total += $count;
                $line(str_pad($table, 34) . $count);
            }

            $line(str_repeat('─', 42));
            $line(str_pad('TOTAL', 34) . $total);
            break;

        case 'dump':
            $head('Writing schema dumps');
            $migrator->writeDumps();
            $line('  backend/database/schema.mysql.sql');
            $line('  backend/database/schema.sqlite.sql');
            break;

        case 'sql':
            $query = (string) ($payload['_positional'][0] ?? $payload['query'] ?? '');

            if ($query === '') {
                $line('Usage: npm run db:stats  |  node tools/cli.mjs sql "SELECT …"');
                $exit = 1;
                break;
            }

            // Guard rail: the console is a debugging tool, not a write channel.
            if (preg_match('/^\s*(select|pragma|explain|with)\b/i', $query) !== 1) {
                $line('Only SELECT / PRAGMA / EXPLAIN / WITH statements are allowed here.');
                $exit = 1;
                break;
            }

            $statement = Database::statement($query);
            $rows = $statement->fetchAll();

            if ($rows === []) {
                $line('(no rows)');
                break;
            }

            $columns = array_keys($rows[0]);
            $widths = array_map(static fn ($column): int => max(12, strlen((string) $column) + 2), $columns);

            $line(implode('', array_map(static fn ($column, $i): string => str_pad((string) $column, $widths[$i]), $columns, array_keys($columns))));

            foreach ($rows as $row) {
                $cells = [];

                foreach (array_values($row) as $index => $value) {
                    $cells[] = str_pad(mb_substr((string) ($value ?? 'NULL'), 0, $widths[$index] - 1), $widths[$index]);
                }

                $line(implode('', $cells));
            }

            $line(sprintf('(%d row%s)', count($rows), count($rows) === 1 ? '' : 's'));
            break;

        case 'user:create':
        case 'user:password':
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            $password = (string) ($payload['password'] ?? '');

            if ($email === '' || $password === '') {
                $line('Usage: node tools/cli.mjs ' . $command . ' --email=you@example.com --password="…" [--name="…"] [--role=super_admin]');
                $exit = 1;
                break;
            }

            $problems = Security::passwordIssues($password);

            if ($problems !== []) {
                $line('Weak password: ' . implode(', ', $problems));
                $exit = 1;
                break;
            }

            $existing = Admin::findByEmail($email);

            if ($command === 'user:create' && $existing !== null) {
                Admin::update((int) $existing['id'], [
                    'password_hash' => Security::hashPassword($password),
                    'must_change_password' => 0,
                ]);
                $line('Updated existing admin ' . $email);
                break;
            }

            if ($existing !== null) {
                Admin::update((int) $existing['id'], [
                    'password_hash' => Security::hashPassword($password),
                    'must_change_password' => 0,
                ]);
                $line('Password updated for ' . $email);
                break;
            }

            $id = Admin::create([
                'name' => (string) ($payload['name'] ?? 'Administrator'),
                'email' => $email,
                'password_hash' => Security::hashPassword($password),
                'role' => (string) ($payload['role'] ?? 'super_admin'),
                'is_active' => 1,
            ]);

            $line('Created admin #' . $id . ' (' . $email . ')');
            break;

        case 'cache:clear':
            $removed = Cache::flush();
            $line('Cleared ' . $removed . ' cache file' . ($removed === 1 ? '' : 's'));
            break;

        default:
            $line('Unknown command: ' . $command);
            $line('Run `node tools/cli.mjs help` for the list of commands.');
            $exit = 1;
    }
} catch (Throwable $e) {
    fwrite(STDERR, PHP_EOL . 'error: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, 'at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);

    if ((bool) Config::get('app.debug', false)) {
        fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    }

    $exit = 1;
}

exit($exit);
