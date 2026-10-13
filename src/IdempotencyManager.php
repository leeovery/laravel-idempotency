<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency;

use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use LeeOvery\Idempotency\Contracts\Idempotency;
use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;
use LeeOvery\Idempotency\Models\IdempotencyKey;

class IdempotencyManager implements Idempotency
{
    public function once(string $key, Closure $callback, bool $throw = false): mixed
    {
        return $this->connection()->transaction(function () use ($key, $callback, $throw): mixed {
            // Only this key's claim means "skip": an AlreadyClaimed thrown by the callback rolls back and propagates.
            try {
                $this->claim($key);
            } catch (AlreadyClaimed $exception) {
                if ($throw) {
                    throw $exception;
                }

                return null;
            }

            return $callback();
        });
    }

    public function claim(string $key, array $meta = []): void
    {
        // The savepoint keeps a caller's open transaction usable after a duplicate; Postgres aborts it otherwise.
        try {
            IdempotencyKey::query()->withSavepointIfNeeded(
                fn (): IdempotencyKey => IdempotencyKey::query()->create(['key' => $key, 'meta' => $meta]),
            );
        } catch (UniqueConstraintViolationException $exception) {
            throw AlreadyClaimed::for($key, $exception);
        }
    }

    public function complete(string $key, array $meta = []): void
    {
        IdempotencyKey::query()->createOrFirst(['key' => $key], ['meta' => $meta]);
    }

    public function completed(string $key): bool
    {
        // A lagging read replica would report a done key as not done.
        return IdempotencyKey::query()->useWritePdo()->where('key', $key)->exists();
    }

    private function connection(): ConnectionInterface
    {
        return IdempotencyKey::query()->getConnection();
    }
}
