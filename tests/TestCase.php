<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency\Tests;

use LeeOvery\Idempotency\IdempotencyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [IdempotencyServiceProvider::class];
    }

    protected function migratePackage(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
