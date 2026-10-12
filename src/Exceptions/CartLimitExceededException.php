<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

final class CartLimitExceededException extends CartException
{
    public function __construct(
        public readonly string $limitType,
        public readonly float|int|null $limit,
        public readonly float|int|null $attempted,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "Cart limit exceeded: {$limitType} (max: {$limit}).",
            $code,
            $previous
        );
    }
}
