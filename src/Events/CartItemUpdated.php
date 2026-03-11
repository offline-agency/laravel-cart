<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

use OfflineAgency\LaravelCart\CartItem;

final readonly class CartItemUpdated
{
    public function __construct(
        public CartItem $cartItem,
    ) {}
}
