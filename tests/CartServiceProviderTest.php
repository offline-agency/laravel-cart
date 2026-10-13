<?php

declare(strict_types=1);

use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\GenericUser;
use Illuminate\Session\SessionManager;
use Mockery\MockInterface;
use OfflineAgency\LaravelCart\Cart;

it('binds the cart service in the container', function (): void {
    expect(resolveCart())->toBeInstanceOf(Cart::class);
});

it('merges the default configuration', function (): void {
    expect(config('cart.destroy_on_logout'))->toBeFalse();
    expect(config('cart.database.table'))->toBe('cart');
});

it('forgets cart on logout when configured', function (): void {
    config()->set('cart.destroy_on_logout', true);

    app()->instance(SessionManager::class, Mockery::mock(SessionManager::class, function (MockInterface $mock): void {
        $mock->shouldReceive('forget')->once()->with('cart');
    }));

    event(new Logout('', new GenericUser(['id' => 1])));
});
