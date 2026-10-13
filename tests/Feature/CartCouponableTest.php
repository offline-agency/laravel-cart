<?php

declare(strict_types=1);

use Carbon\Carbon;
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;
use OfflineAgency\LaravelCart\Tests\Fixtures\PromoModel;

beforeEach(function (): void {
    $this->cart = resolveCart();
    $this->cart->destroy();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function couponable(array $attributes): PromoModel
{
    $promo = new PromoModel($attributes);
    $promo->setAttribute('id', $attributes['id'] ?? 'promo-1');

    return $promo;
}

it('addCoupon accepts any Couponable and converts it to a CartCoupon', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon(couponable([
        'id' => 'promo-77',
        'coupon_code' => 'MODEL10',
        'coupon_type' => 'percentage',
        'coupon_value' => 10.0,
        'coupon_expires_at' => Carbon::now()->addMonth(),
        'coupon_usage_limit' => 5,
        'coupon_min_cart_amount' => 50.0,
    ]));

    $coupon = $this->cart->listCoupons()->firstOrFail();

    expect($this->cart->listCoupons())->toHaveCount(1)
        ->and($coupon->hash)->toBe('promo-77')
        ->and($coupon->code)->toBe('MODEL10')
        ->and($coupon->type)->toBe('percentage')
        ->and($coupon->value)->toBe(10.0)
        ->and($coupon->isGlobal)->toBeTrue()
        ->and($coupon->usageLimit)->toBe(5)
        ->and($coupon->minCartAmount)->toBe(50.0)
        ->and($coupon->expiresAt)->toBeInstanceOf(Carbon::class)
        ->and($this->cart->discount())->toBe(10.0);
});

it('addCoupon rejects an expired Couponable', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon(couponable([
        'id' => 'promo-old',
        'coupon_code' => 'EXPIRED',
        'coupon_type' => 'fixed',
        'coupon_value' => 5.0,
        'coupon_expires_at' => Carbon::now()->subDay(),
    ]));
})->throws(InvalidCouponException::class);

it('addCoupon rejects a Couponable below its minimum cart amount', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 10.0, 0.0);

    $this->cart->addCoupon(couponable([
        'id' => 'promo-min',
        'coupon_code' => 'BIGSPEND',
        'coupon_type' => 'fixed',
        'coupon_value' => 5.0,
        'coupon_min_cart_amount' => 500.0,
    ]));
})->throws(InvalidCouponException::class);

it('addCoupon refuses the same Couponable hash twice', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $promo = couponable([
        'id' => 'promo-dup',
        'coupon_code' => 'DUP',
        'coupon_type' => 'fixed',
        'coupon_value' => 5.0,
    ]);

    $this->cart->addCoupon($promo);
    $this->cart->addCoupon($promo);
})->throws(CouponAlreadyAppliedException::class);

it('syncCoupons drops a coupon once the cart falls below its minimum', function (): void {
    $item = $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon(couponable([
        'id' => 'promo-sync',
        'coupon_code' => 'MIN50',
        'coupon_type' => 'fixed',
        'coupon_value' => 10.0,
        'coupon_min_cart_amount' => 50.0,
    ]));

    expect($this->cart->listCoupons())->toHaveCount(1);

    $this->cart->remove($item->rowId);

    expect($this->cart->syncCoupons())->toBe(['MIN50'])
        ->and($this->cart->listCoupons())->toHaveCount(0)
        ->and($this->cart->hasCartCoupon('MIN50'))->toBeFalse();
});

it('syncCoupons keeps valid coupons and returns an empty array', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);
    $this->cart->addGlobalCoupon('keep-hash', 'KEEP', 'fixed', 5.0);

    expect($this->cart->syncCoupons())->toBe([])
        ->and($this->cart->listCoupons())->toHaveCount(1);
});

it('globalCouponDiscount stops once the total is fully consumed', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addGlobalCoupon('hash-all', 'WIPEOUT', 'fixed', 100.0);
    $this->cart->addGlobalCoupon('hash-extra', 'EXTRA', 'fixed', 25.0);

    expect($this->cart->globalCouponDiscount('100.00'))->toBe('100.00')
        ->and($this->cart->total())->toBe(0.0);
});
