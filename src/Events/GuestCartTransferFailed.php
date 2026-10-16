<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

use OfflineAgency\LaravelCart\Exceptions\GuestCartTransferException;

final readonly class GuestCartTransferFailed
{
    public function __construct(public int|string $userId, public GuestCartTransferException $exception) {}
}
