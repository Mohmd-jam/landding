<?php

declare(strict_types=1);

/**
 * Console entry point.
 *
 * Works in two environments:
 *   • native PHP        → php backend/console.php migrate --fresh
 *   • WASM runtime      → node tools/cli.mjs migrate --fresh
 *     (arguments arrive through the CLI_COMMAND / CLI_ARGS environment vars,
 *      because the WebAssembly runtime has no argv superglobal)
 */

use App\Console\Kernel;

require __DIR__ . '/bootstrap/app.php';
require __DIR__ . '/app/Console/Kernel.php';

$argv = $_SERVER['argv'] ?? [];

$command = getenv('CLI_COMMAND') ?: ($argv[1] ?? 'help');
$rawPayload = getenv('CLI_ARGS') ?: ($argv[2] ?? '{}');
$payload = json_decode((string) $rawPayload, true);
$payload = is_array($payload) ? $payload : [];

// `--flag` style arguments are parsed by tools/cli.mjs and arrive as a map;
// native usage passes a JSON object as the second argument.
if (array_is_list($payload) && $payload !== []) {
    $nested = json_decode((string) ($payload[1] ?? '{}'), true);
    $payload = is_array($nested) ? $nested : [];
}

$kernel = new Kernel(array_merge($payload, ['_command' => $command]));

exit($kernel->handle((string) $command));
