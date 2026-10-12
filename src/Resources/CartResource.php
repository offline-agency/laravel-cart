<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OfflineAgency\LaravelCart\Cart;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @param  Cart  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;

        return $cart->toArray();
    }
}
