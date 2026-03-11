<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

use OfflineAgency\LaravelCart\CartCoupon;

final readonly class CouponRemoved
{
    public function __construct(
        public CartCoupon $coupon,
        public string $cartInstance,
    ) {}
}
