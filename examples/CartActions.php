<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Examples;

use Closure;
use Illuminate\Validation\ValidationException;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Resources\CartResource;

/** Application recipe, not an additional package runtime service. */
final class CartActions
{
    /**
     * Catalog must authorize/select the requested product and variant server-side.
     *
     * @param  Closure(int, string): array{name: string, price: float, gross: float, vat: float, stock: int}  $catalog
     */
    public function __construct(private readonly Cart $cart, private readonly Closure $catalog) {}

    public function add(int $productId, string $size, int $quantity, bool $wishlist = false): CartItem
    {
        return $this->within($wishlist ? 'wishlist' : 'default', function () use ($productId, $size, $quantity): CartItem {
            $this->quantity($quantity);
            $product = ($this->catalog)($productId, $size);
            $existing = $this->cart->content()->first(fn (CartItem $item): bool => (string) $item->id === (string) $productId && $item->options->get('size') === $size);
            $this->available($quantity + ($existing->qty ?? 0), $product['stock']);

            // docs:add:start
            // Catalog values are trusted server-side; clients supply only product/size/quantity.
            return $this->cart->add(id: $productId, name: $product['name'], qty: $quantity,
                price: $product['price'], totalPrice: $product['gross'], vat: $product['vat'], options: ['size' => $size]);
            // docs:add:end
        });
    }

    public function update(string $rowId, int $quantity): void
    {
        $this->within('default', function () use ($rowId, $quantity): void {
            $this->quantity($quantity);
            $item = $this->row($rowId);
            $size = $item->options->get('size');
            $productId = filter_var($item->id, FILTER_VALIDATE_INT);
            if ($productId === false || ! is_string($size)) {
                throw ValidationException::withMessages(['cart' => 'This row is not a catalog variant.']);
            }
            $product = ($this->catalog)($productId, $size);
            $this->available($quantity, $product['stock']);
            // docs:update:start
            $this->cart->update($rowId, ['qty' => $quantity, 'price' => $product['price'],
                'totalPrice' => $product['gross'], 'vat' => $product['vat']]);
            // docs:update:end
        });
    }

    public function remove(string $rowId): void
    {
        $this->within('default', function () use ($rowId): void {
            $this->cart->remove($this->row($rowId)->rowId);
        });
    }

    public function moveWishlistToCart(string $rowId, int $quantity): void
    {
        $this->within('wishlist', function () use ($rowId, $quantity): void {
            $this->quantity($quantity);
            $source = $this->row($rowId);
            $size = $source->options->get('size');
            $productId = filter_var($source->id, FILTER_VALIDATE_INT);
            if ($productId === false || ! is_string($size) || $source->qty < $quantity) {
                throw ValidationException::withMessages(['cart' => 'Select an available wishlist quantity.']);
            }
            // docs:move:start
            // Two operations: target succeeds before source is reduced. Not cross-instance atomic.
            $this->add($productId, $size, $quantity);
            $this->cart->update($source->rowId, ['qty' => $source->qty - $quantity]);
            // docs:move:end
        });
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return $this->within('default', fn (): array => (new CartResource($this->cart))->toArray(request()));
    }

    private function row(string $rowId): CartItem
    {
        return $this->cart->content()->get($rowId)
            ?? throw ValidationException::withMessages(['cart' => 'Select a row owned by the current cart.']);
    }

    private function quantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 99) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 99.']);
        }
    }

    private function available(int|float $quantity, int $stock): void
    {
        if ($quantity > $stock) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity is unavailable.']);
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function within(string $instance, Closure $operation): mixed
    {
        $previous = $this->cart->currentInstance();
        try {
            $this->cart->instance($instance);

            return $operation();
        } finally {
            $this->cart->instance($previous);
        }
    }
}
