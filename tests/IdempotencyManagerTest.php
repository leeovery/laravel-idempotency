<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;
use LeeOvery\Idempotency\IdempotencyManager;
use LeeOvery\Idempotency\Models\IdempotencyKey;

beforeEach(function (): void {
    $this->migratePackage();
});

test('will run the callback once and return its result', function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    $result = $idempotency->once('order:1:confirmed', fn (): string => 'confirmed');

    expect($result)->toBe('confirmed')
        ->and($idempotency->completed('order:1:confirmed'))->toBeTrue();
});

test('will skip the callback and return null when the key is already claimed', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $runs = 0;
    $idempotency->once('order:1:confirmed', function () use (&$runs): string {
        $runs++;

        return 'confirmed';
    });

    $result = $idempotency->once('order:1:confirmed', function () use (&$runs): string {
        $runs++;

        return 'confirmed';
    });

    expect($result)->toBeNull()
        ->and($runs)->toBe(1);
});

test('will throw when the key is already claimed and asked to throw', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->once('order:1:confirmed', fn (): string => 'confirmed');

    $idempotency->once('order:1:confirmed', fn (): string => 'confirmed', throw: true);
})->throws(AlreadyClaimed::class, AlreadyClaimed::for('order:1:confirmed')->getMessage());

test('will run the callback inside a transaction', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $level = DB::transactionLevel();

    $result = $idempotency->once('order:1:confirmed', fn (): int => DB::transactionLevel());

    expect($result)->toBe($level + 1);
});

test('will roll back the claim when the callback throws, so a retry runs', function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    expect(fn (): mixed => $idempotency->once('order:1:confirmed', fn (): never => throw new RuntimeException('Confirmation failed.')))
        ->toThrow(RuntimeException::class, 'Confirmation failed.');

    expect($idempotency->completed('order:1:confirmed'))->toBeFalse()
        ->and($idempotency->once('order:1:confirmed', fn (): string => 'confirmed'))->toBe('confirmed');
});

test("will roll back the callback's own writes with the claim", function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    expect(fn (): mixed => $idempotency->once('order:1:confirmed', function () use ($idempotency): never {
        $idempotency->complete('order:1:receipt');

        throw new RuntimeException('Confirmation failed.');
    }))->toThrow(RuntimeException::class);

    expect($idempotency->completed('order:1:receipt'))->toBeFalse();
});

test('will roll back and rethrow when the callback hits an already claimed key', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->claim('order:1:receipt');

    expect(fn (): mixed => $idempotency->once('order:1:confirmed', fn () => $idempotency->claim('order:1:receipt')))
        ->toThrow(AlreadyClaimed::class, AlreadyClaimed::for('order:1:receipt')->getMessage());

    expect($idempotency->completed('order:1:confirmed'))->toBeFalse();
});

test("will roll back the claim with the caller's transaction", function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    expect(fn (): mixed => DB::transaction(function () use ($idempotency): never {
        $idempotency->once('order:1:confirmed', fn (): string => 'confirmed');

        throw new RuntimeException('Checkout failed.');
    }))->toThrow(RuntimeException::class);

    expect($idempotency->completed('order:1:confirmed'))->toBeFalse();
});

test('will claim a key', function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    $idempotency->claim('message:1:sent', ['channel' => 'sms']);

    expect($idempotency->completed('message:1:sent'))->toBeTrue()
        ->and(IdempotencyKey::query()->sole())
        ->key->toBe('message:1:sent')
        ->meta->toBe(['channel' => 'sms']);
});

test('will not claim a key twice', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->claim('message:1:sent');

    $idempotency->claim('message:1:sent');
})->throws(AlreadyClaimed::class, AlreadyClaimed::for('message:1:sent')->getMessage());

test("will keep the caller's transaction usable after a duplicate claim", function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->claim('message:1:sent');

    DB::transaction(function () use ($idempotency): void {
        rescue(fn () => $idempotency->claim('message:1:sent'), report: false);

        $idempotency->claim('message:2:sent');
    });

    expect($idempotency->completed('message:2:sent'))->toBeTrue();
});

test('will complete a key', function (): void {
    $idempotency = resolve(IdempotencyManager::class);

    $idempotency->complete('message:1:sent', ['receipt' => 'msg_123']);

    expect($idempotency->completed('message:1:sent'))->toBeTrue()
        ->and(IdempotencyKey::query()->sole())
        ->key->toBe('message:1:sent')
        ->meta->toBe(['receipt' => 'msg_123']);
});

test('will complete a key twice, keeping the first meta', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->complete('message:1:sent', ['receipt' => 'msg_123']);

    $idempotency->complete('message:1:sent', ['receipt' => 'msg_456']);

    expect(IdempotencyKey::query()->sole()->meta)->toBe(['receipt' => 'msg_123']);
});

test('will complete a claimed key, keeping the meta of the claim', function (): void {
    $idempotency = resolve(IdempotencyManager::class);
    $idempotency->claim('message:1:sent', ['channel' => 'sms']);

    $idempotency->complete('message:1:sent', ['receipt' => 'msg_123']);

    expect(IdempotencyKey::query()->sole()->meta)->toBe(['channel' => 'sms']);
});

test('will not report an unknown key as completed', function (): void {
    expect(resolve(IdempotencyManager::class)->completed('message:1:sent'))->toBeFalse();
});
