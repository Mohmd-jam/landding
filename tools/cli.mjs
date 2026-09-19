#!/usr/bin/env node
/**
 * Console bridge: `node tools/cli.mjs <command> [options]`.
 *
 * The application console is written in PHP (backend/database/install.php and
 * backend/app/Console/Kernel.php). Because this sandbox has no native PHP
 * binary, the command line is executed inside the same PHP-WASM runtime that
 * serves HTTP — real PDO, real migrations, real seeds.
 *
 * Commands
 *   install                 create schema + seed demo content + default admin
 *   migrate [--fresh]       create missing tables (--fresh drops everything)
 *   seed [--fresh]          load demo content (--fresh clears content tables)
 *   stats                   row counts per table
 *   dump                    write database/schema.{mysql,sqlite}.sql
 *   sql <query>             run a read-only query
 *   user:create             create/update an admin account
 *   user:password           change an admin password
 *   cache:clear             delete cached settings/translations
 *   php <file> [json]       run an arbitrary PHP script with payload
 *   lint [files...]         php -l over the project (and the view files)
 */
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { ROOT, runScript } from './php-runtime.mjs';

const [command = 'help', ...rest] = process.argv.slice(2);

const flags = {};
const positional = [];

for (const arg of rest) {
    if (arg.startsWith('--')) {
        const [key, value] = arg.slice(2).split('=');
        flags[key] = value ?? true;
    } else {
        positional.push(arg);
    }
}

function collectPhpFiles(directory, files = []) {
    for (const entry of readdirSync(directory)) {
        if (['node_modules', '.git', 'vendor', 'storage', 'public/app'].includes(entry)) continue;
        const full = path.join(directory, entry);
        const stats = statSync(full);
        if (stats.isDirectory()) collectPhpFiles(full, files);
        else if (entry.endsWith('.php')) files.push(full);
    }
    return files;
}

async function lint() {
    // Explicit targets: files are checked as given, directories are walked.
    const targets = positional.length
        ? positional.flatMap((target) => {
              const resolved = path.resolve(target);
              return statSync(resolved).isDirectory() ? collectPhpFiles(resolved) : [resolved];
          })
        : collectPhpFiles(ROOT);

    if (targets.length === 0) {
        console.log('No PHP files found.');
        return 0;
    }

    // One interpreter run checks every file: token_get_all(…, TOKEN_PARSE) is
    // the same compile pass `php -l` performs (see backend/database/lint.php).
    const result = await runScript({
        scriptPath: path.join(ROOT, 'backend/database/lint.php'),
        payload: { files: targets },
    });

    const failures = result.stdout
        .split('\n')
        .filter((line) => line.startsWith('fail '))
        .map((line) => line.slice(5));

    for (const failure of failures) {
        const [file, message] = failure.split(' :: ');
        console.log(`✗ ${path.relative(ROOT, file)}`);
        console.log(`    ${message}`);
    }

    const ok = result.exitCode === 0 && failures.length === 0;
    console.log(ok ? `✓ ${targets.length} PHP files pass syntax check` : `✗ ${failures.length || 1} of ${targets.length} file(s) failed`);
    return ok ? 0 : 1;
}

async function main() {
    if (command === 'lint') {
        return lint();
    }

    if (command === 'help' || command === '--help' || command === '-h') {
        console.log(
            [
                'Portfolio CMS console',
                '',
                'Usage: node tools/cli.mjs <command> [options]',
                '',
                '  install                    create schema, seed demo content, create the default admin',
                '  migrate [--fresh]          apply the schema (--fresh recreates every table)',
                '  seed [--fresh]             load demo content',
                '  stats                      show row counts',
                '  dump                       write schema.mysql.sql + schema.sqlite.sql',
                '  sql "<query>"              run a read-only query',
                '  user:create --email= --password= [--name= --role=]',
                '  user:password --email= --password=',
                '  cache:clear                flush cached settings/translations',
                '  php <script> [json]        run a script inside the PHP runtime',
                '  lint [files..]             PHP syntax check',
            ].join('\n')
        );
        return 0;
    }

    // `php <script>` escapes to a raw script run (used by tools/test.mjs).
    if (command === 'php') {
        const script = positional[0];
        if (!script) {
            console.error('Usage: node tools/cli.mjs php <script.php> [json-payload]');
            return 1;
        }
        const payload = positional[1] ? JSON.parse(positional[1]) : {};
        const result = await runScript({ scriptPath: path.resolve(script), payload });
        process.stdout.write(result.stdout);
        if (result.stderr) process.stderr.write(`\n${result.stderr}\n`);
        return result.exitCode === 0 ? 0 : 1;
    }

    const payload = { ...flags, _positional: positional, _command: command, _root: ROOT };
    const result = await runScript({
        scriptPath: path.join(ROOT, 'backend/database/install.php'),
        payload,
        env: { CLI_COMMAND: command },
    });

    process.stdout.write(result.stdout);
    if (result.stderr && result.exitCode !== 0) {
        process.stderr.write(`\n${result.stderr}\n`);
    }

    return result.exitCode === 0 ? 0 : 1;
}

try {
    process.exitCode = await main();
} catch (error) {
    console.error(error);
    process.exitCode = 1;
} finally {
    // The PHP-WASM runtime keeps the event loop alive; exit explicitly.
    setTimeout(() => process.exit(process.exitCode ?? 0), 50).unref();
}
