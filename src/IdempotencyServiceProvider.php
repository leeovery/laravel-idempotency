<?php

declare(strict_types=1);

namespace LeeOvery\Idempotency;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * @codeCoverageIgnore
 */
class IdempotencyServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-idempotency')
            ->hasMigration('create_idempotency_keys_table')
            ->hasInstallCommand(fn (InstallCommand $command): InstallCommand => $command
                ->publishMigrations()
                ->askToRunMigrations());
    }

    public function packageRegistered(): void
    {
        $this->app->scoped(IdempotencyManager::class);
        $this->app->alias(IdempotencyManager::class, 'idempotency-manager');
    }
}
