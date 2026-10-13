<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property array<string, mixed>|null $meta
 */
class IdempotencyKey extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
