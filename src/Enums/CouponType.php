<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Enums;

enum CouponType: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function isPercentage(): bool
    {
        return $this === self::Percentage;
    }

    public function isFixed(): bool
    {
        return $this === self::Fixed;
    }

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Discount',
            self::Percentage => 'Percentage Discount',
        };
    }
}
