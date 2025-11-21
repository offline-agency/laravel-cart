<?php

namespace OfflineAgency\LaravelCart;

use Illuminate\Auth\Events\Logout;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ServiceProvider;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton('cart', Cart::class);

        $this->mergeConfigFrom(__DIR__.'/../config/cart.php', 'cart');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerLogoutListener();
    }

    private function registerPublishables(): void
    {
        $this->publishes([__DIR__.'/../config/cart.php' => config_path('cart.php')], 'config');

        if (! class_exists('CreateCartTable')) {
            $timestamp = date('Y_m_d_His');

            $this->publishes([
                __DIR__.'/../database/migrations/0000_00_00_000000_create_cart_table.php' => database_path("migrations/{$timestamp}_create_cart_table.php"),
            ], 'migrations');
        }
    }

    private function registerLogoutListener(): void
    {
        $this->app['events']->listen(Logout::class, function (): void {
            if ($this->app['config']->get('cart.destroy_on_logout')) {
                $this->app->make(SessionManager::class)->forget('cart');
            }
        });
    }
}
