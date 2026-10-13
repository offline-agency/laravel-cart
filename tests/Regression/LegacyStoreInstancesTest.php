<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    $this->cart = resolveCart();
});

it('stores a second instance under an identifier that already has a stored instance', function (): void {
    $this->cart->instance('default')->add('1', 'Shirt', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-1');

    $this->cart->instance('wishlist')->add('2', 'Shoes', '', 1, 50.0, 61.0, 11.0);
    $this->cart->store('user-1');

    $instances = DB::connection('testing')->table('cart')
        ->where('identifier', 'user-1')->orderBy('instance')->pluck('instance')->all();

    expect($instances)->toBe(['default', 'wishlist']);
});

it('only rejects overwrite of the same identifier and instance', function (): void {
    $this->cart->instance('default')->add('1', 'Shirt', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-1', overwrite: false);

    $this->cart->instance('wishlist')->add('2', 'Shoes', '', 1, 50.0, 61.0, 11.0);
    $this->cart->store('user-1', overwrite: false);

    expect(DB::connection('testing')->table('cart')->where('identifier', 'user-1')->count())->toBe(2);
});

it('restores the current instance and keeps the other stored instances', function (): void {
    $this->cart->instance('default')->add('1', 'Shirt', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-1');
    $this->cart->instance('wishlist')->add('2', 'Shoes', '', 1, 50.0, 61.0, 11.0);
    $this->cart->store('user-1');

    $this->cart->instance('wishlist')->destroy();
    $this->cart->instance('default')->destroy();

    $this->cart->instance('wishlist')->restore('user-1');

    expect($this->cart->instance('wishlist')->content()->first()?->name)->toBe('Shoes')
        ->and($this->cart->instance('default')->count())->toBe(0)
        ->and(DB::connection('testing')->table('cart')->where('identifier', 'user-1')->pluck('instance')->all())
        ->toBe(['default']);
});
