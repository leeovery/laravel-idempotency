<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency\Facades;

use Illuminate\Support\Facades\Facade;
use LeeOvery\Idempotency\Contracts\Idempotency as IdempotencyContract;
use LeeOvery\Idempotency\IdempotencyFake;

/**
 * @mixin IdempotencyContract
 * @mixin IdempotencyFake
 */
class Idempotency extends Facade
{
    public static function fake(): IdempotencyFake
    {
        static::swap($fake = new IdempotencyFake);

        return $fake;
    }

    public static function getFacadeAccessor(): string
    {
        return 'idempotency-manager';
    }
}
