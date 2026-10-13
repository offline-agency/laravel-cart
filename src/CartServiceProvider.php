<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;
use OfflineAgency\LaravelCart\Console\CartClearCommand;
use OfflineAgency\LaravelCart\Console\CartExportCommand;
use OfflineAgency\LaravelCart\Console\CartInspectCommand;
use OfflineAgency\LaravelCart\Console\CartListCommand;
use OfflineAgency\LaravelCart\Console\CartMigrateDriverCommand;
use OfflineAgency\LaravelCart\Console\CartPruneCommand;
use OfflineAgency\LaravelCart\Console\CartStatsCommand;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Exceptions\InvalidDriverException;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use OfflineAgency\LaravelCart\Support\CartConfig;

class CartServiceProvider extends ServiceProvider
{
    private ?int $migrationTimestamp = null;

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cart.php', 'cart');

        // Scoped: the cart and its storage carry per-user state (current
        // instance, cart-level coupons, read cache), so they are rebuilt for
        // every request / queued job instead of living for the whole worker.
        $this->app->scoped(CartStorage::class, function (Application $app): CartStorage {
            $driver = CartConfig::string('cart.storage.driver', 'session');

            return match ($driver) {
                'database' => new DatabaseStorage($app->make(DatabaseManager::class)),
                'session' => new SessionStorage($app->make(SessionManager::class)),
                default => throw new InvalidDriverException($driver),
            };
        });

        $this->app->scoped('cart', function (Application $app): Cart {
            return new Cart(
                $app->make(CartStorage::class),
                $app->make(Dispatcher::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravel-cart');

        $this->registerPublishables();
        $this->registerLogoutListener();
        $this->commands([
            CartClearCommand::class,
            CartStatsCommand::class,
            CartListCommand::class,
            CartInspectCommand::class,
            CartPruneCommand::class,
            CartExportCommand::class,
            CartMigrateDriverCommand::class,
        ]);
    }

    private function registerPublishables(): void
    {
        $this->publishes([__DIR__.'/../config/cart.php' => config_path('cart.php')], 'config');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/laravel-cart'),
        ], 'laravel-cart-lang');

        $this->publishes($this->migrationsInRunOrder([
            '0002_00_00_000002_create_carts_table.php' => 'create_carts_table.php',
            '0003_00_00_000003_create_cart_items_table.php' => 'create_cart_items_table.php',
        ]), 'cart-database-migrations');

        // Apps upgrading from 3.x already published the create migration.
        $this->publishes($this->migrationsInRunOrder([
            '0001_00_00_000001_add_coupons_column_to_cart_table.php' => 'add_coupons_column_to_cart_table.php',
            '0004_00_00_000004_add_meta_column_to_cart_table.php' => 'add_meta_column_to_cart_table.php',
        ]), 'cart-legacy-column-migrations');

        // Legacy store()/restore() table: a fresh install needs the table and both columns.
        $this->publishes($this->migrationsInRunOrder([
            '0000_00_00_000000_create_cart_table.php' => 'create_cart_table.php',
            '0001_00_00_000001_add_coupons_column_to_cart_table.php' => 'add_coupons_column_to_cart_table.php',
            '0004_00_00_000004_add_meta_column_to_cart_table.php' => 'add_meta_column_to_cart_table.php',
        ]), 'migrations');
    }

    /**
     * Map package migrations to published paths whose timestamps increase by
     * one second each, so the host app runs them in the listed order (Laravel
     * orders migrations by file name).
     *
     * The counter is shared by every call: the framework keeps a single
     * destination per source file and provider, so a file registered under
     * several tags takes the destination of its last registration.
     *
     * @param  array<string, string>  $migrations  Package file => published name suffix.
     * @return array<string, string>
     */
    private function migrationsInRunOrder(array $migrations): array
    {
        $this->migrationTimestamp ??= time();

        $published = [];

        foreach ($migrations as $source => $name) {
            $published[__DIR__.'/../database/migrations/'.$source] = database_path('migrations/'.date('Y_m_d_His', $this->migrationTimestamp++).'_'.$name);
        }

        return $published;
    }

    private function registerLogoutListener(): void
    {
        $this->app->make(Dispatcher::class)->listen(Logout::class, function (): void {
            if (config('cart.destroy_on_logout')) {
                $this->app->make(SessionManager::class)->forget('cart');
            }
        });
    }
}
