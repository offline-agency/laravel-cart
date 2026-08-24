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

    public $coupon_code = 'PLAIN10';

    public $coupon_type = 'percentage';

    public $coupon_value = 10.0;

    public $coupon_expires_at = null;

    public $coupon_usage_limit = null;

    public $coupon_min_cart_amount = null;

    public function getKey(): string
    {
        return 'plain-1';
    }
}
