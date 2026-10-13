<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

afterEach(function (): void {
    Artisan::call('migrate:reset');

    File::delete(File::glob(database_path('migrations/*_create_idempotency_keys_table.php')));
});

test('will publish the migration', function (): void {
    artisan('vendor:publish', ['--tag' => 'idempotency-migrations'])->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_idempotency_keys_table.php')))->toHaveCount(1);
});

test('will install the migration and run it', function (): void {
    artisan('idempotency:install')
        ->expectsConfirmation('Would you like to run the migrations now?', 'yes')
        ->assertSuccessful();

    expect(File::glob(database_path('migrations/*_create_idempotency_keys_table.php')))->toHaveCount(1)
        ->and(Schema::hasColumns('idempotency_keys', ['id', 'key', 'meta', 'created_at', 'updated_at']))->toBeTrue();
});
