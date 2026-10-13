<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use stdClass;

/**
 * Stores carts in the normalized `carts` / `cart_items` tables.
 *
 * A cart is owned either by the authenticated user (configured
 * `cart.users.foreign_key` column) or, for guests, by the session id
 * (`session_id` column). Every write runs in a transaction that locks the
 * cart row, so concurrent writers to the same cart are serialized and the
 * last committed write wins; a failed write leaves the previous cart intact.
 */
class DatabaseStorage implements CartStorage
{
    public const string GUEST_COLUMN = 'session_id';

    /** Storage key suffix => carts table column. */
    private const array SUFFIX_COLUMNS = [
        '_global_coupons' => 'coupons',
        '_cart_info' => 'options',
        '_meta' => 'meta',
    ];

    /**
     * Read-through cache, grouped by owner and cart instance.
     *
     * @var array<string, array{expires_at: ?Carbon, values: array<string, mixed>}>
     */
    private array $cache = [];

    private readonly ConnectionInterface $connection;

    private readonly string $cartsTable;

    private readonly string $cartItemsTable;

    private readonly string $ownerColumn;

    public function __construct(
        DatabaseManager $database,
    ) {
        $this->connection = $database->connection(config('cart.database.connection'));
        $this->cartsTable = (string) config('cart.database.carts_table', 'carts');
        $this->cartItemsTable = (string) config('cart.database.cart_items_table', 'cart_items');
        $this->ownerColumn = (string) config('cart.users.foreign_key', 'user_id');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        [$column, $owner] = $this->resolveOwner();
        $instance = $this->parseInstance($key);
        $cacheKey = $this->cacheKey($column, $owner, $instance);

        if ($this->freshCacheGroup($cacheKey) && array_key_exists($key, $this->cache[$cacheKey]['values'])) {
            return $this->cache[$cacheKey]['values'][$key];
        }

        $cart = $this->findCart($column, $owner, $instance);

        if ($cart === null) {
            return $default;
        }

        $result = match ($this->suffixOf($key)) {
            '_global_coupons' => $this->deserializeCoupons($cart->coupons),
            '_cart_info' => $this->decodeArray($cart->options),
            '_meta' => $this->decodeArray($cart->meta),
            default => $this->loadItems((int) $cart->id),
        };

        $this->remember($cacheKey, $cart, $key, $result);

        return $result;
    }

    public function put(string $key, mixed $value): void
    {
        [$column, $owner] = $this->resolveOwner();
        $instance = $this->parseInstance($key);
        $suffix = $this->suffixOf($key);

        $cart = $this->connection->transaction(function () use ($column, $owner, $instance, $suffix, $value): object {
            $cart = $this->lockOrCreateCart($column, $owner, $instance);

            if ($suffix !== null) {
                $this->connection->table($this->cartsTable)
                    ->where('id', $cart->id)
                    ->update([
                        self::SUFFIX_COLUMNS[$suffix] => $this->encodeCartColumn($suffix, $value),
                        'updated_at' => now(),
                    ]);
            } elseif ($value instanceof Collection) {
                $this->syncCartItems((int) $cart->id, $value);
            }

            return $cart;
        });

        $cacheKey = $this->cacheKey($column, $owner, $instance);

        if ($suffix !== null || $value instanceof Collection) {
            $this->remember($cacheKey, $cart, $key, $value);
        } else {
            unset($this->cache[$cacheKey]['values'][$key]);
        }
    }

    public function has(string $key): bool
    {
        [$column, $owner] = $this->resolveOwner();
        $instance = $this->parseInstance($key);

        if ($this->freshCacheGroup($this->cacheKey($column, $owner, $instance))) {
            return true;
        }

        return $this->findCart($column, $owner, $instance) !== null;
    }

    public function remove(string $key): void
    {
        [$column, $owner] = $this->resolveOwner();
        $instance = $this->parseInstance($key);
        $cacheKey = $this->cacheKey($column, $owner, $instance);
        $suffix = $this->suffixOf($key);

        if ($suffix === null) {
            unset($this->cache[$cacheKey]);
        } else {
            unset($this->cache[$cacheKey]['values'][$key]);
        }

        $cart = $this->findCart($column, $owner, $instance);

        if ($cart === null) {
            return;
        }

        if ($suffix === null) {
            $this->deleteCart((int) $cart->id);

            return;
        }

        // Suffix keys only clear their own column; items and the other columns stay.
        $this->connection->table($this->cartsTable)
            ->where('id', $cart->id)
            ->update([self::SUFFIX_COLUMNS[$suffix] => null, 'updated_at' => now()]);
    }

    /**
     * Authenticated carts are keyed by the configured user column, guest carts
     * by the session id. A session id is never written to the user column.
     *
     * @return array{0: string, 1: int|string}
     */
    private function resolveOwner(): array
    {
        /** @var int|string|null $authId */
        $authId = auth()->id();

        if ($authId !== null) {
            return [$this->ownerColumn, $authId];
        }

        return [self::GUEST_COLUMN, session()->getId()];
    }

    private function cacheKey(string $column, int|string $owner, string $instance): string
    {
        return $column."\0".$owner."\0".$instance;
    }

    /**
     * Whether a cached group exists and its cart has not expired since it was cached.
     */
    private function freshCacheGroup(string $cacheKey): bool
    {
        if (! isset($this->cache[$cacheKey])) {
            return false;
        }

        $expiresAt = $this->cache[$cacheKey]['expires_at'];

        if ($expiresAt !== null && now()->greaterThan($expiresAt)) {
            unset($this->cache[$cacheKey]);

            return false;
        }

        return true;
    }

    private function remember(string $cacheKey, object $cart, string $key, mixed $value): void
    {
        $this->cache[$cacheKey]['expires_at'] = empty($cart->expires_at) ? null : Carbon::parse($cart->expires_at);
        $this->cache[$cacheKey]['values'][$key] = $value;
    }

    /**
     * Parse the cart instance name from a storage key.
     * Keys follow pattern: "{instance}", "{instance}_global_coupons", "{instance}_cart_info", "{instance}_meta"
     */
    private function parseInstance(string $key): string
    {
        $suffix = $this->suffixOf($key);

        return $suffix === null ? $key : substr($key, 0, -strlen($suffix));
    }

    private function suffixOf(string $key): ?string
    {
        foreach (array_keys(self::SUFFIX_COLUMNS) as $suffix) {
            if (str_ends_with($key, $suffix)) {
                return $suffix;
            }
        }

        return null;
    }

    private function cartQuery(string $column, int|string $owner, string $instance): Builder
    {
        return $this->connection->table($this->cartsTable)
            ->where($column, $owner)
            ->where('instance', $instance);
    }

    /**
     * Find the owner's cart, purging it when its TTL has elapsed.
     */
    private function findCart(string $column, int|string $owner, string $instance, bool $lock = false): ?object
    {
        $cart = $this->cartQuery($column, $owner, $instance)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->first();

        if ($cart !== null && ! empty($cart->expires_at) && now()->greaterThan(Carbon::parse($cart->expires_at))) {
            $this->deleteCart((int) $cart->id);
            unset($this->cache[$this->cacheKey($column, $owner, $instance)]);

            return null;
        }

        return $cart;
    }

    /**
     * Return the locked cart row, creating it when missing. The unique
     * (owner, instance) index plus insertOrIgnore make concurrent creation safe:
     * whoever loses the race re-reads the row the winner inserted.
     */
    private function lockOrCreateCart(string $column, int|string $owner, string $instance): object
    {
        $cart = $this->findCart($column, $owner, $instance, lock: true);

        if ($cart !== null) {
            return $cart;
        }

        $ttl = config('cart.ttl');

        $this->connection->table($this->cartsTable)->insertOrIgnore([
            $column => $owner,
            'instance' => $instance,
            'expires_at' => $ttl !== null ? now()->addMinutes((int) $ttl) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->cartQuery($column, $owner, $instance)->lockForUpdate()->firstOrFail();
    }

    private function deleteCart(int $cartId): void
    {
        $this->connection->transaction(function () use ($cartId): void {
            $this->connection->table($this->cartItemsTable)->where('cart_id', $cartId)->delete();
            $this->connection->table($this->cartsTable)->where('id', $cartId)->delete();
        });
    }

    private function encodeCartColumn(string $suffix, mixed $value): ?string
    {
        if ($suffix === '_global_coupons') {
            return $value instanceof Collection && $value->isNotEmpty()
                ? (string) json_encode($value->map(fn ($c) => method_exists($c, 'toArray') ? $c->toArray() : $c)->values()->all())
                : null;
        }

        return ! empty($value) ? (string) json_encode($value) : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeArray(?string $json): array
    {
        $decoded = ! empty($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return Collection<string, CartItem>
     */
    private function loadItems(int $cartId): Collection
    {
        $content = new Collection;

        $rows = $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cartId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $cartItem = $this->hydrateCartItem($row);
            $content->put($cartItem->rowId, $cartItem);
        }

        return $content;
    }

    /**
     * Replace the cart lines. Must run inside the caller's transaction.
     *
     * @param  Collection<string, CartItem>  $content
     */
    private function syncCartItems(int $cartId, Collection $content): void
    {
        $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cartId)
            ->delete();

        $rows = $content->map(fn (CartItem $item): array => [
            'cart_id' => $cartId,
            'row_id' => $item->rowId,
            'item_id' => (string) $item->id,
            'name' => $item->name,
            'subtitle' => $item->subtitle ?? '',
            'original_price' => $item->originalPrice,
            'original_total_price' => $item->originalTotalPrice,
            'original_vat' => $item->originalVat,
            'price' => $item->price,
            'total_price' => $item->totalPrice,
            'vat' => $item->vat,
            'vat_rate' => $item->vatRate,
            'vat_fc_code' => $item->vatFcCode ?? '',
            'product_fc_code' => $item->productFcCode ?? '',
            'url_img' => $item->urlImg ?? '',
            'qty' => $item->qty,
            'options' => json_encode($item->options->toArray(), JSON_PRESERVE_ZERO_FRACTION),
            'applied_coupons' => ! empty($item->appliedCoupons) ? json_encode($item->appliedCoupons, JSON_PRESERVE_ZERO_FRACTION) : null,
            'discount_value' => $item->discountValue ?? 0.0,
            'associated_model' => $item->associatedModel,
            'created_at' => $item->createdAt,
            'updated_at' => $item->updatedAt,
        ])->values()->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->connection->table($this->cartItemsTable)->insert($chunk);
        }

        $this->connection->table($this->cartsTable)
            ->where('id', $cartId)
            ->update(['updated_at' => now()]);
    }

    private function hydrateCartItem(object $row): CartItem
    {
        $options = ! empty($row->options) ? json_decode($row->options, true) : [];

        // Build from the original amounts so construction-time derived values
        // (vat label, tax) match, then restore the current (discounted) state.
        $cartItem = new CartItem(
            id: $row->item_id,
            name: $row->name,
            subtitle: $row->subtitle ?? '',
            qty: $this->castQuantity($row->qty),
            price: (float) $row->original_price,
            totalPrice: (float) $row->original_total_price,
            vatFcCode: $row->vat_fc_code ?? '',
            productFcCode: $row->product_fc_code ?? '',
            vat: (float) $row->original_vat,
            urlImg: $row->url_img ?? '',
            createdAt: Carbon::parse($row->created_at),
            updatedAt: Carbon::parse($row->updated_at),
            options: is_array($options) ? $options : [],
        );

        $cartItem->rowId = $row->row_id;
        $cartItem->price = (float) $row->price;
        $cartItem->totalPrice = (float) $row->total_price;
        $cartItem->vat = (float) $row->vat;
        $cartItem->vatRate = (float) $row->vat_rate;
        $cartItem->taxRate = $cartItem->vatRate;
        $cartItem->tax = $cartItem->formatFloat($cartItem->originalPrice * ($cartItem->taxRate / 100));
        $cartItem->discountValue = (float) ($row->discount_value ?? 0.0);

        if (! empty($row->associated_model)) {
            $cartItem->associate($row->associated_model);
        }

        // Applied coupons are objects (see CartItem::applyCoupon()).
        $coupons = ! empty($row->applied_coupons) ? json_decode($row->applied_coupons) : null;
        $cartItem->appliedCoupons = $coupons instanceof stdClass ? get_object_vars($coupons) : [];

        return $cartItem;
    }

    private function castQuantity(mixed $qty): int|float
    {
        $qty = (float) $qty;

        return floor($qty) === $qty ? (int) $qty : $qty;
    }

    /**
     * @return Collection<string, CartCoupon>
     */
    private function deserializeCoupons(?string $json): Collection
    {
        $data = ! empty($json) ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return collect();
        }

        return collect($data)->mapWithKeys(function (array $c) {
            $coupon = new CartCoupon(
                hash: $c['hash'],
                code: $c['code'],
                type: $c['type'],
                value: (float) $c['value'],
                isGlobal: $c['isGlobal'] ?? false,
                expiresAt: isset($c['expiresAt']) ? Carbon::parse($c['expiresAt']) : null,
                usageLimit: $c['usageLimit'] ?? null,
                minCartAmount: isset($c['minCartAmount']) ? (float) $c['minCartAmount'] : null,
            );

            return [$coupon->hash => $coupon];
        });
    }
}
