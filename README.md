<div align="center">

# 🔂 Laravel Idempotency

**Idempotency keys for Laravel side effects**

An atomic `once()` for transactional work and the `claim` / `complete` / `completed` primitives for external effects,
<br>backed by one table and one unique index.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/leeovery/laravel-idempotency.svg)](https://packagist.org/packages/leeovery/laravel-idempotency)
[![Tests](https://img.shields.io/github/actions/workflow/status/leeovery/laravel-idempotency/run-tests.yml?branch=main&label=tests)](https://github.com/leeovery/laravel-idempotency/actions/workflows/run-tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4.svg)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20.svg)](https://laravel.com)

[Install](#install) · [Usage](#usage) · [Guarantees](#guarantees) · [Testing](#testing) · [API](#api)

</div>

---

A queued listener gets redelivered, a workflow step retries, a user double-clicks. Each time, the side effect behind it runs again unless something remembers it already ran. This package is that memory: a string key per side effect, `"order:{$order->id}:confirmed"`, recorded in one table whose unique index decides who goes first.

It gives you two shapes, and says plainly which guarantee each one buys.

## Why Laravel Idempotency?

- **The database decides.** `claim()` is one `INSERT` against a unique index. Two workers racing for the same key can't both win, and there's no lock to expire or forget to release.
- **The claim commits with the work.** `once()` claims the key and runs your callback in one transaction, so the claim and the work's own writes land together or not at all. A failure leaves nothing behind, and the retry runs.
- **Honest about external effects.** Nothing inside one app makes a send exactly-once. The package gives you the two shapes that are achievable, at-most-once and at-least-once, and tells you which you're using.
- **Not tied to a model.** A key is a string, so any side effect can have one, not only those a model flag can describe.

## Install

Requires PHP 8.4+ and Laravel 12 or 13.

```bash
composer require leeovery/laravel-idempotency
php artisan idempotency:install
```

`idempotency:install` publishes the migration for the `idempotency_keys` table and offers to run it. To do it by hand:

```bash
php artisan vendor:publish --tag=idempotency-migrations
php artisan migrate
```

## Usage

A key names one side effect of one thing: `"order:{$order->id}:confirmed"`, `"message:{$message->id}:sent"`. Keys are up to 255 characters, and rows are never deleted, because a deleted key would let its side effect run again.

### `once()`: transactional work

`once()` claims the key and runs the callback in one database transaction. If the key is already claimed, it skips the callback and returns `null`. If the callback throws, the claim rolls back with everything else the callback wrote, so a retry runs cleanly.

```php
use LeeOvery\Idempotency\Facades\Idempotency;

Idempotency::once("order:{$order->id}:confirmed", fn () => $order->confirm());
```

Use it for work that lives in the database: recording an event, starting a workflow, writing a ledger row. **Never put an external call inside `once()`.** A transaction can't take back an email or an HTTP request: if the commit fails after the call, the claim rolls back and the retry calls again.

Pass `throw: true` to get `AlreadyClaimed` instead of `null`, when the caller needs to tell "already done" apart from a callback that returned `null`:

```php
use LeeOvery\Idempotency\Exceptions\AlreadyClaimed;

try {
    Idempotency::once($key, fn () => $workflow->start(), throw: true);
} catch (AlreadyClaimed) {
    // Started by an earlier delivery.
}
```

Inside a transaction of your own, `once()` nests: the claim commits or rolls back with your transaction.

### check-do-log: external effects

An irreversible effect, such as sending a message, can't sit in a transaction. Check the key, do the work, then record it:

```php
if (Idempotency::completed($key)) {
    return;
}

$receipt = $sms->send($message);

Idempotency::complete($key, ['receipt' => $receipt->id]);
```

This is at-least-once. If the process dies between the send and `complete()`, the retry sends again. For a customer message, a rare double is the right trade against a message silently lost.

`complete()` is safe to call twice; the first call's `meta` is kept.

### `claim()`: the atomic reserve

`claim()` reserves a key and throws `AlreadyClaimed` if it's taken. It's the step inside `once()`, and you can use it on its own when a double is worse than a loss: claim first, then do the work. Whoever claims first does the work, and a crash after the claim loses it.

```php
try {
    Idempotency::claim($key, ['channel' => 'sms']);
} catch (AlreadyClaimed) {
    return;
}
```

A duplicate `claim()` inside your own transaction leaves that transaction usable, Postgres included: the insert runs in a savepoint.

A claimed key counts as completed: `completed()` returns `true` as soon as the row exists.

## Guarantees

| Shape | Guarantee | A crash part-way through |
|---|---|---|
| `once()` | at most once | Before the commit, the claim and the work roll back together, and the retry runs. |
| check-do-log (`completed`, do, `complete`) | at least once | Between the work and `complete()`, the retry does the work again. |
| `claim()`, then do | at most once | Between the claim and the work, the work is lost and the retry skips it. |

None of these is exactly-once across an external boundary, and nothing inside one app can be. Two upgrades close the gaps, and the package builds neither:

- **Dedup at the provider.** Pass the key to the provider as well, such as Stripe's `Idempotency-Key` header, so the provider drops the repeat. It's the only true guard against a double.
- **A two-phase claim.** Claim as pending, mark completed after the work, and sweep stale pending keys for retry. It closes the loss window of claim-then-do for work where losing it hurts.

## Testing

`Idempotency::fake()` swaps in an in-memory fake, so tests of the code that calls the facade need no migration:

```php
use LeeOvery\Idempotency\Facades\Idempotency;

test('will confirm the order once', function (): void {
    $idempotency = Idempotency::fake();
    $order = Order::factory()->create();

    resolve(ConfirmOrderAction::class)($order);

    $idempotency->assertCompleted("order:{$order->id}:confirmed");
});
```

The fake behaves like the table. A repeated `once()` skips, a duplicate `claim()` throws, and a `once()` callback that throws undoes every key it claimed, as the rollback would. To test the "already done" path, complete the key on the fake before running the code.

| Assertion | Passes when |
|---|---|
| `assertCompleted($key, ?array $meta = null)` | the key was claimed or completed, with exactly this `meta` if given |
| `assertNotCompleted($key)` | the key was never claimed or completed |
| `assertNothingCompleted()` | no key was claimed or completed |

The fake replaces what the facade resolves. Code that injects `IdempotencyManager` still gets the real class.

## API

All through `LeeOvery\Idempotency\Facades\Idempotency`:

| Method | |
|---|---|
| `once(string $key, Closure $callback, bool $throw = false): mixed` | Claim and run in one transaction. Returns the callback's result, or `null` when already claimed (`AlreadyClaimed` with `throw: true`). |
| `claim(string $key, array $meta = []): void` | Reserve the key. Throws `AlreadyClaimed` if it exists. |
| `complete(string $key, array $meta = []): void` | Record the key as done. Idempotent; the first `meta` is kept. |
| `completed(string $key): bool` | Whether the key exists. Reads from the write connection, so a lagging replica can't report a done key as not done. |

## License

MIT. See [LICENSE.md](LICENSE.md).
