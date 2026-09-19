<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** HTTP-aware exception carrying a status code and optional response headers. */
class HttpException extends RuntimeException
{
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $headers = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode), $statusCode, $previous);
    }

    public static function notFound(string $message = 'Resource not found'): self
    {
        return new self(404, $message);
    }

    public static function unauthorized(string $message = 'Authentication required'): self
    {
        return new self(401, $message);
    }

    public static function badRequest(string $message = 'The request could not be understood'): self
    {
        return new self(400, $message);
    }

    public static function forbidden(string $message = 'You are not allowed to perform this action'): self
    {
        return new self(403, $message);
    }

    public static function validation(string $message = 'The given data was invalid', array $errors = []): ValidationException
    {
        return new ValidationException($message, $errors);
    }

    public static function csrf(string $message = 'Security token expired. Please refresh the page.'): self
    {
        return new self(419, $message);
    }

    public static function tooManyRequests(string $message = 'Too many requests. Please slow down.'): self
    {
        return new self(429, $message);
    }

    public static function server(string $message = 'Internal server error'): self
    {
        return new self(500, $message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Access denied',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            419 => 'Session expired',
            429 => 'Too many requests',
            default => 'Request failed',
        };
    }
}
