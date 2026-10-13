<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency;

use Closure;
use Illuminate\Support\Testing\Fakes\Fake;
use LeeOvery\Idempotency\Contracts\Idempotency;
use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;
use PHPUnit\Framework\Assert;
use Throwable;

class IdempotencyFake implements Fake, Idempotency
{
    /** @var array<string, array<string, mixed>> */
    private array $keys = [];

    public function once(string $key, Closure $callback, bool $throw = false): mixed
    {
        $keys = $this->keys;

        try {
            $this->claim($key);
        } catch (AlreadyClaimed $exception) {
            if ($throw) {
                throw $exception;
            }

            return null;
        }

        // Stands in for the rollback: a failed callback undoes its claim and every key it claimed itself.
        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->keys = $keys;

            throw $exception;
        }
    }

    public function claim(string $key, array $meta = []): void
    {
        if ($this->completed($key)) {
            throw AlreadyClaimed::for($key);
        }

        $this->keys[$key] = $meta;
    }

    public function complete(string $key, array $meta = []): void
    {
        $this->keys[$key] ??= $meta;
    }

    public function completed(string $key): bool
    {
        return array_key_exists($key, $this->keys);
    }

    /** @param  array<string, mixed>|null  $meta */
    public function assertCompleted(string $key, ?array $meta = null): void
    {
        Assert::assertTrue($this->completed($key), "The idempotency key [{$key}] was not completed.");

        if ($meta !== null) {
            Assert::assertSame($meta, $this->keys[$key], "The idempotency key [{$key}] was completed with different meta.");
        }
    }

    public function assertNotCompleted(string $key): void
    {
        Assert::assertFalse($this->completed($key), "The idempotency key [{$key}] was completed.");
    }

    public function assertNothingCompleted(): void
    {
        Assert::assertSame([], array_keys($this->keys), 'Idempotency keys were completed.');
    }
}
