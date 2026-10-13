<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency\Exceptions;

use DomainException;
use Throwable;

final class AlreadyClaimed extends DomainException
{
    public static function for(string $key, ?Throwable $previous = null): self
    {
        return new self(sprintf('Idempotency key already claimed: [%s].', $key), previous: $previous);
    }
}
