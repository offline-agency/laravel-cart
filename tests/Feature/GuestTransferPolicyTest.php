<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

it('merges trusted guest state with authenticated price and coupon precedence on custom schemas', function (string $type, int|string $owner): void {
    config([
        'cart.storage.driver' => 'database',
        'cart.users.foreign_key' => 'customer_id',
        'cart.users.foreign_key_type' => $type,
        'cart.database.carts_table' => 'custom_carts',
        'cart.database.cart_items_table' => 'custom_lines',
        'cart.database.connection' => 'cart_test_custom',
        'database.connections.cart_test_custom' => config('database.connections.testing'),
    ]);
    $this->artisan('migrate', ['--database' => 'cart_test_custom']);
    $database = app(DatabaseManager::class);
    $storage = new DatabaseStorage($database);
    $cart = new Cart($storage, app('events'));
    $guest = session()->getId();
    $cart->add(id: 1, name: 'Stale guest price', qty: 2, price: 1.0);
    $cart->add(id: 1, name: 'Guest variant', qty: 1, price: 5.0, options: ['size' => 'L']);
    $cart->addCoupon(new CartCoupon(hash: 'guest-conflict', code: 'SHARED', type: 'fixed', value: 5.0));
    $cart->addCoupon(new CartCoupon(hash: 'guest-only', code: 'ONLY', type: 'fixed', value: 1.0));
    $cart->addCoupon(new CartCoupon(hash: 'expiring', code: 'EXPIRE', type: 'fixed', value: 1.0, expiresAt: now()->addMinute()));
    $cart->setOptions(['shared' => 'guest', 'guest' => true]);
    $cart->setMeta('shared', 'guest')->setMeta('guest', true);
    $cart->instance('wishlist')->add(id: 3, name: 'Wish', qty: 1, price: 3.0);
    $cart->instance('excluded')->add(id: 4, name: 'Excluded', qty: 1, price: 4.0);

    auth()->guard('web')->login(new GenericUser(['id' => $owner, 'password' => 'test-hash']));
    session()->regenerate();
    $cart->instance('default');
    $item = $cart->add(id: 1, name: 'Trusted user price', qty: 1, price: 10.0);
    $cart->addItemCoupon($item->rowId, 'USER', 'fixed', 1.0);
    $cart->addCoupon(new CartCoupon(hash: 'user-conflict', code: 'SHARED', type: 'fixed', value: 2.0));
    $cart->addCoupon(new CartCoupon(hash: 'later-user-conflict', code: 'SHARED', type: 'fixed', value: 3.0));
    $cart->setOptions(['shared' => 'user']);
    $cart->setMeta('shared', 'user');
    $this->travel(2)->minutes();

    expect($storage->transferGuest($guest, ['wishlist', 'default']))->toBeTrue();
    expect($cart->count())->toBe(4)->and($cart->uniqueCount())->toBe(2)
        ->and($cart->total())->toBe(31.0)
        ->and($cart->get($item->rowId)->qty)->toBe(3)
        ->and($cart->get($item->rowId)->originalPrice)->toBe(10.0)
        ->and($cart->get($item->rowId)->hasCoupons())->toBeTrue()
        ->and($cart->listCoupons()->pluck('code')->all())->toBe(['SHARED', 'ONLY'])
        ->and($cart->getOptions())->toEqual(['shared' => 'user', 'guest' => true])
        ->and($cart->meta())->toEqual(['shared' => 'user', 'guest' => true]);
    expect($cart->instance('wishlist')->total())->toBe(3.0)
        ->and($cart->instance('excluded')->isEmpty())->toBeTrue()
        ->and($storage->transferGuest($guest, ['default', 'wishlist']))->toBeFalse();
    expect($database->connection('cart_test_custom')->table('custom_carts')->where('session_id', $guest)->count())->toBe(1);
})->with([
    ['unsignedBigInteger', 42],
    ['uuid', (string) Str::uuid()],
    ['ulid', (string) Str::ulid()],
]);
