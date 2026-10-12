<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\CartStorage;

class DatabaseStorage implements CartStorage
{
    /** @var array<string, mixed> */
    private array $cache = [];

    private readonly ConnectionInterface $connection;

    private readonly string $cartsTable;

    private readonly string $cartItemsTable;

    public function __construct(
        DatabaseManager $database,
    ) {
        $this->connection = $database->connection(config('cart.database.connection'));
        $this->cartsTable = (string) config('cart.database.carts_table', 'carts');
        $this->cartItemsTable = (string) config('cart.database.cart_items_table', 'cart_items');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $userId = $this->resolveUserId();
        $instance = $this->parseInstance($key);

        $cart = $this->connection->table($this->cartsTable)
            ->where('user_id', $userId)
            ->where('instance', $instance)
            ->first();

        if ($cart === null) {
            return $default;
        }

        // Check TTL expiration
        if (! empty($cart->expires_at) && now()->greaterThan($cart->expires_at)) {
            $this->connection->table($this->cartItemsTable)->where('cart_id', $cart->id)->delete();
            $this->connection->table($this->cartsTable)->where('id', $cart->id)->delete();

            return $default;
        }

        // Determine what type of data is requested
        if (str_ends_with($key, '_global_coupons')) {
            $result = ! empty($cart->coupons)
                ? $this->deserializeCoupons($cart->coupons)
                : collect();
            $this->cache[$key] = $result;

            return $result;
        }

        if (str_ends_with($key, '_cart_info')) {
            $result = ! empty($cart->options) ? json_decode($cart->options, true) : [];
            $this->cache[$key] = $result;

            return $result;
        }

        if (str_ends_with($key, '_meta')) {
            $result = ! empty($cart->meta) ? json_decode($cart->meta, true) : [];
            $this->cache[$key] = $result;

            return $result;
        }

        // Default: cart content (Collection of CartItems)
        $items = $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cart->id)
            ->get();

        $content = new Collection;
        foreach ($items as $row) {
            $cartItem = $this->hydrateCartItem($row);
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->cache[$key] = $content;

        return $content;
    }

    public function put(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;

        $userId = $this->resolveUserId();
        $instance = $this->parseInstance($key);

        $cartId = $this->ensureCartExists($userId, $instance);

        if (str_ends_with($key, '_global_coupons')) {
            $this->connection->table($this->cartsTable)
                ->where('id', $cartId)
                ->update([
                    'coupons' => $value instanceof Collection && $value->isNotEmpty()
                        ? json_encode($value->map(fn ($c) => method_exists($c, 'toArray') ? $c->toArray() : $c)->values()->all())
                        : null,
                    'updated_at' => now(),
                ]);

            return;
        }

        if (str_ends_with($key, '_cart_info')) {
            $this->connection->table($this->cartsTable)
                ->where('id', $cartId)
                ->update([
                    'options' => ! empty($value) ? json_encode($value) : null,
                    'updated_at' => now(),
                ]);

            return;
        }

        if (str_ends_with($key, '_meta')) {
            $this->connection->table($this->cartsTable)
                ->where('id', $cartId)
                ->update([
                    'meta' => ! empty($value) ? json_encode($value) : null,
                    'updated_at' => now(),
                ]);

            return;
        }

        // Default: cart content (Collection of CartItems)
        if ($value instanceof Collection) {
            $this->syncCartItems($cartId, $value);
        }
    }

    public function has(string $key): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return true;
        }

        $userId = $this->resolveUserId();
        $instance = $this->parseInstance($key);

        return $this->connection->table($this->cartsTable)
            ->where('user_id', $userId)
            ->where('instance', $instance)
            ->exists();
    }

    public function remove(string $key): void
    {
        unset($this->cache[$key]);

        $userId = $this->resolveUserId();
        $instance = $this->parseInstance($key);

        $cart = $this->connection->table($this->cartsTable)
            ->where('user_id', $userId)
            ->where('instance', $instance)
            ->first();

        if ($cart === null) {
            return;
        }

        $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cart->id)
            ->delete();

        if (str_ends_with($key, '_global_coupons')) {
            $this->connection->table($this->cartsTable)
                ->where('id', $cart->id)
                ->update(['coupons' => null, 'updated_at' => now()]);

            return;
        }

        $this->connection->table($this->cartsTable)
            ->where('id', $cart->id)
            ->delete();
    }

    private function resolveUserId(): string
    {
        /** @var string|int|null $authId */
        $authId = auth()->id();

        return (string) ($authId ?? session()->getId());
    }

    /**
     * Parse the cart instance name from a storage key.
     * Keys follow pattern: "{instance}", "{instance}_global_coupons", "{instance}_cart_info", "{instance}_meta"
     */
    private function parseInstance(string $key): string
    {
        foreach (['_global_coupons', '_cart_info', '_meta'] as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return substr($key, 0, -strlen($suffix));
            }
        }

        return $key;
    }

    private function ensureCartExists(string $userId, string $instance): int
    {
        $cart = $this->connection->table($this->cartsTable)
            ->where('user_id', $userId)
            ->where('instance', $instance)
            ->first();

        if ($cart !== null) {
            return (int) $cart->id;
        }

        $ttl = config('cart.ttl');
        $expiresAt = $ttl !== null ? now()->addMinutes((int) $ttl) : null;

        return (int) $this->connection->table($this->cartsTable)->insertGetId([
            'user_id' => $userId,
            'instance' => $instance,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  Collection<string, CartItem>  $content
     */
    private function syncCartItems(int $cartId, Collection $content): void
    {
        // Delete existing items and re-insert
        $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cartId)
            ->delete();

        foreach ($content as $item) {
            $this->connection->table($this->cartItemsTable)->insert([
                'cart_id' => $cartId,
                'row_id' => $item->rowId,
                'item_id' => (string) $item->id,
                'name' => $item->name,
                'subtitle' => $item->subtitle ?? '',
                'price' => $item->price,
                'total_price' => $item->totalPrice,
                'vat' => $item->vat,
                'vat_rate' => $item->vatRate,
                'vat_fc_code' => $item->vatFcCode ?? '',
                'product_fc_code' => $item->productFcCode ?? '',
                'url_img' => $item->urlImg ?? '',
                'qty' => $item->qty,
                'options' => json_encode($item->options->toArray()),
                'applied_coupons' => ! empty($item->appliedCoupons) ? json_encode($item->appliedCoupons) : null,
                'discount_value' => $item->discountValue ?? 0.0,
                'associated_model' => $item->associatedModel,
                'created_at' => $item->createdAt,
                'updated_at' => $item->updatedAt,
            ]);
        }

        $this->connection->table($this->cartsTable)
            ->where('id', $cartId)
            ->update(['updated_at' => now()]);
    }

    private function hydrateCartItem(object $row): CartItem
    {
        $options = ! empty($row->options) ? json_decode($row->options, true) : [];

        $cartItem = new CartItem(
            id: $row->item_id,
            name: $row->name,
            subtitle: $row->subtitle ?? '',
            qty: $row->qty,
            price: (float) $row->price,
            totalPrice: (float) $row->total_price,
            vatFcCode: $row->vat_fc_code ?? '',
            productFcCode: $row->product_fc_code ?? '',
            vat: (float) $row->vat,
            urlImg: $row->url_img ?? '',
            options: $options ?? [],
        );

        if (! empty($row->associated_model)) {
            $cartItem->associate($row->associated_model);
        }

        if (! empty($row->applied_coupons)) {
            $cartItem->appliedCoupons = json_decode($row->applied_coupons, true) ?? [];
        }

        $cartItem->discountValue = (float) ($row->discount_value ?? 0.0);

        return $cartItem;
    }

    private function deserializeCoupons(string $json): Collection
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return collect();
        }

        return collect($data)->mapWithKeys(function (array $c) {
            $coupon = new \OfflineAgency\LaravelCart\CartCoupon(
                hash: $c['hash'],
                code: $c['code'],
                type: $c['type'],
                value: (float) $c['value'],
                isGlobal: $c['isGlobal'] ?? false,
                expiresAt: isset($c['expiresAt']) ? \Carbon\Carbon::parse($c['expiresAt']) : null,
                usageLimit: $c['usageLimit'] ?? null,
                minCartAmount: isset($c['minCartAmount']) ? (float) $c['minCartAmount'] : null,
            );

            return [$coupon->hash => $coupon];
        });
    }
}
