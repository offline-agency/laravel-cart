<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

final readonly class CartStored
{
    public function __construct(
        public mixed $identifier,
        public string $instance,
    ) {}
}
