<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    config()->set('cart.storage.driver', 'database');
    config()->set('cart.ttl', 60);
    app()->forgetScopedInstances();
    $this->cart = resolveCart();
});

it('cart:prune --expired-only --instance matches carts written by the database driver', function (): void {
    expect(app(CartStorage::class))->toBeInstanceOf(DatabaseStorage::class);

    $this->cart->instance('default')->add('1', 'Shirt', '', 1, 10.0, 12.2, 2.2);
    $this->cart->instance('wishlist')->add('2', 'Shoes', '', 1, 50.0, 61.0, 11.0);

    DB::connection('testing')->table('carts')->update(['expires_at' => now()->subMinute()]);

    $this->artisan('cart:prune', ['--expired-only' => true, '--instance' => 'default', '--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->count())->toBe(1);
});
