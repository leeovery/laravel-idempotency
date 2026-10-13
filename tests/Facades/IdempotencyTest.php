<?php

declare(strict_types=1);

use LeeOvery\Idempotency\Facades\Idempotency;
use LeeOvery\Idempotency\IdempotencyFake;
use LeeOvery\Idempotency\IdempotencyManager;
use LeeOvery\Idempotency\Models\IdempotencyKey;

test('will resolve the manager', function (): void {
    expect(Idempotency::getFacadeRoot())->toBe(resolve(IdempotencyManager::class));
});

test('will record keys in the database through the manager', function (): void {
    $this->migratePackage();

    Idempotency::once('order:1:confirmed', fn (): string => 'confirmed');

    expect(IdempotencyKey::query()->sole()->key)->toBe('order:1:confirmed');
});

test('will swap in the fake', function (): void {
    $fake = Idempotency::fake();

    $result = Idempotency::once('order:1:confirmed', fn (): string => 'confirmed');

    expect($fake)->toBeInstanceOf(IdempotencyFake::class)
        ->and(Idempotency::isFake())->toBeTrue()
        ->and($result)->toBe('confirmed');
    $fake->assertCompleted('order:1:confirmed');
});
