<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Session\SessionManager;
use OfflineAgency\LaravelCart\Cart;

it('binds the cart service in the container', function (): void {
    expect($this->app->make('cart'))->toBeInstanceOf(Cart::class);
});

it('merges the default configuration', function (): void {
    expect(config('cart.destroy_on_logout'))->toBeFalse();
    expect(config('cart.database.table'))->toBe('cart');
});

it('forgets cart on logout when configured', function (): void {
    $this->app['config']->set('cart.destroy_on_logout', true);

    $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock): void {
        $mock->shouldReceive('forget')->once()->with('cart');
    }));

    event(new Logout('', Mockery::mock(Authenticatable::class)));
});
