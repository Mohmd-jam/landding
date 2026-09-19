<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object: content, status, headers and cookies.
 *
 * Controllers always return one of these; the kernel adds the security headers
 * afterwards, which keeps header policy in exactly one place.
 */
final class Response
{
    /** @var array<string,mixed> */
    private array $headers = [];

    private string $content = '';

    private ?string $filePath = null;

    private ?string $fileName = null;

    private function __construct(private int $status = 200, private array $cookies = [])
    {
    }

    /* --------------------------------------------------------------------- */
    /* Factories                                                             */
    /* --------------------------------------------------------------------- */

    public static function json(mixed $data, int $status = 200): self
    {
        $response = new self($status);
        $response->headers['Content-Type'] = 'application/json; charset=utf-8';
        $response->content = Json::encode($data);

        return $response;
    }

    public static function html(string $html, int $status = 200): self
    {
        $response = new self($status);
        $response->headers['Content-Type'] = 'text/html; charset=utf-8';
        $response->content = $html;

        return $response;
    }

    public static function text(string $text, int $status = 200): self
    {
        $response = new self($status);
        $response->headers['Content-Type'] = 'text/plain; charset=utf-8';
        $response->content = $text;

        return $response;
    }

    public static function xml(string $xml, int $status = 200): self
    {
        $response = new self($status);
        $response->headers['Content-Type'] = 'application/xml; charset=utf-8';
        $response->content = $xml;

        return $response;
    }

    public static function redirect(string $location, int $status = 302): self
    {
        $response = new self($status);
        $response->headers['Location'] = $location;

        return $response;
    }

    public static function noContent(int $status = 204): self
    {
        return new self($status);
    }

    public static function download(string $path, ?string $name = null, string $contentType = 'application/octet-stream'): self
    {
        $response = new self(200);
        $response->headers['Content-Type'] = $contentType;
        $response->headers['Content-Disposition'] = 'attachment; filename="' . str_replace('"', '', $name ?? basename($path)) . '"';
        $response->filePath = $path;

        return $response;
    }

    public static function file(string $path, string $contentType): self
    {
        $response = new self(200);
        $response->headers['Content-Type'] = $contentType;
        $response->filePath = $path;

        return $response;
    }

    /* --------------------------------------------------------------------- */
    /* Fluent modifiers                                                       */
    /* --------------------------------------------------------------------- */

    public function withStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    /** @param array<string,string|array<int,string>> $headers */
    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $this->cookies[] = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'http_only' => $httpOnly,
            'same_site' => $sameSite,
            'secure' => (bool) Config::get('security.session.secure', false),
        ];

        return $this;
    }

    public function withDownload(string $path, ?string $name = null, string $contentType = 'application/octet-stream'): self
    {
        $this->filePath = $path;
        $this->headers['Content-Type'] = $contentType;
        $this->headers['Content-Disposition'] = 'attachment; filename="' . str_replace('"', '', $name ?? basename($path)) . '"';

        return $this;
    }

    public function withCache(int $seconds): self
    {
        $this->headers['Cache-Control'] = $seconds > 0 ? 'public, max-age=' . $seconds : 'no-store, no-cache, must-revalidate';

        return $this;
    }

    public function isFile(): bool
    {
        return $this->filePath !== null;
    }

    /* --------------------------------------------------------------------- */
    /* Sending                                                               */
    /* --------------------------------------------------------------------- */

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function isEmpty(): bool
    {
        return $this->content === '' && $this->filePath === null;
    }

    /** Emit to the SAPI (skipped when running under CLI). */
    public function send(): void
    {
        if (PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
            echo $this->content;

            return;
        }

        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                if (is_array($value)) {
                    foreach ($value as $single) {
                        header($name . ': ' . $single, false);
                    }
                    continue;
                }

                header($name . ': ' . $value);
            }

            foreach ($this->cookies as $cookie) {
                $header = sprintf(
                    'Set-Cookie: %s=%s; Path=%s; SameSite=%s%s%s',
                    $cookie['name'],
                    rawurlencode($cookie['value']),
                    $cookie['path'],
                    $cookie['same_site'],
                    $cookie['http_only'] ? '; HttpOnly' : '',
                    $cookie['expires'] > 0 ? '; Expires=' . gmdate('D, d M Y H:i:s', $cookie['expires']) . ' GMT' : ''
                );

                if ($cookie['secure']) {
                    $header .= '; Secure';
                }

                header($header, false);
            }
        }

        if ($this->filePath !== null) {
            if (is_file($this->filePath)) {
                readfile($this->filePath);
            }

            return;
        }

        echo $this->content;
    }
}
