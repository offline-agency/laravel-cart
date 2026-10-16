<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\AtomicCartStorage;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Exceptions\CartStorageException;
use OfflineAgency\LaravelCart\Exceptions\GuestCartTransferException;
use OfflineAgency\LaravelCart\Support\CartConfig;
use OfflineAgency\LaravelCart\Support\CartContentCodec;
use OfflineAgency\LaravelCart\Support\Coerce;
use OfflineAgency\LaravelCart\Support\CouponData;
use stdClass;

/**
 * Stores carts in the normalized `carts` / `cart_items` tables.
 *
 * A cart is owned either by the authenticated user (configured
 * `cart.users.foreign_key` column) or, for guests, by the session id
 * (`session_id` column). Every write runs in a transaction that locks the
 * cart row. Package-managed mutations hold this lock from read through write.
 * Direct put() calls still replace the supplied value (last writer wins).
 */
class DatabaseStorage implements AtomicCartStorage
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
        $this->connection = $database->connection(CartConfig::connection());
        $this->cartsTable = CartConfig::string('cart.database.carts_table', 'carts');
        $this->cartItemsTable = CartConfig::string('cart.database.cart_items_table', 'cart_items');
        $this->ownerColumn = CartConfig::string('cart.users.foreign_key', 'user_id');
    }

    public function atomic(string $instance, Closure $operation): mixed
    {
        try {
            return $this->connection->transaction(function () use ($instance, $operation): mixed {
                $this->cache = [];
                [$column, $owner] = $this->resolveOwner();
                $this->lockOrCreateCart($column, $owner, $instance);

                return $operation();
            }, 3);
        } finally {
            // Never retain values read in a rolled-back transaction or an outer savepoint.
            $this->cache = [];
        }
    }

    public function afterCommit(Closure $callback): void
    {
        if ($this->connection->transactionLevel() > 0) {
            $this->connection->afterCommit($callback);
        } else {
            $callback();
        }
    }

    /**
     * Trusted server-side identity captured before login only. Never use request input.
     * All allowed instances are transferred in one transaction; deleting the source
     * makes replay a no-op. Both owners are locked in lexical owner/instance order.
     *
     * @param  list<string>  $instances
     */
    public function transferGuest(string $guestSession, array $instances): bool
    {
        [$column, $owner] = $this->resolveOwner();
        if ($column === self::GUEST_COLUMN) {
            throw new GuestCartTransferException('Authenticate before transferring a guest cart.');
        }
        try {
            return $this->connection->transaction(function () use ($guestSession, $instances, $column, $owner): bool {
                $this->cache = [];
                $locks = [];
                foreach (array_unique($instances) as $instance) {
                    $key = 'cart.'.$instance;
                    $locks[$this->cacheKey(self::GUEST_COLUMN, $guestSession, $key)] = [self::GUEST_COLUMN, $guestSession, $key];
                    $locks[$this->cacheKey($column, $owner, $key)] = [$column, $owner, $key];
                }
                ksort($locks);
                foreach ($locks as [$lockColumn, $lockOwner, $key]) {
                    if ($lockColumn === self::GUEST_COLUMN) {
                        $this->findCart($lockColumn, $lockOwner, $key, lock: true);
                    } else {
                        $this->lockOrCreateCart($lockColumn, $lockOwner, $key);
                    }
                }
                $transferred = false;
                foreach (array_unique($instances) as $instance) {
                    $key = 'cart.'.$instance;
                    $source = $this->findCart(self::GUEST_COLUMN, $guestSession, $key, lock: true);
                    if ($source === null) {
                        continue;
                    }
                    $cart = new Cart($this, app(Dispatcher::class));
                    $cart->instance($instance)->mergeGuestState(
                        $this->loadItems($this->cartId($source)),
                        $this->deserializeCoupons(Coerce::nullableString($source->coupons)),
                        $this->decodeArray(Coerce::nullableString($source->options)),
                        $this->decodeArray(Coerce::nullableString($source->meta)),
                    );
                    $this->deleteCart($this->cartId($source));
                    $transferred = true;
                }

                return $transferred;
            }, 3);
        } catch (QueryException|CartLimitExceededException|\InvalidArgumentException $exception) {
            throw new GuestCartTransferException('Cart transfer failed; both original carts were retained. Correct the limits or storage error and retry.', previous: $exception);
        } finally {
            $this->cache = [];
        }
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
            '_global_coupons' => $this->deserializeCoupons(Coerce::nullableString($cart->coupons)),
            '_cart_info' => $this->decodeArray(Coerce::nullableString($cart->options)),
            '_meta' => $this->decodeArray(Coerce::nullableString($cart->meta)),
            default => $this->loadItems($this->cartId($cart)),
        };

        $this->remember($cacheKey, $cart, $key, $result);

        return $result;
    }

    public function put(string $key, mixed $value): void
    {
        [$column, $owner] = $this->resolveOwner();
        $instance = $this->parseInstance($key);
        $suffix = $this->suffixOf($key);

        $cart = $this->connection->transaction(function () use ($column, $owner, $instance, $suffix, $value): stdClass {
            $cart = $this->lockOrCreateCart($column, $owner, $instance);

            if ($suffix !== null) {
                $this->connection->table($this->cartsTable)
                    ->where('id', $this->cartId($cart))
                    ->update([
                        self::SUFFIX_COLUMNS[$suffix] => $this->encodeCartColumn($suffix, $value),
                        'updated_at' => now(),
                    ]);
            } elseif ($value instanceof Collection) {
                $this->syncCartItems($this->cartId($cart), $value);
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
            $this->deleteCart($this->cartId($cart));

            return;
        }

        // Suffix keys only clear their own column; items and the other columns stay.
        $this->connection->table($this->cartsTable)
            ->where('id', $this->cartId($cart))
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

    private function remember(string $cacheKey, stdClass $cart, string $key, mixed $value): void
    {
        if ($this->connection->transactionLevel() > 0) {
            return;
        }
        $this->cache[$cacheKey]['expires_at'] = $this->expiresAt($cart);
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

    /**
     * @return key-of<self::SUFFIX_COLUMNS>|null
     */
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
    private function findCart(string $column, int|string $owner, string $instance, bool $lock = false): ?stdClass
    {
        $cart = $this->cartQuery($column, $owner, $instance)
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())
            ->first();

        if ($cart === null) {
            return null;
        }

        $expiresAt = $this->expiresAt($cart);

        if ($expiresAt !== null && now()->greaterThan($expiresAt)) {
            $this->deleteCart($this->cartId($cart));
            unset($this->cache[$this->cacheKey($column, $owner, $instance)]);

            return null;
        }

        return $cart;
    }

    private function cartId(stdClass $cart): int
    {
        return Coerce::int($cart->id);
    }

    private function expiresAt(stdClass $cart): ?Carbon
    {
        $expiresAt = Coerce::nullableString($cart->expires_at);

        return $expiresAt === null || $expiresAt === '' ? null : Carbon::parse($expiresAt);
    }

    /**
     * Return the locked cart row, creating it when missing. The unique
     * (owner, instance) index plus insertOrIgnore make concurrent creation safe:
     * whoever loses the race re-reads the row the winner inserted.
     */
    private function lockOrCreateCart(string $column, int|string $owner, string $instance): stdClass
    {
        $cart = $this->findCart($column, $owner, $instance, lock: true);

        if ($cart !== null) {
            return $cart;
        }

        $ttl = CartConfig::nullableNumber('cart.ttl');

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
                ? json_encode($value->map(fn (mixed $c): mixed => $c instanceof Couponable || $c instanceof Arrayable ? $c->toArray() : $c)->values()->all(), JSON_THROW_ON_ERROR)
                : null;
        }

        return ! empty($value) ? json_encode($value, JSON_THROW_ON_ERROR) : null;
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
     * @param  Collection<array-key, mixed>  $content
     */
    private function syncCartItems(int $cartId, Collection $content): void
    {
        $items = CartContentCodec::cartItems($content);

        if ($items === null) {
            throw new CartStorageException('The cart content to store must only contain cart items.');
        }

        $this->connection->table($this->cartItemsTable)
            ->where('cart_id', $cartId)
            ->delete();

        $rows = $items->map(fn (CartItem $item): array => [
            'cart_id' => $cartId,
            'row_id' => $item->rowId,
            'item_id' => (string) $item->id,
            'name' => $item->name,
            'subtitle' => $item->subtitle,
            'original_price' => $item->originalPrice,
            'original_total_price' => $item->originalTotalPrice,
            'original_vat' => $item->originalVat,
            'price' => $item->price,
            'total_price' => $item->totalPrice,
            'vat' => $item->vat,
            'vat_rate' => $item->vatRate,
            'vat_fc_code' => $item->vatFcCode,
            'product_fc_code' => $item->productFcCode,
            'url_img' => $item->urlImg,
            'qty' => $item->qty,
            'options' => json_encode($item->options->toArray(), JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR),
            'applied_coupons' => ! empty($item->appliedCoupons) ? json_encode($item->appliedCoupons, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) : null,
            'discount_value' => $item->discountValue,
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

    private function hydrateCartItem(stdClass $row): CartItem
    {
        // Build from the original amounts so construction-time derived values
        // (vat label, tax) match, then restore the current (discounted) state.
        $cartItem = new CartItem(
            id: Coerce::key($row->item_id),
            name: Coerce::string($row->name),
            subtitle: Coerce::nullableString($row->subtitle) ?? '',
            qty: $this->castQuantity(Coerce::float($row->qty)),
            price: Coerce::float($row->original_price),
            totalPrice: Coerce::float($row->original_total_price),
            vatFcCode: Coerce::nullableString($row->vat_fc_code) ?? '',
            productFcCode: Coerce::nullableString($row->product_fc_code) ?? '',
            vat: Coerce::float($row->original_vat),
            urlImg: Coerce::nullableString($row->url_img) ?? '',
            createdAt: Carbon::parse(Coerce::string($row->created_at)),
            updatedAt: Carbon::parse(Coerce::string($row->updated_at)),
            options: $this->decodeArray(Coerce::nullableString($row->options)),
        );

        $cartItem->rowId = Coerce::string($row->row_id);
        $cartItem->price = Coerce::float($row->price);
        $cartItem->totalPrice = Coerce::float($row->total_price);
        $cartItem->vat = Coerce::float($row->vat);
        $cartItem->vatRate = Coerce::float($row->vat_rate);
        $cartItem->taxRate = $cartItem->vatRate;
        $cartItem->tax = $cartItem->formatFloat($cartItem->originalPrice * ($cartItem->taxRate / 100));
        $cartItem->discountValue = Coerce::float($row->discount_value ?? 0.0);

        $associatedModel = Coerce::nullableString($row->associated_model);

        if ($associatedModel !== null && $associatedModel !== '') {
            $cartItem->associate($associatedModel);
        }

        $cartItem->appliedCoupons = $this->appliedCoupons(Coerce::nullableString($row->applied_coupons));

        return $cartItem;
    }

    /**
     * Applied coupons are stored as a JSON object keyed by coupon code; they
     * are restored as the objects CartItem::applyCoupon() creates.
     *
     * @return array<string, object{couponCode: string, couponType: string, couponValue: float, discountValue: float}>
     */
    private function appliedCoupons(?string $json): array
    {
        $coupons = [];

        foreach ($this->decodeArray($json) as $coupon) {
            $coupon = is_array($coupon) ? $coupon : [];
            $couponCode = Coerce::string($coupon['couponCode'] ?? null);

            $coupons[$couponCode] = (object) [
                'couponCode' => $couponCode,
                'couponType' => Coerce::string($coupon['couponType'] ?? null),
                'couponValue' => Coerce::float($coupon['couponValue'] ?? null),
                'discountValue' => Coerce::float($coupon['discountValue'] ?? 0.0),
            ];
        }

        return $coupons;
    }

    private function castQuantity(float $qty): int|float
    {
        return floor($qty) === $qty ? (int) $qty : $qty;
    }

    /**
     * @return Collection<string, CartCoupon>
     */
    private function deserializeCoupons(?string $json): Collection
    {
        return new Collection(CouponData::decodeList($json, isGlobalDefault: false));
    }
}
