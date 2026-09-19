<?php

declare(strict_types=1);

/**
 * PHP syntax checker used by `node tools/cli.mjs lint`.
 *
 * The WebAssembly runtime cannot spawn `php -l` as a subprocess, but compiling
 * a source file through `token_get_all($code, TOKEN_PARSE)` performs exactly the
 * same parse/compile pass: any syntax error surfaces as a ParseError.
 *
 * Input : CLI_ARGS = {"files": ["/abs/path.php", …]}
 * Output: one line per file — "ok <path>" or "fail <path> :: <message>"
 */

foreach (['STDOUT' => 'php://stdout', 'STDERR' => 'php://stderr'] as $constant => $stream) {
    if (!defined($constant)) {
        define($constant, fopen($stream, 'w'));
    }
}

$payload = json_decode((string) (getenv('CLI_ARGS') ?: '{}'), true);

if (!is_array($payload)) {
    $payload = [];
}

$files = $payload['files'] ?? [];

if (!is_array($files) || $files === []) {
    fwrite(STDOUT, "fail - :: no files given\n");
    exit(1);
}

$failed = 0;

foreach ($files as $file) {
    $file = (string) $file;

    if (!is_file($file)) {
        $failed++;
        fwrite(STDOUT, 'fail ' . $file . " :: file not found\n");
        continue;
    }

    $source = (string) file_get_contents($file);

    try {
        token_get_all($source, TOKEN_PARSE);
        fwrite(STDOUT, 'ok ' . $file . "\n");
    } catch (ParseError $e) {
        $failed++;
        fwrite(STDOUT, 'fail ' . $file . ' :: ' . $e->getMessage() . ' (line ' . $e->getLine() . ")\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDOUT, 'fail ' . $file . ' :: ' . $e->getMessage() . "\n");
    }
}

exit($failed > 0 ? 1 : 0);
