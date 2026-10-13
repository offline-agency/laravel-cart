<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use OfflineAgency\LaravelCart\CartServiceProvider;
use OfflineAgency\LaravelCart\Support\Coerce;

/**
 * Copy a tag's published migrations into a scratch directory under their
 * published file names, so they run in the order the host app would run them.
 */
function publishedMigrationsDirectory(string $tag): string
{
    $directory = sys_get_temp_dir().'/laravel-cart-published-'.$tag.'-'.uniqid();
    File::ensureDirectoryExists($directory);

    foreach (ServiceProvider::pathsToPublish(CartServiceProvider::class, $tag) as $source => $destination) {
        File::copy((string) $source, $directory.'/'.basename(Coerce::string($destination)));
    }

    return $directory;
}

it('publishes the legacy table with its coupons and meta columns, in run order', function (): void {
    $directory = publishedMigrationsDirectory('migrations');

    $names = collect(File::files($directory))->map(fn (SplFileInfo $file): string => $file->getFilename())->sort()->values();
    expect($names->map(fn (string $name): string => substr($name, 18))->all())
        ->toBe(['create_cart_table.php', 'add_coupons_column_to_cart_table.php', 'add_meta_column_to_cart_table.php']);

    // CreateCartTable is a named class (as published by 3.x) that other tests already
    // loaded from the package path, so run that file from there and the rest as published.
    $create = (string) $names->first();
    $this->artisan('migrate', ['--database' => 'testing', '--path' => realpath(__DIR__.'/../../database/migrations/0000_00_00_000000_create_cart_table.php'), '--realpath' => true])
        ->assertExitCode(0);
    File::delete($directory.'/'.$create);
    $this->artisan('migrate', ['--database' => 'testing', '--path' => $directory, '--realpath' => true])
        ->assertExitCode(0);

    expect(Schema::connection('testing')->hasColumns('cart', ['identifier', 'instance', 'content', 'coupons', 'meta']))->toBeTrue();

    File::deleteDirectory($directory);
});

it('publishes the normalized tables so carts is created before cart_items', function (): void {
    $directory = publishedMigrationsDirectory('cart-database-migrations');

    $this->artisan('migrate', ['--database' => 'testing', '--path' => $directory, '--realpath' => true])
        ->assertExitCode(0);

    expect(Schema::connection('testing')->hasTable('carts'))->toBeTrue()
        ->and(Schema::connection('testing')->hasTable('cart_items'))->toBeTrue();

    File::deleteDirectory($directory);
});

it('upgrades an existing v3 legacy table with only the column migrations', function (): void {
    // A v3 app already has the legacy table from the create migration (identical schema).
    Schema::connection('testing')->create('cart', function (Blueprint $table): void {
        $table->string('identifier');
        $table->string('instance');
        $table->longText('content');
        $table->nullableTimestamps();
        $table->primary(['identifier', 'instance']);
    });

    $columns = publishedMigrationsDirectory('cart-legacy-column-migrations');
    expect(File::files($columns))->toHaveCount(2);

    $this->artisan('migrate', ['--database' => 'testing', '--path' => $columns, '--realpath' => true])
        ->assertExitCode(0);

    expect(Schema::connection('testing')->hasColumns('cart', ['coupons', 'meta']))->toBeTrue();

    File::deleteDirectory($columns);
});
