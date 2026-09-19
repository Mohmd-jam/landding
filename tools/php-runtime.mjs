/**
 * Shared PHP runtime loader.
 *
 * The application targets PHP 8.3 behind Apache/Nginx + PHP-FPM. This sandbox
 * has no native PHP binary, so the very same codebase is executed through the
 * `@php-wasm/node` runtime: a real PHP 8.3 interpreter compiled to WebAssembly
 * with PDO (mysql + sqlite), mbstring, fileinfo, sessions, gd, zip and curl.
 *
 * `tools/server.mjs` (HTTP) and `tools/cli.mjs` (console) both use this module,
 * so exactly one place knows how to boot PHP.
 */
import { PHP } from '@php-wasm/universal';
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { existsSync, mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
export const PUBLIC_DIR = path.join(ROOT, 'public');
export const FRONT_CONTROLLER = path.join(PUBLIC_DIR, 'index.php');
export const STORAGE_DIR = path.join(ROOT, 'storage');

let php = null;
let bootPromise = null;

/** Serialises PHP execution — one interpreter handles one request at a time. */
let queue = Promise.resolve();

export function enqueue(task) {
    const run = queue.then(task, task);
    queue = run.then(
        () => undefined,
        () => undefined
    );
    return run;
}

export async function bootPhp({ version = '8.3', processId = 1 } = {}) {
    if (php) return php;
    if (bootPromise) return bootPromise;

    bootPromise = (async () => {
        for (const dir of ['sessions', 'cache', 'logs', 'mail', 'backups']) {
            const target = path.join(STORAGE_DIR, dir);
            if (!existsSync(target)) mkdirSync(target, { recursive: true });
        }

        const id = await loadNodeRuntime(version, { emscriptenOptions: { processId } });

        php = new PHP(id);
        useHostFilesystem(php);
        php.chdir(ROOT);

        return php;
    })();

    return bootPromise;
}

export function getPhp() {
    return php;
}

/**
 * PHP-WASM surfaces a non-zero exit code through a thrown error whose message
 * embeds the captured stdout/stderr. Normalise that back into a result object.
 */
function normaliseFailure(error) {
    const message = String(error?.message ?? error);
    const stdoutMatch = message.match(/=== Stdout ===([\s\S]*?)(?:=== Stderr ===|$)/);
    const stderrMatch = message.match(/=== Stderr ===([\s\S]*?)(?:\n\s+at |$)/);

    return {
        stdout: stdoutMatch ? stdoutMatch[1].trim() : '',
        stderr: stderrMatch ? stderrMatch[1].trim() : message,
        exitCode: 1,
        status: 500,
    };
}

/**
 * Run `scriptPath` inside PHP with CLI-ish superglobals.
 *
 * @param {object} options
 * @param {string} options.scriptPath absolute path of the PHP file
 * @param {Record<string,unknown>} [options.payload] console command arguments (CLI_ARGS)
 * @param {Record<string,string>} [options.env]
 */
export async function runScript({ scriptPath, payload = {}, env = {}, cwd = ROOT, method = 'GET', uri = '/' }) {
    const runtime = await bootPhp();
    if (cwd !== ROOT) runtime.chdir(cwd);

    try {
        const result = await runtime.run({
            scriptPath,
            relativeUri: uri,
            method,
            env: { ...env, CLI_ARGS: JSON.stringify(payload) },
            $_SERVER: {
                SCRIPT_FILENAME: scriptPath,
                SCRIPT_NAME: path.relative(PUBLIC_DIR, scriptPath),
                DOCUMENT_ROOT: PUBLIC_DIR,
                REQUEST_METHOD: method,
                REQUEST_URI: uri,
                HTTP_HOST: 'localhost',
                REMOTE_ADDR: '127.0.0.1',
            },
        });

        return {
            stdout: new TextDecoder().decode(result.bytes),
            stderr: result.errors ?? '',
            exitCode: result.exitCode,
            status: result.httpStatusCode,
        };
    } catch (error) {
        return normaliseFailure(error);
    }
}
