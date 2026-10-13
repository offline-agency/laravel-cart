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

        $this->publishes([
            __DIR__.'/../database/migrations/0002_00_00_000002_create_carts_table.php' => database_path('migrations/'.date('Y_m_d_His').'_create_carts_table.php'),
            __DIR__.'/../database/migrations/0003_00_00_000003_create_cart_items_table.php' => database_path('migrations/'.date('Y_m_d_His').'_create_cart_items_table.php'),
        ], 'cart-database-migrations');

        if (! class_exists('CreateCartTable')) {
            $timestamp = date('Y_m_d_His');

            $this->publishes([
                __DIR__.'/../database/migrations/0000_00_00_000000_create_cart_table.php' => database_path("migrations/{$timestamp}_create_cart_table.php"),
            ], 'migrations');
        }
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
