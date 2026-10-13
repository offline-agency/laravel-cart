<?php

declare(strict_types=1);

use Carbon\Carbon;
use OfflineAgency\LaravelCart\Enums\CouponType;
use OfflineAgency\LaravelCart\Tests\Fixtures\PlainPromo;
use OfflineAgency\LaravelCart\Tests\Fixtures\PromoModel;

/**
 * @param  array<string, mixed>  $attributes
 */
function promoModel(array $attributes = []): PromoModel
{
    $promo = new PromoModel($attributes);

    if (array_key_exists('id', $attributes)) {
        $promo->setAttribute('id', $attributes['id']);
    }

    return $promo;
}

it('getHash falls back to the model primary key', function (): void {
    expect(promoModel(['id' => 42])->getHash())->toBe('42');
});

it('getHash returns an empty string when the model has no key', function (): void {
    expect(promoModel()->getHash())->toBe('');
});

it('getCode reads the coupon_code column', function (): void {
    expect(promoModel(['coupon_code' => 'SUMMER25'])->getCode())->toBe('SUMMER25');
});

it('getCode returns an empty string when the column is missing', function (): void {
    expect(promoModel()->getCode())->toBe('');
});

it('getCouponType maps the coupon_type column to the enum', function (): void {
    expect(promoModel(['coupon_type' => 'percentage'])->getCouponType())->toBe(CouponType::Percentage)
        ->and(promoModel(['coupon_type' => 'fixed'])->getCouponType())->toBe(CouponType::Fixed);
});

it('getCouponType defaults to fixed when the column is missing', function (): void {
    expect(promoModel()->getCouponType())->toBe(CouponType::Fixed);
});

it('getCouponType throws on an unknown coupon type', function (): void {
    promoModel(['coupon_type' => 'buy-one-get-one'])->getCouponType();
})->throws(ValueError::class);

it('getCouponValue casts the column to float', function (): void {
    expect(promoModel(['coupon_value' => '12.50'])->getCouponValue())->toBe(12.5);
});

it('getCouponValue defaults to zero when the column is missing', function (): void {
    expect(promoModel()->getCouponValue())->toBe(0.0);
});

it('getCouponExpiresAt returns null when the column is missing', function (): void {
    expect(promoModel()->getCouponExpiresAt())->toBeNull();
});

it('getCouponExpiresAt passes through an existing Carbon instance', function (): void {
    $date = Carbon::parse('2027-01-31 12:00:00');

    expect(promoModel(['coupon_expires_at' => $date])->getCouponExpiresAt())->toBe($date);
});

it('getCouponExpiresAt parses a string date into Carbon', function (): void {
    $expiresAt = promoModel(['coupon_expires_at' => '2027-01-31'])->getCouponExpiresAt();

    expect($expiresAt)->toBeInstanceOf(Carbon::class)
        ->and($expiresAt?->toDateString())->toBe('2027-01-31');
});

it('getCouponUsageLimit casts the column to int and defaults to null', function (): void {
    expect(promoModel(['coupon_usage_limit' => '3'])->getCouponUsageLimit())->toBe(3)
        ->and(promoModel()->getCouponUsageLimit())->toBeNull();
});

it('getMinCartAmount casts the column to float and defaults to null', function (): void {
    expect(promoModel(['coupon_min_cart_amount' => '50'])->getMinCartAmount())->toBe(50.0)
        ->and(promoModel()->getMinCartAmount())->toBeNull();
});

it('isExpired returns false when no expiry date is set', function (): void {
    expect(promoModel()->isExpired())->toBeFalse();
});

it('isExpired returns false for a future expiry date', function (): void {
    expect(promoModel(['coupon_expires_at' => Carbon::now()->addDay()])->isExpired())->toBeFalse();
});

it('isExpired returns true for a past expiry date', function (): void {
    expect(promoModel(['coupon_expires_at' => Carbon::now()->subDay()])->isExpired())->toBeTrue();
});

it('isApplicableTo returns true when no minimum is set', function (): void {
    expect(promoModel()->isApplicableTo(0.0))->toBeTrue();
});

it('isApplicableTo compares the cart total against the minimum', function (): void {
    $promo = promoModel(['coupon_min_cart_amount' => 50.0]);

    expect($promo->isApplicableTo(49.99))->toBeFalse()
        ->and($promo->isApplicableTo(50.0))->toBeTrue()
        ->and($promo->isApplicableTo(50.01))->toBeTrue();
});

it('toArray merges the coupon fields onto the model attributes', function (): void {
    $array = promoModel([
        'id' => 7,
        'coupon_code' => 'SUMMER25',
        'coupon_type' => 'percentage',
        'coupon_value' => 25.0,
        'coupon_expires_at' => Carbon::parse('2027-08-31 00:00:00'),
        'coupon_usage_limit' => 10,
        'coupon_min_cart_amount' => 50.0,
    ])->toArray();

    expect($array['hash'])->toBe('7')
        ->and($array['code'])->toBe('SUMMER25')
        ->and($array['type'])->toBe('percentage')
        ->and($array['value'])->toBe(25.0)
        ->and($array['expiresAt'])->toStartWith('2027-08-31')
        ->and($array['usageLimit'])->toBe(10)
        ->and($array['minCartAmount'])->toBe(50.0)
        ->and($array)->toHaveKey('coupon_code');
});

it('toArray serialises a missing expiry date as null', function (): void {
    expect(promoModel(['coupon_code' => 'FLAT5'])->toArray()['expiresAt'])->toBeNull();
});

it('toArray returns only the coupon fields when there is no parent implementation', function (): void {
    $array = (new PlainPromo)->toArray();

    expect(array_keys($array))->toBe([
        'hash', 'code', 'type', 'value', 'expiresAt', 'usageLimit', 'minCartAmount',
    ])->and($array['hash'])->toBe('plain-1')
        ->and($array['code'])->toBe('PLAIN10')
        ->and($array['type'])->toBe('percentage')
        ->and($array['value'])->toBe(10.0);
});
