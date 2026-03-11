<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();

    $this->artisan('migrate', ['--database' => 'testing']);
});

it('stores and restores cart items correctly', function (): void {
    $this->cart->add('1', 'Product A', '', 2, 10.0, 12.2, 2.2);

    $this->cart->store('user-1');
    $this->cart->destroy();

    expect($this->cart->count())->toBe(0);

    $this->cart->restore('user-1');

    expect($this->cart->count())->toBe(2);
});

it('does not lose variant options on restore', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2, '', '', '', ['color' => 'red', 'size' => 'L']);

    $this->cart->store('user-opts');
    $this->cart->destroy();
    $this->cart->restore('user-opts');

    $item = $this->cart->content()->first();
    expect($item->options['color'])->toBe('red')
        ->and($item->options['size'])->toBe('L');
});

it('stores and restores cart with per-item coupons in session', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $rowId = $this->cart->content()->keys()->first();
    $this->cart->applyCoupon($rowId, 'SAVE10', 'fixed', 10.0);

    $this->cart->store('user-coupon');
    $this->cart->destroy();
    $this->cart->restore('user-coupon');

    expect($this->cart->hasCoupon('SAVE10'))->toBeTrue();
});

it('stores and restores cart — global coupons persist in session', function (): void {
    $this->cart->addGlobalCoupon('gh1', 'GLOBAL10', 'fixed', 10.0);

    // Global coupons are session-based, not DB-stored — check session persistence
    $cart2 = $this->app->make('cart');

    expect($cart2->getGlobalCoupons())->toHaveCount(1)
        ->and($cart2->getGlobalCoupons()->first()->code)->toBe('GLOBAL10');
});

it('mergeOnRestore merges restored items with existing session cart', function (): void {
    // Add an item and store it
    $this->cart->add('1', 'Stored Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-merge');
    $this->cart->destroy();

    // Add a different item to the current session
    $this->cart->add('2', 'Session Product', '', 1, 20.0, 24.4, 4.4);

    // Restore with merge — should have both items
    $this->cart->restore('user-merge', mergeOnRestore: true);

    expect($this->cart->uniqueCount())->toBe(2);
});

it('restore without mergeOnRestore replaces current session cart', function (): void {
    $this->cart->add('1', 'Stored Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-replace');
    $this->cart->destroy();

    // Add different item to the session
    $this->cart->add('2', 'Session Product', '', 2, 20.0, 24.4, 4.4);

    // Restore without merge — should replace session item
    $this->cart->restore('user-replace');

    // Should have only the restored item
    expect($this->cart->uniqueCount())->toBe(1)
        ->and($this->cart->content()->first()->id)->toBe('1');
});

it('store and restore roundtrip for cart-level coupons via DB', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $this->cart->addGlobalCoupon('hash-db', 'DB_COUPON', 'fixed', 15.0);

    $this->cart->store('user-coupon-db');
    $this->cart->destroy();

    // After destroy, global coupons should be gone from session
    expect($this->cart->getGlobalCoupons())->toHaveCount(0);

    // Restore should bring back the cart-level coupon from the DB
    $this->cart->restore('user-coupon-db');

    expect($this->cart->getGlobalCoupons())->toHaveCount(1)
        ->and($this->cart->getGlobalCoupons()->first()->code)->toBe('DB_COUPON');
});
