<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish request object wrapping PHP superglobals.
 *
 * Input access is deliberately explicit (`input`, `query`, `file`, `header`) so
 * controllers never touch $_POST/$_GET directly and every value goes through the
 * same normalisation (trim, dot-notation, array handling).
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $attributes = [];

    /** @var array<string,mixed> */
    private array $routeParams = [];

    /** @param array<string,mixed> $properties */
    private function __construct(private array $properties)
    {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');

        // Strip a possible sub-directory install (e.g. /portfolio/public).
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '');
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        // Method override for HTML forms that need PUT/DELETE semantics.
        $rawBody = (string) file_get_contents('php://input');
        $post = $_POST;
        $contentType = strtolower((string) ($server['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json') && $rawBody !== '') {
            $decoded = Json::decode($rawBody, null);

            if (is_array($decoded)) {
                $post = $decoded;
            }
        }

        return new self([
            'method' => $method,
            'real_method' => $method,
            'uri' => $uri,
            'path' => $path === '' ? '/' : $path,
            'query' => $_GET,
            'body' => $post,
            'raw_body' => $rawBody,
            'files' => $_FILES,
            'server' => $server,
            'headers' => self::readHeaders($server),
            'cookies' => $_COOKIE,
        ]);
    }

    /** Build a request from scratch (used by tests and the CLI runtime bridge). */
    public static function make(string $method, string $path, array $body = [], array $query = [], array $headers = [], array $cookies = []): self
    {
        return new self([
            'method' => strtoupper($method),
            'real_method' => strtoupper($method),
            'uri' => $path . ($query !== [] ? '?' . http_build_query($query) : ''),
            'path' => '/' . trim($path, '/'),
            'query' => $query,
            'body' => $body,
            'raw_body' => Json::encode($body),
            'files' => [],
            'server' => ['REQUEST_METHOD' => strtoupper($method), 'REMOTE_ADDR' => '127.0.0.1'],
            'headers' => array_change_key_case($headers, CASE_LOWER),
            'cookies' => $cookies,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /* Basics                                                                */
    /* --------------------------------------------------------------------- */

    public function method(): string
    {
        return (string) $this->properties['method'];
    }

    /** Let the kernel apply a form _method override without touching globals. */
    public function setMethod(string $method): void
    {
        $this->properties['method'] = strtoupper($method);
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isWrite(): bool
    {
        return in_array($this->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function uri(): string
    {
        return (string) $this->properties['uri'];
    }

    public function path(): string
    {
        return (string) $this->properties['path'];
    }

    public function segments(): array
    {
        return array_values(array_filter(explode('/', trim($this->path(), '/')), static fn (string $s): bool => $s !== ''));
    }

    public function fullPathWithQuery(): string
    {
        return $this->path() . ($this->queryString() !== '' ? '?' . $this->queryString() : '');
    }

    public function queryString(): string
    {
        return (string) ($this->properties['server']['QUERY_STRING'] ?? '');
    }

    /* --------------------------------------------------------------------- */
    /* Input                                                                 */
    /* --------------------------------------------------------------------- */

    /** Query-string value with dot notation (page.filters.status). */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        $query = (array) $this->properties['query'];

        return $key === null ? $query : self::pluck($query, $key, $default);
    }

    /** @return array<string,mixed> */
    public function body(): array
    {
        return (array) $this->properties['body'];
    }

    public function rawBody(): string
    {
        return (string) $this->properties['raw_body'];
    }

    /** Body first, then query string (the usual API expectation). */
    public function input(string $key, mixed $default = null): mixed
    {
        $body = (array) $this->properties['body'];
        $value = self::pluck($body, $key, '__missing__');

        if ($value !== '__missing__') {
            return $value;
        }

        $value = self::pluck((array) $this->properties['query'], $key, '__missing__');

        return $value === '__missing__' ? $default : $value;
    }

    public function has(string $key): bool
    {
        return $this->input($key, '__missing__') !== '__missing__';
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);

        return $value !== null && $value !== '' && $value !== [];
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return in_array($value, [true, 1, '1', 'on', 'true', 'yes'], true);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function array(string $key): array
    {
        $value = $this->input($key, []);

        return is_array($value) ? $value : [];
    }

    /** @param array<int,string> $keys @return array<string,mixed> */
    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $result[$key] = $this->input($key);
            }
        }

        return $result;
    }

    /**
     * Validate this request and return only the validated keys.
     *
     * @param array<string,array<int,string>|string> $rules
     * @return array<string,mixed>
     */
    public function validate(array $rules, array $messages = []): array
    {
        return Validator::make($this->body() + $this->query(), $rules, $messages)->validate();
    }

    /* --------------------------------------------------------------------- */
    /* Transport details                                                     */
    /* --------------------------------------------------------------------- */

    /** @return array<string,string> */
    public function headers(): array
    {
        return (array) $this->properties['headers'];
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $value = $this->properties['headers'][strtolower($name)] ?? null;

        return $value === null ? $default : (string) $value;
    }

    public function cookie(string $name, ?string $default = null): ?string
    {
        $value = $this->properties['cookies'][$name] ?? null;

        return $value === null ? $default : (string) $value;
    }

    /** @return array<string,mixed> */
    public function cookies(): array
    {
        return (array) $this->properties['cookies'];
    }

    /** @return array<string,mixed> */
    public function files(): array
    {
        return (array) $this->properties['files'];
    }

    /** @return array<string,mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->properties['files'][$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /** @return array<int,array<string,mixed>> */
    public function fileList(string $key): array
    {
        $file = $this->properties['files'][$key] ?? null;

        if (!is_array($file) || !is_array($file['name'] ?? null)) {
            return [];
        }

        $files = [];

        foreach (array_keys($file['name']) as $index) {
            $files[] = [
                'name' => $file['name'][$index],
                'type' => $file['type'][$index] ?? '',
                'tmp_name' => $file['tmp_name'][$index] ?? '',
                'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $file['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->properties['server'][$key] ?? $default;
    }

    public function ip(): string
    {
        $forwarded = $this->header('x-forwarded-for');

        if ($forwarded !== null && $forwarded !== '') {
            $first = trim(explode(',', $forwarded)[0]);

            if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        $remote = (string) ($this->properties['server']['REMOTE_ADDR'] ?? '');

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->properties['server']['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referer(): ?string
    {
        $referer = (string) ($this->properties['server']['HTTP_REFERER'] ?? '');

        return $referer === '' ? null : $referer;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('x-requested-with', '')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        return $this->isAjax()
            || str_contains(strtolower((string) $this->header('accept', '')), 'application/json')
            || str_starts_with($this->path(), '/api/');
    }

    public function isSecure(): bool
    {
        $https = (string) ($this->properties['server']['HTTPS'] ?? '');

        return ($https !== '' && strtolower($https) !== 'off')
            || strtolower((string) $this->header('x-forwarded-proto', '')) === 'https';
    }

    public function baseUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = (string) ($this->properties['server']['HTTP_HOST'] ?? 'localhost');

        return $scheme . '://' . $host;
    }

    /** @return array<string,mixed> */
    public function serverAll(): array
    {
        return (array) $this->properties['server'];
    }

    /* --------------------------------------------------------------------- */
    /* Route context                                                         */
    /* --------------------------------------------------------------------- */

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /** Dot-notation read from a nested array. */
    private static function pluck(array $source, string $key, mixed $default): mixed
    {
        if (array_key_exists($key, $source)) {
            return $source[$key];
        }

        $value = $source;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string,string> */
    private static function readHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[strtolower(str_replace('_', '-', $key))] = (string) $value;
            }
        }

        return $headers;
    }
}
