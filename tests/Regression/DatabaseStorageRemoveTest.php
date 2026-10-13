<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

/**
 * Defect 2: DatabaseStorage::remove() deleted the cart items before looking
 * at the key suffix, so removing coupons/meta/options wiped the cart.
 */
function regRemoveStorage(): DatabaseStorage
{
    return new DatabaseStorage(app(DatabaseManager::class));
}

function regRemoveSeed(DatabaseStorage $storage): CartItem
{
    $item = new CartItem(id: '1', name: 'Kept', subtitle: '', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);

    $storage->put('cart.default', new Collection([$item->rowId => $item]));
    $storage->put('cart.default_global_coupons', collect([
        'h' => new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 1.0),
    ]));
    $storage->put('cart.default_meta', ['source' => 'web']);
    $storage->put('cart.default_cart_info', ['options' => ['gift' => true]]);

    return $item;
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('keeps items, meta and options when only the coupons are removed', function (): void {
    $storage = regRemoveStorage();
    $item = regRemoveSeed($storage);

    $storage->remove('cart.default_global_coupons');

    $fresh = regRemoveStorage();

    expect($fresh->get('cart.default'))->toHaveCount(1)
        ->and($fresh->get('cart.default')->get($item->rowId)->name)->toBe('Kept')
        ->and($fresh->get('cart.default_meta'))->toBe(['source' => 'web'])
        ->and($fresh->get('cart.default_cart_info'))->toBe(['options' => ['gift' => true]])
        ->and($fresh->get('cart.default_global_coupons'))->toBeEmpty()
        ->and($storage->get('cart.default'))->toHaveCount(1)
        ->and($storage->get('cart.default_global_coupons', 'missing'))->toBeEmpty();
});

it('keeps items, coupons and options when only the meta is removed', function (): void {
    $storage = regRemoveStorage();
    regRemoveSeed($storage);

    $storage->remove('cart.default_meta');

    $fresh = regRemoveStorage();

    expect($fresh->get('cart.default'))->toHaveCount(1)
        ->and($fresh->get('cart.default_global_coupons'))->toHaveCount(1)
        ->and($fresh->get('cart.default_cart_info'))->toBe(['options' => ['gift' => true]])
        ->and($fresh->get('cart.default_meta'))->toBe([])
        ->and($storage->get('cart.default_meta'))->toBe([]);
});

it('keeps items, coupons and meta when only the cart info is removed', function (): void {
    $storage = regRemoveStorage();
    regRemoveSeed($storage);

    $storage->remove('cart.default_cart_info');

    $fresh = regRemoveStorage();

    expect($fresh->get('cart.default'))->toHaveCount(1)
        ->and($fresh->get('cart.default_global_coupons'))->toHaveCount(1)
        ->and($fresh->get('cart.default_meta'))->toBe(['source' => 'web'])
        ->and($fresh->get('cart.default_cart_info'))->toBe([])
        ->and($storage->get('cart.default_cart_info'))->toBe([]);
});

it('invalidates every cached entry of the cart when the whole cart is removed', function (): void {
    $storage = regRemoveStorage();
    regRemoveSeed($storage);

    // Warm the cache for every key.
    $storage->get('cart.default');
    $storage->get('cart.default_meta');
    $storage->get('cart.default_cart_info');
    $storage->get('cart.default_global_coupons');

    $storage->remove('cart.default');

    expect(DB::table('carts')->count())->toBe(0)
        ->and(DB::table('cart_items')->count())->toBe(0)
        ->and($storage->has('cart.default'))->toBeFalse()
        ->and($storage->has('cart.default_meta'))->toBeFalse()
        ->and($storage->get('cart.default_meta', 'gone'))->toBe('gone')
        ->and($storage->get('cart.default_cart_info', 'gone'))->toBe('gone')
        ->and($storage->get('cart.default_global_coupons', 'gone'))->toBe('gone');
});

it('does not touch other instances when removing a cart', function (): void {
    $storage = regRemoveStorage();
    regRemoveSeed($storage);
    $other = new CartItem(id: '2', name: 'Wish', subtitle: '', qty: 1, price: 1.0, totalPrice: 1.0, vat: 0.0);
    $storage->put('cart.wishlist', new Collection([$other->rowId => $other]));

    $storage->remove('cart.default');

    expect(regRemoveStorage()->get('cart.wishlist'))->toHaveCount(1);
});
