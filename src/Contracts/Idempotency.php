<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency\Contracts;

use Closure;
use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;

interface Idempotency
{
    /**
     * Claim the key and run the callback in one transaction. Returns the callback's result, or null when
     * the key is already claimed, unless $throw is true, in which case AlreadyClaimed is rethrown.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn|null
     *
     * @throws AlreadyClaimed
     */
    public function once(string $key, Closure $callback, bool $throw = false): mixed;

    /**
     * Atomically reserve the key; throws AlreadyClaimed when it's already claimed.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws AlreadyClaimed
     */
    public function claim(string $key, array $meta = []): void;

    /**
     * Record the key as done. Safe to call twice; the first call's meta is kept.
     *
     * @param  array<string, mixed>  $meta
     */
    public function complete(string $key, array $meta = []): void;

    public function completed(string $key): bool;
}
