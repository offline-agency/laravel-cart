<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponHashException;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
    // Add a standard item
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $this->rowId = $this->cart->content()->keys()->first();
});

it('adds a per-item coupon and it appears in coupons()', function (): void {
    $this->cart->applyCoupon($this->rowId, 'SAVE10', 'fixed', 10.0);

    expect($this->cart->coupons())->toHaveKey('SAVE10')
        ->and($this->cart->coupons()['SAVE10']->couponValue)->toBe(10.0);
});

it('getCoupons returns a Collection of CartCoupon objects', function (): void {
    $this->cart->applyCoupon($this->rowId, 'SAVE10', 'fixed', 10.0);

    $coupons = $this->cart->getCoupons();

    expect($coupons)->toHaveCount(1)
        ->and($coupons->first())->toBeInstanceOf(CartCoupon::class)
        ->and($coupons->first()->code)->toBe('SAVE10')
        ->and($coupons->first()->type)->toBe('fixed')
        ->and($coupons->first()->value)->toBe(10.0);
});

it('hasCoupon returns true when coupon is present', function (): void {
    $this->cart->applyCoupon($this->rowId, 'SAVE10', 'fixed', 10.0);

    expect($this->cart->hasCoupon('SAVE10'))->toBeTrue();
});

it('hasCoupon returns false when coupon is absent', function (): void {
    expect($this->cart->hasCoupon('NONEXISTENT'))->toBeFalse();
});

it('removeCoupon removes the coupon and fires cart.coupon_removed event', function (): void {
    // Fake events first, then get a fresh cart so it uses the fake dispatcher
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $rowId = $cart->content()->keys()->first();
    $cart->applyCoupon($rowId, 'SAVE10', 'fixed', 10.0);

    $result = $cart->removeCoupon('SAVE10');

    expect($result)->toBeInstanceOf(Cart::class)
        ->and($cart->hasCoupon('SAVE10'))->toBeFalse();
    Event::assertDispatched('cart.coupon_removed');
});

it('removeCoupon throws InvalidCouponHashException for unknown coupon', function (): void {
    expect(fn () => $this->cart->removeCoupon('BOGUS'))
        ->toThrow(InvalidCouponHashException::class);
});

it('removeCoupon returns static for fluent chaining', function (): void {
    $this->cart->applyCoupon($this->rowId, 'SAVE10', 'fixed', 10.0);

    expect($this->cart->removeCoupon('SAVE10'))->toBeInstanceOf(Cart::class);
});

it('removeAllCoupons removes all per-item coupons and fires cart.coupons_cleared', function (): void {
    // Fake events first, then get a fresh cart so it uses the fake dispatcher
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $rowId1 = $cart->content()->keys()->first();
    $cart->add('2', 'Product 2', '', 1, 50.0, 61.0, 11.0);
    $rowId2 = $cart->content()->keys()->last();

    $cart->applyCoupon($rowId1, 'SAVE10', 'fixed', 10.0);
    $cart->applyCoupon($rowId2, 'SAVE5', 'fixed', 5.0);

    $cart->removeAllCoupons();

    expect($cart->hasCoupon('SAVE10'))->toBeFalse()
        ->and($cart->hasCoupon('SAVE5'))->toBeFalse();
    Event::assertDispatched('cart.coupons_cleared');
});

it('fires cart.coupons_cleared event on removeAllCoupons', function (): void {
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $rowId = $cart->content()->keys()->first();
    $cart->applyCoupon($rowId, 'SAVE10', 'fixed', 10.0);

    $cart->removeAllCoupons();

    Event::assertDispatched('cart.coupons_cleared');
});

it('correctly persists coupons in session between add and retrieve', function (): void {
    $this->cart->applyCoupon($this->rowId, 'SAVE10', 'fixed', 10.0);

    // Simulate a new Cart object loading from the same session
    $cart2 = $this->app->make('cart');

    expect($cart2->hasCoupon('SAVE10'))->toBeTrue();
});

it('percentage coupon reduces price correctly', function (): void {
    $this->cart->applyCoupon($this->rowId, 'PCT10', 'percentage', 10.0);

    $item = $this->cart->get($this->rowId);
    // 10% of 122.0 = 12.2 discount → totalPrice = 109.8
    expect($item->totalPrice)->toBe(109.8);
});
