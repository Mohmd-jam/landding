<?php

declare(strict_types=1);

namespace App\Core;

/** 422 with per-field messages, rendered as { error: { fields: {...} } } by the API. */
final class ValidationException extends HttpException
{
    public function __construct(string $message, private array $errors = [])
    {
        parent::__construct(422, $message);
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if (is_array($messages) && isset($messages[0])) {
                return (string) $messages[0];
            }
        }

        return null;
    }

    /** @param array<string,string|array<int,string>> $errors */
    public static function withMessages(array $errors, string $message = 'The submitted data is invalid.'): self
    {
        $normalised = [];

        foreach ($errors as $field => $messages) {
            $normalised[$field] = is_array($messages) ? array_values($messages) : [$messages];
        }

        return new self($message, $normalised);
    }
}
