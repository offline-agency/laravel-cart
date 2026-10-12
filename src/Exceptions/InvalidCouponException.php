<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

final class InvalidCouponException extends CartException
{
    public function __construct(
        public readonly string $couponCode,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Coupon [{$couponCode}] is invalid and cannot be applied.",
            $code,
            $previous
        );
    }
}
