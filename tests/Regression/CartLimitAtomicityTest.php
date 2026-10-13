<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemUpdated;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use PHPUnit\Framework\Assert;

/**
 * Defect 8: add()/update() mutated the stored items before the limits were
 * checked, so a rejected operation still changed the cart.
 */
function regLimitCart(string $driver): Cart
{
    $storage = $driver === 'database'
        ? new DatabaseStorage(app(DatabaseManager::class))
        : new SessionStorage(app('session'));

    return new Cart($storage, app('events'));
}

/**
 * @return array<string, mixed>
 */
function regLimitSnapshot(Cart $cart): array
{
    return [
        'items' => $cart->content()->map(fn (CartItem $i): array => $i->toArray())->all(),
        'keys' => $cart->content()->keys()->all(),
        'count' => $cart->count(),
        'total' => $cart->total(),
        'subtotal' => $cart->subtotal(),
        'coupons' => $cart->listCoupons()->keys()->all(),
        'itemCoupons' => array_keys($cart->coupons()),
    ];
}

/**
 * @return array{0: Cart, 1: CartItem}
 */
function regLimitSeed(string $driver): array
{
    $cart = regLimitCart($driver);
    $item = $cart->add('1', 'Existing', '', 2, 10.0, 10.0, 0.0, options: ['size' => 'M']);
    $cart->add('2', 'Other', '', 1, 5.0, 5.0, 0.0);
    $cart->addItemCoupon($item->rowId, 'ONE', 'fixed', 1.0);
    $cart->addCoupon(new CartCoupon(hash: 'g', code: 'G', type: 'fixed', value: 2.0));

    return [$cart, $item];
}

/**
 * @param  array<array-key, mixed>  $before
 * @param  callable(Cart): mixed  $operation
 */
function regLimitAssertUnchanged(string $driver, Cart $cart, array $before, callable $operation): void
{
    $dispatched = [];
    foreach ([CartItemAdded::class, CartItemUpdated::class, 'cart.added', 'cart.updated'] as $event) {
        app('events')->listen($event, function () use (&$dispatched, $event): void {
            $dispatched[] = $event;
        });
    }

    try {
        $operation($cart);
        Assert::fail('Expected CartLimitExceededException');
    } catch (CartLimitExceededException) {
        // expected
    }

    expect(regLimitSnapshot($cart))->toEqual($before)
        ->and(regLimitSnapshot(regLimitCart($driver)))->toEqual($before)
        ->and($dispatched)->toBe([]);
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    // Normalized storage keeps timestamps at second precision.
    Carbon::setTestNow('2026-03-01 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('leaves the cart untouched when add() exceeds max_qty_per_item on an existing row', function (string $driver): void {
    [$cart] = regLimitSeed($driver);
    $before = regLimitSnapshot($cart);
    config(['cart.limits.max_qty_per_item' => 3]);

    regLimitAssertUnchanged($driver, $cart, $before, fn (Cart $c) => $c->add('1', 'Existing', '', 2, 10.0, 10.0, 0.0, options: ['size' => 'M']));
})->with(['session', 'database']);

it('leaves the cart untouched when add() exceeds max_items', function (string $driver): void {
    [$cart] = regLimitSeed($driver);
    $before = regLimitSnapshot($cart);
    config(['cart.limits.max_items' => 2]);

    regLimitAssertUnchanged($driver, $cart, $before, fn (Cart $c) => $c->add('3', 'New', '', 1, 1.0, 1.0, 0.0));
})->with(['session', 'database']);

it('leaves the cart untouched when add() exceeds max_total', function (string $driver): void {
    [$cart] = regLimitSeed($driver);
    $before = regLimitSnapshot($cart);
    config(['cart.limits.max_total' => 30]);

    regLimitAssertUnchanged($driver, $cart, $before, fn (Cart $c) => $c->add('3', 'Pricey', '', 1, 100.0, 100.0, 0.0));
})->with(['session', 'database']);

it('leaves the cart untouched when update() exceeds max_qty_per_item', function (string $driver): void {
    [$cart, $item] = regLimitSeed($driver);
    $before = regLimitSnapshot($cart);
    config(['cart.limits.max_qty_per_item' => 3]);

    regLimitAssertUnchanged($driver, $cart, $before, fn (Cart $c) => $c->update($item->rowId, 10));
})->with(['session', 'database']);

it('leaves the cart untouched when an update() that changes the row id exceeds a limit', function (string $driver): void {
    [$cart, $item] = regLimitSeed($driver);
    $before = regLimitSnapshot($cart);
    config(['cart.limits.max_qty_per_item' => 3]);

    regLimitAssertUnchanged($driver, $cart, $before, fn (Cart $c) => $c->update($item->rowId, [
        'qty' => 10,
        'options' => ['size' => 'XL'],
    ]));
})->with(['session', 'database']);

it('still applies valid updates and adds', function (string $driver): void {
    [$cart, $item] = regLimitSeed($driver);
    config(['cart.limits.max_qty_per_item' => 5, 'cart.limits.max_items' => 3]);

    $cart->update($item->rowId, 4);
    $cart->add('3', 'Third', '', 1, 1.0, 1.0, 0.0);

    expect(regLimitCart($driver)->get($item->rowId)->qty)->toBe(4)
        ->and(regLimitCart($driver)->uniqueCount())->toBe(3);
})->with(['session', 'database']);
