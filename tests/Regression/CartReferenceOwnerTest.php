<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

it('separates guest and user reference contexts even with identical string identities', function (): void {
    config(['cart.users.foreign_key_type' => 'string']);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = new Cart(new DatabaseStorage(app(DatabaseManager::class)), app('events'));
    $guest = $cart->add(id: 1, name: 'Guest', qty: 1, price: 10.0);
    $this->actingAs(new GenericUser(['id' => session()->getId()]));
    $cart->add(id: 1, name: 'User', qty: 1, price: 20.0);
    expect($cart->total())->toBe(20.0)->and($guest->price)->toBe(10.0)->and($guest->name)->toBe('Guest');
});
