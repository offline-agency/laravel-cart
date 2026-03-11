<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Events;

use OfflineAgency\LaravelCart\CartItem;

final readonly class CartItemAdded
{
    public function __construct(
        public CartItem $cartItem,
    ) {}
}
