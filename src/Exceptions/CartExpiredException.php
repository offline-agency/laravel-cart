<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

final class CartExpiredException extends CartException
{
    public function __construct(
        public readonly string $identifier,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "The cart for identifier [{$identifier}] has expired.",
            $code,
            $previous
        );
    }
}
