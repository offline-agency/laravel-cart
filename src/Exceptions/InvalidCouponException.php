<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

use RuntimeException;

final class InvalidCouponException extends RuntimeException
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
