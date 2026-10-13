<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OfflineAgency\LaravelCart\CartItem;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    private readonly CartItem $cartItem;

    public function __construct(CartItem $resource)
    {
        parent::__construct($resource);

        $this->cartItem = $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->cartItem->toArray();
    }
}
