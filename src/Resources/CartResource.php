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
    private readonly Cart $cart;

    public function __construct(Cart $resource)
    {
        parent::__construct($resource);

        $this->cart = $resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->cart->toArray();
    }
}
