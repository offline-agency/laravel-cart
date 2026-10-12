<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

final class CouponAlreadyAppliedException extends CartException
{
    public function __construct(
        public readonly string $couponCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Coupon [{$couponCode}] has already been applied to this cart.",
            $code,
            $previous
        );
    }
}
