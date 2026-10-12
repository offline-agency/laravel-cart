<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Exceptions;

final class InvalidDriverException extends CartException
{
    public function __construct(
        public readonly string $driver,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? $message : "The cart storage driver [{$driver}] is not supported.",
            $code,
            $previous
        );
    }
}
