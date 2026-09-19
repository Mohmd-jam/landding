<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Cache;
use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use App\Core\Str;
use App\Models\Admin;
use App\Models\Setting;
use Database\Migrator;
use Database\Seeder;

/**
 * Tiny console kernel: command name → method. Output is plain text so it can be
 * piped (used by tools/cli.mjs and by deployment scripts).
 */
final class Kernel
{
    private array $args;

    public function __construct(array $args = [])
    {
        $this->args = $args;
    }

    public function handle(string $command): int
    {
        $method = 'command' . str_replace(' ', '', ucwords(str_replace([':', '-'], ' ', $command)));

        if (!method_exists($this, $method)) {
            $this->error('Unknown command: ' . $command);
            $this->help();

            return 1;
        }

        try {
            return (int) $this->{$method}();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            if (Config::get('app.debug')) {
                $this->line($e->getFile() . ':' . $e->getLine());
                $this->line($e->getTraceAsString());
            }
            Logger::exception($e);

            return 1;
        }
    }

    /* --------------------------------------------------------------------- */
    /* Commands                                                              */
    /* --------------------------------------------------------------------- */

    private function commandHelp(): int
    {
        $this->help();

        return 0;
    }

    private function help(): void
    {
        $this->line('');
        $this->line('  Portfolio CMS — console');
        $this->line('  ------------------------------------------------------------');
        foreach ([
            'install [--force]' => 'create schema (if needed) and seed demo content',
            'migrate --fresh' => 'drop every table and recreate the schema',
            'seed [--fresh]' => 'load the bilingual demo dataset',
            'dump' => 'write database/schema.mysql.sql + schema.sqlite.sql',
            'stats' => 'row counts per table',
            'user:create --email --password --name --role' => 'create an admin account',
            'user:password --email --password' => 'reset an admin password',
            'cache:clear' => 'flush the application cache',
            'sql "SELECT …"' => 'run a read-only query',
        ] as $command => $description) {
            $this->line('   ' . str_pad($command, 46) . $description);
        }
        $this->line('');
    }

    private function commandInstall(): int
    {
        $this->line('▸ driver: ' . Database::driver());

        $migrator = new Migrator();
        $force = (bool) ($this->args['force'] ?? false);
        $log = $migrator->createAll($force);

        foreach ($log as $line) {
            $this->line('   ' . $line);
        }

        if (Database::isSqlite()) {
            $migrator->createSqliteIndexes();
        }

        $seeder = new Seeder();
        $seeded = $seeder->run($this->args);
        foreach ($seeded as $line) {
            $this->line('   ' . $line);
        }

        $migrator->writeDumps();
        $this->line('   wrote schema.mysql.sql + schema.sqlite.sql');

        $this->success('Installation complete.');
        $this->line('   Admin login: ' . $this->arg('email', 'admin@example.com') . ' / password from .env (ADMIN_PASSWORD)');

        return 0;
    }

    private function commandMigrate(): int
    {
        $migrator = new Migrator();
        $log = $migrator->createAll((bool) ($this->args['fresh'] ?? false));

        if (Database::isSqlite()) {
            $migrator->createSqliteIndexes();
        }

        foreach ($log as $line) {
            $this->line('   ' . $line);
        }

        $this->success('Schema is up to date on ' . Database::driver() . '.');

        return 0;
    }

    private function commandSeed(): int
    {
        $seeder = new Seeder();

        foreach ($seeder->run($this->args) as $line) {
            $this->line('   ' . $line);
        }

        $this->success('Demo content loaded.');

        return 0;
    }

    private function commandDump(): int
    {
        $migrator = new Migrator();
        $migrator->writeDumps();
        $this->success('Wrote backend/database/schema.mysql.sql and schema.sqlite.sql');

        return 0;
    }

    private function commandStats(): int
    {
        $tables = array_keys(Migrator::schema());
        $this->line('');
        $this->line('  table                          rows');
        $this->line('  ------------------------------------------------------------');

        $total = 0;
        foreach ($tables as $table) {
            try {
                $count = Database::table($table)->count();
            } catch (\Throwable) {
                $count = -1;
            }
            $total += max(0, $count);
            $this->line('  ' . str_pad($table, 30) . str_pad((string) $count, 8, ' ', STR_PAD_LEFT));
        }

        $this->line('  ------------------------------------------------------------');
        $this->line('  ' . str_pad('TOTAL', 30) . str_pad((string) $total, 8, ' ', STR_PAD_LEFT));
        $this->line('');

        return 0;
    }

    private function commandUserCreate(): int
    {
        $email = strtolower($this->arg('email', ''));
        $password = $this->arg('password', '');
        $name = $this->arg('name', 'Administrator');
        $role = $this->arg('role', 'super_admin');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return 1;
        }

        if ($password === '') {
            $password = Str::random(12) . 'Aa1!';
            $this->line('   generated password: ' . $password);
        }

        $issues = Security::passwordIssues($password);
        if ($issues !== []) {
            $this->error('Password too weak: ' . implode(', ', $issues));

            return 1;
        }

        $existing = Admin::findByEmail($email);

        if ($existing !== null) {
            Admin::update((int) $existing['id'], [
                'password_hash' => Security::hashPassword($password),
                'name' => $name,
                'role' => $role,
                'is_active' => 1,
                'failed_attempts' => 0,
                'locked_until' => null,
            ]);
            $this->success('Updated existing admin ' . $email);

            return 0;
        }

        Admin::create([
            'name' => $name,
            'email' => $email,
            'password_hash' => Security::hashPassword($password),
            'role' => $role,
            'is_active' => 1,
        ]);

        $this->success('Created admin ' . $email . ' (' . $role . ')');

        return 0;
    }

    private function commandUserPassword(): int
    {
        $email = strtolower($this->arg('email', ''));
        $password = $this->arg('password', '');
        $admin = Admin::findByEmail($email);

        if ($admin === null) {
            $this->error('No admin with email ' . $email);

            return 1;
        }

        if ($password === '') {
            $password = Str::random(12) . 'Aa1!';
            $this->line('   generated password: ' . $password);
        }

        Admin::update((int) $admin['id'], [
            'password_hash' => Security::hashPassword($password),
            'failed_attempts' => 0,
            'locked_until' => null,
            'remember_token' => null,
        ]);
        Admin::deleteSessionsFor((int) $admin['id']);

        $this->success('Password updated for ' . $email);

        return 0;
    }

    private function commandCacheClear(): int
    {
        Cache::flush();
        $this->success('Application cache flushed.');

        return 0;
    }

    private function commandSql(): int
    {
        $sql = $this->args['_positional'][0] ?? $this->arg('_query', '');

        if ($sql === '') {
            $this->error('Provide a query: node tools/cli.mjs sql "SELECT * FROM settings LIMIT 3"');

            return 1;
        }

        if (preg_match('/^\s*(select|pragma|explain|with)\b/i', $sql) !== 1) {
            $this->error('Only read-only queries are allowed here (SELECT / PRAGMA / EXPLAIN).');

            return 1;
        }

        $rows = Database::select($sql);
        $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');

        return 0;
    }

    /* --------------------------------------------------------------------- */

    private function arg(string $key, string $default = ''): string
    {
        $value = $this->args[$key] ?? $default;

        return is_string($value) ? $value : (string) $default;
    }

    private function line(string $text = ''): void
    {
        echo $text . "\n";
    }

    private function success(string $text): void
    {
        echo '✓ ' . $text . "\n";
    }

    private function error(string $text): void
    {
        echo '✗ ' . $text . "\n";
    }
}
