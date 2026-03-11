<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Enums;

enum CartEventType: string
{
    case Added = 'cart.added';
    case Updated = 'cart.updated';
    case Removed = 'cart.removed';
    case Stored = 'cart.stored';
    case Restored = 'cart.restored';
    case CouponRemoved = 'cart.coupon_removed';
    case CouponsCleared = 'cart.coupons_cleared';
    case GlobalCouponAdded = 'cart.global_coupon_added';
    case GlobalCouponRemoved = 'cart.global_coupon_removed';
}
