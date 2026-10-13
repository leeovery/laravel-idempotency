<?php

declare(strict_types=1);

use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;
use LeeOvery\Idempotency\IdempotencyFake;
use PHPUnit\Framework\ExpectationFailedException;

test('will run the callback once and skip a repeat', function (): void {
    $fake = new IdempotencyFake;

    $first = $fake->once('order:1:confirmed', fn (): string => 'confirmed');
    $second = $fake->once('order:1:confirmed', fn (): string => 'confirmed');

    expect($first)->toBe('confirmed')
        ->and($second)->toBeNull();
});

test('will throw when the key is already claimed and asked to throw', function (): void {
    $fake = new IdempotencyFake;
    $fake->once('order:1:confirmed', fn (): string => 'confirmed');

    $fake->once('order:1:confirmed', fn (): string => 'confirmed', throw: true);
})->throws(AlreadyClaimed::class, AlreadyClaimed::for('order:1:confirmed')->getMessage());

test('will undo the claim and the keys the callback claimed when the callback throws', function (): void {
    $fake = new IdempotencyFake;
    $fake->complete('order:1:created');

    expect(fn (): mixed => $fake->once('order:1:confirmed', function () use ($fake): never {
        $fake->complete('order:1:receipt');

        throw new RuntimeException('Confirmation failed.');
    }))->toThrow(RuntimeException::class);

    expect($fake->completed('order:1:created'))->toBeTrue()
        ->and($fake->completed('order:1:confirmed'))->toBeFalse()
        ->and($fake->completed('order:1:receipt'))->toBeFalse();
});

test('will not claim a key twice', function (): void {
    $fake = new IdempotencyFake;
    $fake->claim('message:1:sent');

    $fake->claim('message:1:sent');
})->throws(AlreadyClaimed::class, AlreadyClaimed::for('message:1:sent')->getMessage());

test('will complete a key twice, keeping the first meta', function (): void {
    $fake = new IdempotencyFake;
    $fake->complete('message:1:sent', ['receipt' => 'msg_123']);

    $fake->complete('message:1:sent', ['receipt' => 'msg_456']);

    $fake->assertCompleted('message:1:sent', ['receipt' => 'msg_123']);
});

test('will pass assertCompleted for a completed key', function (): void {
    $fake = new IdempotencyFake;

    $fake->claim('message:1:sent', ['channel' => 'sms']);

    $fake->assertCompleted('message:1:sent');
    $fake->assertCompleted('message:1:sent', ['channel' => 'sms']);
});

test('will fail assertCompleted for a key that was not completed', function (): void {
    (new IdempotencyFake)->assertCompleted('message:1:sent');
})->throws(ExpectationFailedException::class, 'The idempotency key [message:1:sent] was not completed.');

test('will fail assertCompleted when the meta differs', function (): void {
    $fake = new IdempotencyFake;
    $fake->complete('message:1:sent', ['receipt' => 'msg_123']);

    $fake->assertCompleted('message:1:sent', ['receipt' => 'msg_456']);
})->throws(ExpectationFailedException::class, 'The idempotency key [message:1:sent] was completed with different meta.');

test('will fail assertNotCompleted for a completed key', function (): void {
    $fake = new IdempotencyFake;
    $fake->complete('message:1:sent');

    $fake->assertNotCompleted('message:1:sent');
})->throws(ExpectationFailedException::class, 'The idempotency key [message:1:sent] was completed.');

test('will fail assertNothingCompleted once a key is completed', function (): void {
    $fake = new IdempotencyFake;
    $fake->assertNothingCompleted();

    $fake->complete('message:1:sent');

    $fake->assertNothingCompleted();
})->throws(ExpectationFailedException::class, 'Idempotency keys were completed.');
