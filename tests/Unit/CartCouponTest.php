<?php

declare(strict_types=1);

use Carbon\Carbon;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Enums\CouponType;

// ── Constructor & basic properties ────────────────────────────────────────────

it('stores all constructor arguments as public properties', function (): void {
    $expiresAt = Carbon::now()->addDays(7);

    $coupon = new CartCoupon(
        hash: 'abc123',
        code: 'SUMMER20',
        type: 'percentage',
        value: 20.0,
        isGlobal: true,
        expiresAt: $expiresAt,
        usageLimit: 100,
        minCartAmount: 50.0,
    );

    expect($coupon->hash)->toBe('abc123')
        ->and($coupon->code)->toBe('SUMMER20')
        ->and($coupon->type)->toBe('percentage')
        ->and($coupon->value)->toBe(20.0)
        ->and($coupon->isGlobal)->toBeTrue()
        ->and($coupon->expiresAt)->toEqual($expiresAt)
        ->and($coupon->usageLimit)->toBe(100)
        ->and($coupon->minCartAmount)->toBe(50.0);
});

it('defaults isGlobal to false', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->isGlobal)->toBeFalse();
});

it('defaults expiresAt, usageLimit, and minCartAmount to null', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->expiresAt)->toBeNull()
        ->and($coupon->usageLimit)->toBeNull()
        ->and($coupon->minCartAmount)->toBeNull();
});

// ── isPercentage / isFixed ────────────────────────────────────────────────────

it('isPercentage returns true for percentage type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'percentage', value: 10.0);

    expect($coupon->isPercentage())->toBeTrue();
});

it('isPercentage returns false for fixed type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 10.0);

    expect($coupon->isPercentage())->toBeFalse();
});

it('isFixed returns true for fixed type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 10.0);

    expect($coupon->isFixed())->toBeTrue();
});

it('isFixed returns false for percentage type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'percentage', value: 10.0);

    expect($coupon->isFixed())->toBeFalse();
});

// ── couponType() ──────────────────────────────────────────────────────────────

it('couponType returns CouponType::Fixed for fixed type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->couponType())->toBe(CouponType::Fixed);
});

it('couponType returns CouponType::Percentage for percentage type', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'percentage', value: 5.0);

    expect($coupon->couponType())->toBe(CouponType::Percentage);
});

// ── Couponable getters ────────────────────────────────────────────────────────

it('getHash returns the hash', function (): void {
    $coupon = new CartCoupon(hash: 'my-hash', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->getHash())->toBe('my-hash');
});

it('getCode returns the code', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'MY_CODE', type: 'fixed', value: 5.0);

    expect($coupon->getCode())->toBe('MY_CODE');
});

it('getCouponType returns the CouponType enum', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'percentage', value: 5.0);

    expect($coupon->getCouponType())->toBeInstanceOf(CouponType::class)
        ->and($coupon->getCouponType())->toBe(CouponType::Percentage);
});

it('getCouponValue returns the value', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 42.5);

    expect($coupon->getCouponValue())->toBe(42.5);
});

it('getCouponExpiresAt returns null when not set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->getCouponExpiresAt())->toBeNull();
});

it('getCouponExpiresAt returns the Carbon instance when set', function (): void {
    $expiresAt = Carbon::now()->addHour();
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, expiresAt: $expiresAt);

    expect($coupon->getCouponExpiresAt())->toEqual($expiresAt);
});

it('getCouponUsageLimit returns null when not set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->getCouponUsageLimit())->toBeNull();
});

it('getCouponUsageLimit returns the limit when set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, usageLimit: 50);

    expect($coupon->getCouponUsageLimit())->toBe(50);
});

it('getMinCartAmount returns null when not set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->getMinCartAmount())->toBeNull();
});

it('getMinCartAmount returns the minimum when set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, minCartAmount: 99.0);

    expect($coupon->getMinCartAmount())->toBe(99.0);
});

// ── isExpired ─────────────────────────────────────────────────────────────────

it('isExpired returns false when no expiresAt is set', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->isExpired())->toBeFalse();
});

it('isExpired returns false for a future expiry date', function (): void {
    $coupon = new CartCoupon(
        hash: 'h',
        code: 'C',
        type: 'fixed',
        value: 5.0,
        expiresAt: Carbon::now()->addDay(),
    );

    expect($coupon->isExpired())->toBeFalse();
});

it('isExpired returns true for a past expiry date', function (): void {
    $coupon = new CartCoupon(
        hash: 'h',
        code: 'C',
        type: 'fixed',
        value: 5.0,
        expiresAt: Carbon::now()->subSecond(),
    );

    expect($coupon->isExpired())->toBeTrue();
});

// ── isApplicableTo ────────────────────────────────────────────────────────────

it('isApplicableTo returns true when minCartAmount is null', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    expect($coupon->isApplicableTo(0.0))->toBeTrue()
        ->and($coupon->isApplicableTo(999.0))->toBeTrue();
});

it('isApplicableTo returns false when cart total is below minimum', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, minCartAmount: 100.0);

    expect($coupon->isApplicableTo(99.99))->toBeFalse();
});

it('isApplicableTo returns true when cart total equals minimum', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, minCartAmount: 100.0);

    expect($coupon->isApplicableTo(100.0))->toBeTrue();
});

it('isApplicableTo returns true when cart total exceeds minimum', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0, minCartAmount: 50.0);

    expect($coupon->isApplicableTo(150.0))->toBeTrue();
});

// ── toArray / jsonSerialize ───────────────────────────────────────────────────

it('toArray includes all properties', function (): void {
    $expiresAt = Carbon::now()->addWeek();

    $coupon = new CartCoupon(
        hash: 'abc',
        code: 'TEST',
        type: 'fixed',
        value: 10.0,
        isGlobal: true,
        expiresAt: $expiresAt,
        usageLimit: 5,
        minCartAmount: 20.0,
    );

    $array = $coupon->toArray();

    expect($array)->toHaveKeys(['hash', 'code', 'type', 'value', 'isGlobal', 'expiresAt', 'usageLimit', 'minCartAmount'])
        ->and($array['hash'])->toBe('abc')
        ->and($array['code'])->toBe('TEST')
        ->and($array['type'])->toBe('fixed')
        ->and($array['value'])->toBe(10.0)
        ->and($array['isGlobal'])->toBeTrue()
        ->and($array['expiresAt'])->toBe($expiresAt->toIso8601String())
        ->and($array['usageLimit'])->toBe(5)
        ->and($array['minCartAmount'])->toBe(20.0);
});

it('toArray serialises null fields as null', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    $array = $coupon->toArray();

    expect($array['expiresAt'])->toBeNull()
        ->and($array['usageLimit'])->toBeNull()
        ->and($array['minCartAmount'])->toBeNull();
});

it('jsonSerialize returns the same data as toArray', function (): void {
    $coupon = new CartCoupon(
        hash: 'h1',
        code: 'JSTEST',
        type: 'percentage',
        value: 15.0,
        isGlobal: false,
    );

    expect($coupon->jsonSerialize())->toBe($coupon->toArray());
});

it('can be JSON-encoded', function (): void {
    $coupon = new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 5.0);

    $json = json_encode($coupon);

    expect($json)->toBeString()->toBeJson();

    $decoded = json_decode($json, true);
    expect($decoded['code'])->toBe('C')
        ->and($decoded['type'])->toBe('fixed')
        ->and((float) $decoded['value'])->toBe(5.0);
});
