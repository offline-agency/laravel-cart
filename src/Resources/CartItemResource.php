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
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CartItem $item */
        $item = $this->resource;

        return $item->toArray();
    }
}
