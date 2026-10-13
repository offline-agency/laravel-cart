<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

/**
 * Defect 5: the DatabaseStorage cache was keyed only by storage key, so a
 * long-lived instance served one owner's cart to another and ignored TTL.
 */
function regCacheStorage(): DatabaseStorage
{
    return new DatabaseStorage(app(DatabaseManager::class));
}

/**
 * @return Collection<string, CartItem>
 */
function regCacheContent(string $id): Collection
{
    $item = new CartItem(id: $id, name: 'Item '.$id, subtitle: '', qty: 1, price: 3.0, totalPrice: 3.0, vat: 0.0);

    return new Collection([$item->rowId => $item]);
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('does not serve user A cart to user B from the same storage instance', function (): void {
    $storage = regCacheStorage();

    $this->actingAs(new GenericUser(['id' => 1]));
    $storage->put('cart.default', regCacheContent('a'));
    $storage->put('cart.default_meta', ['owner' => 'a']);
    expect($storage->get('cart.default'))->toHaveCount(1);

    $this->actingAs(new GenericUser(['id' => 2]));

    expect($storage->get('cart.default'))->toBeNull()
        ->and($storage->has('cart.default'))->toBeFalse()
        ->and($storage->get('cart.default_meta', []))->toBe([]);

    $this->actingAs(new GenericUser(['id' => 1]));

    expect(cartItemsOf($storage->get('cart.default'))->first()?->id)->toBe('a');
});

it('does not serve the guest cart after the user authenticates', function (): void {
    $storage = regCacheStorage();

    $storage->put('cart.default', regCacheContent('guest'));
    expect($storage->has('cart.default'))->toBeTrue();

    $this->actingAs(new GenericUser(['id' => 5]));

    expect($storage->has('cart.default'))->toBeFalse()
        ->and($storage->get('cart.default'))->toBeNull();
});

it('keeps cart instances apart', function (): void {
    $storage = regCacheStorage();

    $storage->put('cart.default', regCacheContent('d'));

    expect($storage->get('cart.wishlist'))->toBeNull()
        ->and($storage->has('cart.wishlist'))->toBeFalse();
});

it('honours the ttl after a cached read', function (): void {
    config(['cart.ttl' => 10]);
    Carbon::setTestNow('2026-01-01 12:00:00');

    $storage = regCacheStorage();
    $storage->put('cart.default', regCacheContent('ttl'));
    expect($storage->get('cart.default'))->toHaveCount(1)
        ->and($storage->has('cart.default'))->toBeTrue();

    Carbon::setTestNow('2026-01-01 12:30:00');

    expect($storage->has('cart.default'))->toBeFalse()
        ->and($storage->get('cart.default', 'expired'))->toBe('expired');
});

it('starts a fresh cart when writing to an expired one', function (): void {
    config(['cart.ttl' => 10]);
    Carbon::setTestNow('2026-01-01 12:00:00');

    regCacheStorage()->put('cart.default', regCacheContent('old'));

    Carbon::setTestNow('2026-01-01 12:30:00');

    regCacheStorage()->put('cart.default', regCacheContent('new'));

    expect(cartItemsOf(regCacheStorage()->get('cart.default'))->first()?->id)->toBe('new');
});

it('resolves the cart and its storage once per request or job', function (): void {
    config(['cart.storage.driver' => 'database']);

    $cart = resolveCart();
    $storage = app(CartStorage::class);

    expect(resolveCart())->toBe($cart)
        ->and(app(CartStorage::class))->toBe($storage);

    app()->forgetScopedInstances();

    expect(resolveCart())->not->toBe($cart)
        ->and(app(CartStorage::class))->not->toBe($storage);
});

it('does not leak cart state between jobs of a long-lived worker', function (): void {
    config(['cart.storage.driver' => 'database']);

    $this->actingAs(new GenericUser(['id' => 1]));
    $cart = resolveCart();
    $cart->add('1', 'A product', '', 1, 10.0, 10.0, 0.0);
    $cart->addCoupon(new CartCoupon(hash: 'h', code: 'A-ONLY', type: 'fixed', value: 1.0));

    // What the queue worker does between jobs.
    app()->forgetScopedInstances();
    Facade::clearResolvedInstances();

    $this->actingAs(new GenericUser(['id' => 2]));
    $next = resolveCart();

    expect($next->listCoupons())->toBeEmpty()
        ->and($next->isEmpty())->toBeTrue();
});
