<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

final readonly class CartRestored
{
    public function __construct(
        public int|string $identifier,
        public string $instance,
    ) {}
}
