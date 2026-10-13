<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Enums\CouponType;

it('exposes fixed and percentage as backed string cases', function (): void {
    expect(CouponType::Fixed->value)->toBe('fixed')
        ->and(CouponType::Percentage->value)->toBe('percentage')
        ->and(CouponType::cases())->toHaveCount(2);
});

it('builds the enum from its string value', function (): void {
    expect(CouponType::from('fixed'))->toBe(CouponType::Fixed)
        ->and(CouponType::from('percentage'))->toBe(CouponType::Percentage);
});

it('tryFrom returns null for an unknown value', function (): void {
    expect(CouponType::tryFrom('buy-one-get-one'))->toBeNull();
});

it('isFixed is true only for the fixed case', function (): void {
    expect(CouponType::Fixed->isFixed())->toBeTrue()
        ->and(CouponType::Percentage->isFixed())->toBeFalse();
});

it('isPercentage is true only for the percentage case', function (): void {
    expect(CouponType::Percentage->isPercentage())->toBeTrue()
        ->and(CouponType::Fixed->isPercentage())->toBeFalse();
});

it('label returns a human readable description per case', function (): void {
    expect(CouponType::Fixed->label())->toBe('Fixed Discount')
        ->and(CouponType::Percentage->label())->toBe('Percentage Discount');
});
