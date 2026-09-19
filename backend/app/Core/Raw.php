<?php

declare(strict_types=1);

namespace App\Core;

/** Marker for a raw SQL fragment that must not be quoted by the query builder. */
final class Raw
{
    public function __construct(public readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
