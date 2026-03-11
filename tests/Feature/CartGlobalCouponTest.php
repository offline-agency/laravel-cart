<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponHashException;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('adds a global percentage coupon', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'SAVE10PCT', 'percentage', 10.0);

    $coupons = $this->cart->getGlobalCoupons();
    expect($coupons)->toHaveCount(1)
        ->and($coupons->first())->toBeInstanceOf(CartCoupon::class)
        ->and($coupons->first()->isGlobal)->toBeTrue()
        ->and($coupons->first()->isPercentage())->toBeTrue();
});

it('adds a global fixed coupon', function (): void {
    $this->cart->addGlobalCoupon('hash2', 'SAVE20', 'fixed', 20.0);

    $coupons = $this->cart->getGlobalCoupons();
    expect($coupons->first()->isFixed())->toBeTrue()
        ->and($coupons->first()->value)->toBe(20.0);
});

it('removes a global coupon', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'SAVE10PCT', 'percentage', 10.0);
    $this->cart->removeGlobalCoupon('hash1');

    expect($this->cart->getGlobalCoupons())->toHaveCount(0);
});

it('removeGlobalCoupon throws when hash not found', function (): void {
    expect(fn () => $this->cart->removeGlobalCoupon('nonexistent'))
        ->toThrow(InvalidCouponHashException::class);
});

it('computes percentage discount correctly', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'SAVE10PCT', 'percentage', 10.0);

    $discount = $this->cart->globalCouponDiscount('200.00');

    expect($discount)->toBe('20.00');
});

it('computes fixed discount correctly', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'SAVE15', 'fixed', 15.0);

    $discount = $this->cart->globalCouponDiscount('200.00');

    expect($discount)->toBe('15.00');
});

it('applies multiple global coupons percentage first then fixed', function (): void {
    // 10% of 200 = 20, remaining 180; then fixed 30 = discount total 50
    $this->cart->addGlobalCoupon('hashF', 'FIXED30', 'fixed', 30.0);
    $this->cart->addGlobalCoupon('hashP', 'PCT10', 'percentage', 10.0);

    $discount = $this->cart->globalCouponDiscount('200.00');

    expect($discount)->toBe('50.00');
});

it('never exceeds cart total with fixed coupon', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'BIG_FIXED', 'fixed', 9999.0);

    $discount = $this->cart->globalCouponDiscount('100.00');

    expect($discount)->toBe('100.00');
});

it('gets all global coupons', function (): void {
    $this->cart->addGlobalCoupon('h1', 'A', 'fixed', 5.0);
    $this->cart->addGlobalCoupon('h2', 'B', 'percentage', 5.0);

    expect($this->cart->getGlobalCoupons())->toHaveCount(2);
});

it('fires cart.global_coupon_added event', function (): void {
    // Fake before resolving Cart so it gets the fake dispatcher
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->addGlobalCoupon('hash1', 'SAVE10', 'fixed', 10.0);

    Event::assertDispatched('cart.global_coupon_added');
});

it('fires cart.global_coupon_removed event', function (): void {
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->addGlobalCoupon('hash1', 'SAVE10', 'fixed', 10.0);
    $cart->removeGlobalCoupon('hash1');

    Event::assertDispatched('cart.global_coupon_removed');
});

it('addGlobalCoupon returns static for fluent chaining', function (): void {
    expect($this->cart->addGlobalCoupon('h1', 'A', 'fixed', 5.0))->toBeInstanceOf(Cart::class);
});

it('removeGlobalCoupon returns static for fluent chaining', function (): void {
    $this->cart->addGlobalCoupon('h1', 'A', 'fixed', 5.0);
    expect($this->cart->removeGlobalCoupon('h1'))->toBeInstanceOf(Cart::class);
});

it('global coupons persist in session across requests', function (): void {
    $this->cart->addGlobalCoupon('hash1', 'SAVE10', 'fixed', 10.0);

    $cart2 = $this->app->make('cart');

    expect($cart2->getGlobalCoupons())->toHaveCount(1);
});

it('addCoupon throws InvalidCouponException for expired coupon', function (): void {
    $coupon = new CartCoupon(
        hash: 'hexp',
        code: 'EXPIRED',
        type: 'fixed',
        value: 10.0,
        isGlobal: true,
        expiresAt: Carbon::now()->subDay(),
    );

    expect(fn () => $this->cart->addCoupon($coupon))
        ->toThrow(InvalidCouponException::class);
});

it('addCoupon throws InvalidCouponException when minCartAmount is not met', function (): void {
    $this->cart->add('1', 'Cheap Item', '', 1, 5.0, 5.0, 0.0);

    $coupon = new CartCoupon(
        hash: 'hmin',
        code: 'MINREQ',
        type: 'fixed',
        value: 5.0,
        isGlobal: true,
        minCartAmount: 100.0,
    );

    expect(fn () => $this->cart->addCoupon($coupon))
        ->toThrow(InvalidCouponException::class);
});

it('addCoupon succeeds when cart total meets minCartAmount', function (): void {
    $this->cart->add('1', 'Expensive Item', '', 1, 150.0, 150.0, 0.0);

    $coupon = new CartCoupon(
        hash: 'hmin2',
        code: 'MINREQ2',
        type: 'fixed',
        value: 10.0,
        isGlobal: true,
        minCartAmount: 100.0,
    );

    $this->cart->addCoupon($coupon);

    expect($this->cart->hasCartCoupon('hmin2'))->toBeTrue();
});
