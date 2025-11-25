<?php

namespace OfflineAgency\LaravelCart\Tests;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Mockery;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartServiceProvider;
use Orchestra\Testbench\TestCase;

class CartServiceProviderTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string<CartServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }

    /** @test */
    public function it_binds_the_cart_service_in_the_container(): void
    {
        $this->assertInstanceOf(Cart::class, $this->app->make('cart'));
    }

    /** @test */
    public function it_merges_the_default_configuration(): void
    {
        $this->assertFalse(config('cart.destroy_on_logout'));
        $this->assertEquals('cart', config('cart.database.table'));
    }

    /** @test */
    public function it_forgets_cart_on_logout_when_configured(): void
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock): void {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        event(new Logout('', Mockery::mock(Authenticatable::class)));
    }
}