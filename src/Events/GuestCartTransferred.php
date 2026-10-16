<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

final readonly class GuestCartTransferred
{
    /** @param list<string> $instances */
    public function __construct(public int|string $userId, public array $instances) {}
}
