<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Traits\HasDiscount;

/**
 * Plain (non-Eloquent) consumer of the HasDiscount trait: it has no parent class,
 * so toArray() has no parent implementation to merge onto.
 */
class PlainPromo implements Couponable
{
    use HasDiscount;

    public string $coupon_code = 'PLAIN10';

    public string $coupon_type = 'percentage';

    public float $coupon_value = 10.0;

    public ?string $coupon_expires_at = null;

    public ?int $coupon_usage_limit = null;

    public ?float $coupon_min_cart_amount = null;

    public function getKey(): string
    {
        return 'plain-1';
    }
}
