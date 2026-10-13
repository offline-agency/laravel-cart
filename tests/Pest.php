<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartContentCodec;
use OfflineAgency\LaravelCart\Tests\FeatureTestCase;

uses(FeatureTestCase::class)->in('.');

/**
 * The cart instance the container resolves for the "cart" binding.
 */
function resolveCart(): Cart
{
    $cart = app('cart');

    if (! $cart instanceof Cart) {
        throw new UnexpectedValueException('The "cart" binding did not resolve to a Cart instance.');
    }

    return $cart;
}

/**
 * Narrow a value read back from a cart storage to the cart items it must hold.
 *
 * @return Collection<string, CartItem>
 */
function cartItemsOf(mixed $value): Collection
{
    $items = $value instanceof Collection ? CartContentCodec::cartItems($value) : null;

    if ($items === null) {
        throw new UnexpectedValueException('Expected a collection of cart items.');
    }

    return $items;
}

/**
 * Narrow a value read back from a cart storage to the cart-level coupons it must hold.
 *
 * @return Collection<array-key, CartCoupon>
 */
function cartCouponsOf(mixed $value): Collection
{
    if (! $value instanceof Collection) {
        throw new UnexpectedValueException('Expected a collection of cart coupons.');
    }

    $coupons = [];

    foreach ($value as $key => $coupon) {
        if (! $coupon instanceof CartCoupon) {
            throw new UnexpectedValueException('Expected a collection of cart coupons.');
        }

        $coupons[$key] = $coupon;
    }

    return new Collection($coupons);
}

/**
 * Decode a JSON document that must be an object or array.
 *
 * @return array<array-key, mixed>
 */
function decodeJson(string|false $json): array
{
    $decoded = is_string($json) ? json_decode($json, true, flags: JSON_THROW_ON_ERROR) : null;

    if (! is_array($decoded)) {
        throw new UnexpectedValueException('Expected a JSON object or array.');
    }

    return $decoded;
}
