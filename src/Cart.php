<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Traits\Macroable;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemRemoved;
use OfflineAgency\LaravelCart\Events\CartItemUpdated;
use OfflineAgency\LaravelCart\Events\CartRestored;
use OfflineAgency\LaravelCart\Events\CartStored;
use OfflineAgency\LaravelCart\Events\CouponApplied;
use OfflineAgency\LaravelCart\Events\CouponRemoved as CouponRemovedEvent;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Exceptions\CartStorageException;
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;
use OfflineAgency\LaravelCart\Exceptions\CouponNotFoundException;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponHashException;
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;
use OfflineAgency\LaravelCart\Support\CartConfig;
use OfflineAgency\LaravelCart\Support\CartContentCodec;
use OfflineAgency\LaravelCart\Support\Coerce;
use OfflineAgency\LaravelCart\Support\CouponData;

/**
 * @phpstan-import-type AppliedCoupon from CartItem
 *
 * @phpstan-type CouponSummary object{rowId: string, couponCode: string, couponType: string, couponValue: float}
 *
 * @property-read float $total
 * @property-read float $tax
 * @property-read float $subtotal
 */
class Cart
{
    use Macroable;

    const DEFAULT_INSTANCE = 'default';

    const CART_OPTIONS_KEY = 'options';

    private CartStorage $storage;

    private Dispatcher $events;

    private string $instance;

    /** @var Collection<string, CartCoupon> */
    private Collection $globalCoupons;

    public function __construct(CartStorage $storage, Dispatcher $events)
    {
        $this->storage = $storage;
        $this->events = $events;
        $this->globalCoupons = new Collection;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    public function instance(?string $instance = null): Cart
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        $this->globalCoupons = $this->storedGlobalCoupons();

        return $this;
    }

    public function currentInstance(): string
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item: a Buyable (with the quantity as second argument), an
     * attributes array, a list of Buyables / attributes arrays, or the
     * individual attributes.
     *
     * @param  array<array-key, mixed>  $options
     * @return ($id is array ? array<array-key, CartItem>|CartItem : CartItem)
     */
    public function add(
        mixed $id,
        mixed $name = null,
        ?string $subtitle = null,
        ?int $qty = null,
        ?float $price = null,
        ?float $totalPrice = null,
        ?float $vat = null,
        ?string $vatFcCode = '',
        ?string $productFcCode = '',
        ?string $urlImg = '',
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        array $options = []
    ): array|CartItem {
        if (is_array($id) && $this->isMulti($id)) {
            return array_map(fn (mixed $item): CartItem => $this->addItem($item), $id);
        }

        return $this->addItem(
            $id, $name, $subtitle, $qty, $price, $totalPrice, $vat,
            $vatFcCode, $productFcCode, $urlImg, $createdAt, $updatedAt, $options
        );
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private function addItem(
        mixed $id,
        mixed $name = null,
        ?string $subtitle = null,
        ?int $qty = null,
        ?float $price = null,
        ?float $totalPrice = null,
        ?float $vat = null,
        ?string $vatFcCode = '',
        ?string $productFcCode = '',
        ?string $urlImg = '',
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        array $options = []
    ): CartItem {
        $createdAt ??= Carbon::now();
        $updatedAt ??= Carbon::now();

        $cartItem = $this->createCartItem(
            $id, $name, $subtitle, $qty, $price, $totalPrice,
            $vatFcCode, $productFcCode, $vat, $urlImg, $createdAt, $updatedAt, $options
        );

        // Validate a candidate copy so a rejected add leaves the stored content untouched.
        $content = $this->getContent()->collect();

        $existing = $content->get($cartItem->rowId);

        if ($existing !== null) {
            $cartItem->qty += $existing->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        $this->enforceCartLimits($content, $cartItem);

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.added', $cartItem);
        }
        $this->events->dispatch(new CartItemAdded($cartItem));

        $this->storage->put($this->instance, $content);

        return $cartItem;
    }

    /**
     * Update an item's quantity, or its attributes from a Buyable or an attributes array.
     *
     * @param  Buyable|array<array-key, mixed>|int|float|numeric-string  $qty
     */
    public function update(string $rowId, mixed $qty): ?CartItem
    {
        // Work on candidate copies: the stored item and collection are only
        // changed once the limits have accepted the update.
        $storedItem = $this->get($rowId);
        $cartItem = clone $storedItem;

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = Coerce::number($qty);
        }

        $content = $this->getContent()->collect();

        if ($rowId !== $cartItem->rowId) {
            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);

            return null;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        if ($cartItem->qty > 0) {
            $this->enforceCartLimits($content, $cartItem);
        }

        // Commit: carry the accepted state onto the stored instance so existing
        // references to the item observe the update, as before.
        foreach (get_object_vars($cartItem) as $property => $value) {
            $storedItem->{$property} = $value;
        }
        $cartItem = $storedItem;
        $content->put($cartItem->rowId, $cartItem);

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.updated', $cartItem);
        }
        $this->events->dispatch(new CartItemUpdated($cartItem));

        $this->storage->put($this->instance, $content);

        return $cartItem;
    }

    public function remove(string $rowId): void
    {
        $cartItem = $this->get($rowId);

        if (! empty($cartItem->appliedCoupons)) {
            foreach (array_keys($cartItem->appliedCoupons) as $couponCode) {
                $cartItem->detachCoupon($couponCode);
            }
        }

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.removed', $cartItem);
        }
        $this->events->dispatch(new CartItemRemoved($cartItem));

        $this->storage->put($this->instance, $content);
    }

    public function get(string $rowId): CartItem
    {
        $cartItem = $this->getContent()->get($rowId);

        if ($cartItem === null) {
            throw new InvalidRowIDException(__('laravel-cart::cart.invalid_row_id'));
        }

        return $cartItem;
    }

    public function destroy(): void
    {
        $this->setOptions([]);
        $this->storage->remove($this->instance);
        $this->storage->remove($this->instance.'_global_coupons');
        $this->storage->remove($this->instance.'_meta');
        $this->globalCoupons = new Collection;
    }

    /**
     * @return Collection<string, CartItem>
     */
    public function content(): Collection
    {
        return $this->getContent();
    }

    public function count(): int|float
    {
        return $this->getContent()->sum(fn (CartItem $cartItem): int|float => $cartItem->qty);
    }

    /**
     * Returns true when the cart has zero items.
     */
    public function isEmpty(): bool
    {
        return $this->content()->isEmpty();
    }

    /**
     * Returns true when the cart has one or more items.
     */
    public function isNotEmpty(): bool
    {
        return $this->content()->isNotEmpty();
    }

    /**
     * Return the first CartItem matching the optional Closure, or null.
     *
     * @param  (Closure(CartItem, string): bool)|null  $callback
     */
    public function first(?Closure $callback = null): ?CartItem
    {
        return $this->getContent()->first($callback);
    }

    /**
     * Return only CartItems where the given attribute equals the given value.
     *
     * @return Collection<string, CartItem>
     */
    public function where(string $key, mixed $value): Collection
    {
        return $this->getContent()->where($key, $value);
    }

    /**
     * Determine whether an item with the given id and model class is already in the cart.
     *
     * @param  string  $model  Fully-qualified class name of the associated model
     */
    public function isAlreadyAdded(string|int $id, string $model): bool
    {
        return $this->getContent()->contains(function (CartItem $cartItem) use ($id, $model) {
            return (int) $cartItem->id === (int) $id
                && $cartItem->associatedModel === $model;
        });
    }

    /**
     * Find the first CartItem in the cart matching the given id and model class.
     *
     * @param  string  $model  Fully-qualified class name of the associated model
     */
    public function searchById(string|int $id, string $model): ?CartItem
    {
        return $this->getContent()
            ->first(function (CartItem $cartItem) use ($id, $model) {
                return (int) $cartItem->id === (int) $id
                    && $cartItem->associatedModel === $model;
            });
    }

    /**
     * Return the number of unique product rows in the cart (not the quantity sum).
     */
    public function uniqueCount(): int
    {
        return $this->getContent()->count();
    }

    /**
     * Check whether the cart meets the configured minimum total.
     */
    public function meetsMinimum(): bool
    {
        $min = CartConfig::nullableNumber('cart.limits.min_total');

        return $min === null || $this->rawTotal() >= $min;
    }

    /**
     * Get the raw item total before cart-level coupon discounts.
     */
    private function rawTotal(): float
    {
        $total = $this->getContent()->reduce(function (float $total, CartItem $cartItem): float {
            return $total + $cartItem->totalPrice + (float) (($cartItem->qty - 1) * $cartItem->originalTotalPrice);
        }, 0.0);

        return $total < 0 ? 0.0 : $this->formatFloat($total);
    }

    /**
     * Get the total price of the items in the cart after cart-level coupon discounts.
     */
    public function total(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): float
    {
        $raw = $this->rawTotal();
        $discount = (float) $this->globalCouponDiscount((string) $raw);
        $result = $raw - $discount;

        return $result < 0 ? 0.0 : $this->formatFloat($result);
    }

    public function vat(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): float
    {
        $vat = $this->getContent()->reduce(function (float $tax, CartItem $cartItem): float {
            return $tax + $cartItem->vat + (float) (($cartItem->qty - 1) * $cartItem->originalVat);
        }, 0.0);

        return $vat < 0 ? 0 : $this->formatFloat($vat);
    }

    public function subtotal(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): float
    {
        $subtotal = $this->getContent()->reduce(function (float $subTotal, CartItem $cartItem): float {
            $cartItemSubTotal = $cartItem->name !== 'discountCartItem'
                ? $cartItem->price + (float) (($cartItem->qty - 1) * $cartItem->originalPrice)
                : 0;

            return $subTotal + $cartItemSubTotal;
        }, 0.0);

        return $subtotal < 0 ? 0 : $this->formatFloat($subtotal);
    }

    public function originalTotalPrice(?int $decimals = null, ?string $decimalPoint = null, ?string $thousandSeparator = null): float
    {
        return (float) $this->getContent()->reduce(function (float $originalTotalPrice, CartItem $cartItem): float {
            return $originalTotalPrice + ($cartItem->originalTotalPrice * $cartItem->qty);
        }, 0.0);
    }

    /**
     * Get the total discount amount produced by all cart-level coupons against the current subtotal.
     */
    public function discount(): float
    {
        return (float) $this->globalCouponDiscount((string) $this->rawTotal());
    }

    /**
     * Return a Collection grouped by tax rate for generating fiscal receipts.
     *
     * @return Collection<int, array{rate: float, net: string, vat: string, tax: string, gross: string, tax_name: string}>
     */
    public function vatBreakdown(): Collection
    {
        $roundingMode = CartConfig::roundingMode();
        $decimals = CartConfig::decimals();
        $decimalPoint = CartConfig::decimalPoint();
        $thousandSep = CartConfig::thousandSeparator();

        $groups = [];

        foreach ($this->getContent() as $cartItem) {
            if ($cartItem->name === 'discountCartItem') {
                continue;
            }

            $rate = (float) $cartItem->vatRate;
            $key = number_format($rate, 2, '.', '');
            $qty = (float) $cartItem->qty;
            $itemNet = $cartItem->price + ($qty - 1) * $cartItem->originalPrice;
            $itemVat = $cartItem->vat + ($qty - 1) * $cartItem->originalVat;

            if (! isset($groups[$key])) {
                $groups[$key] = ['rate' => $rate, 'net' => 0.0, 'vat' => 0.0];
            }

            $groups[$key]['net'] += $itemNet;
            $groups[$key]['vat'] += $itemVat;
        }

        return collect(array_values($groups))->map(function (array $group) use ($roundingMode, $decimals, $decimalPoint, $thousandSep): array {
            $net = round($group['net'], $decimals, $roundingMode);
            $vat = round($group['vat'], $decimals, $roundingMode);
            $gross = round($net + $vat, $decimals, $roundingMode);

            return [
                'rate' => $group['rate'],
                'net' => number_format($net, $decimals, $decimalPoint, $thousandSep),
                'vat' => number_format($vat, $decimals, $decimalPoint, $thousandSep),
                'tax' => number_format($vat, $decimals, $decimalPoint, $thousandSep),
                'gross' => number_format($gross, $decimals, $decimalPoint, $thousandSep),
                'tax_name' => $this->taxName(),
            ];
        });
    }

    /**
     * @param  Closure(CartItem, string): bool  $search
     * @return Collection<string, CartItem>
     */
    public function search(Closure $search): Collection
    {
        return $this->getContent()->filter($search);
    }

    /**
     * @param  object|string  $model  A model instance or class name
     */
    public function associate(string $rowId, object|string $model): void
    {
        if (is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException(__('laravel-cart::cart.unknown_model', ['model' => $model]));
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->storage->put($this->instance, $content);
    }

    public function store(int|string $identifier, bool $overwrite = true): void
    {
        $content = $this->getContent();
        $exists = $this->storedCart($identifier, $this->currentInstance())->exists();

        if ($exists && ! $overwrite) {
            throw new CartAlreadyStoredException(__('laravel-cart::cart.cart_already_stored', ['identifier' => $identifier]));
        }

        $serializedContent = CartContentCodec::encode($content);
        $couponsJson = $this->globalCoupons->isNotEmpty()
            ? json_encode($this->globalCoupons->map(fn (CartCoupon $c) => $c->toArray())->values()->all(), JSON_THROW_ON_ERROR)
            : null;
        $meta = $this->meta();
        $metaJson = ! empty($meta) ? json_encode($meta, JSON_THROW_ON_ERROR) : null;

        $data = [
            'content' => $serializedContent,
            'coupons' => $couponsJson,
            'updated_at' => now(),
        ];

        // Include meta if the column exists (requires 0004 migration)
        if ($this->hasMetaColumn()) {
            $data['meta'] = $metaJson;
        }

        if ($exists) {
            $this->storedCart($identifier, $this->currentInstance())->update($data);
        } else {
            $this->getConnection()->table($this->getTableName())->insert(array_merge([
                'identifier' => $identifier,
                'instance' => $this->currentInstance(),
                'created_at' => now(),
            ], $data));
        }

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.stored');
        }
        $this->events->dispatch(new CartStored($identifier, $this->currentInstance()));
    }

    /**
     * @param  bool  $mergeOnRestore  When true the restored cart is merged with the current session
     *                                cart (union by rowId) instead of replacing it.
     */
    public function restore(int|string $identifier, bool $mergeOnRestore = false, bool $keepStored = false): void
    {
        // Prefer the row stored for the current instance; fall back to any
        // instance stored under the identifier (single-instance behaviour).
        $stored = $this->storedCart($identifier, $this->currentInstance())->first()
            ?? $this->storedCart($identifier)->first();

        if ($stored === null) {
            return;
        }

        $storedContent = CartContentCodec::decode(Coerce::nullableString($stored->content));

        if ($storedContent === null) {
            throw new CartStorageException(__('laravel-cart::cart.stored_cart_undecodable', ['identifier' => $identifier]));
        }

        $currentInstance = $this->currentInstance();

        $this->instance(Coerce::string($stored->instance));

        $content = $mergeOnRestore ? $this->getContent() : new Collection;

        foreach ($storedContent as $cartItem) {
            if ($mergeOnRestore && $content->has($cartItem->rowId)) {
                continue;
            }
            $content->put($cartItem->rowId, $cartItem);
        }

        $storedCoupons = CouponData::decodeList(Coerce::nullableString($stored->coupons ?? null), isGlobalDefault: true);

        if ($storedCoupons !== []) {
            foreach ($storedCoupons as $coupon) {
                $this->globalCoupons->put($coupon->hash, $coupon);
            }

            $this->persistGlobalCoupons();
        }

        // Restore metadata if available
        $storedMeta = Coerce::nullableString($stored->meta ?? null);
        if ($storedMeta !== null && $storedMeta !== '') {
            $restoredMeta = json_decode($storedMeta, true);
            if (is_array($restoredMeta)) {
                $this->storage->put($this->instance.'_meta', $restoredMeta);
            }
        }

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.restored');
        }
        $this->events->dispatch(new CartRestored($identifier, $this->currentInstance()));

        $this->storage->put($this->instance, $content);

        $this->instance($currentInstance);

        if (! $keepStored) {
            $this->storedCart($identifier, Coerce::string($stored->instance))->delete();
        }
    }

    public function __get(string $attribute): ?float
    {
        if ($attribute === 'total') {
            return $this->total();
        }

        if ($attribute === 'tax') {
            return $this->vat();
        }

        if ($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

    public function totalVatLabel(): string
    {
        return $this->vat() > 0
            ? __('laravel-cart::cart.tax_included')
            : __('laravel-cart::cart.tax_exempt');
    }

    public function taxName(): string
    {
        return __('laravel-cart::cart.tax_name');
    }

    // ── Serialization ──────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'instance' => $this->currentInstance(),
            'items' => $this->getContent()->map(fn (CartItem $item) => $item->toArray())->values()->all(),
            'count' => $this->count(),
            'uniqueCount' => $this->uniqueCount(),
            'subtotal' => $this->subtotal(),
            'vat' => $this->vat(),
            'total' => $this->total(),
            'discount' => $this->discount(),
            'coupons' => $this->listCoupons()->values()->all(),
            'meta' => $this->meta(),
            'isEmpty' => $this->isEmpty(),
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * Sum a given attribute across all items in the cart.
     */
    public function sum(string $attribute): float
    {
        return Coerce::float($this->getContent()->sum($attribute));
    }

    /**
     * Calculate the total weight of all items in the cart (the `weight` option times the quantity).
     */
    public function weight(): float
    {
        return $this->getContent()->reduce(function (float $total, CartItem $item): float {
            $itemWeight = Coerce::float($item->options->get('weight') ?? 0);

            return $total + ($itemWeight * $item->qty);
        }, 0.0);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'timestamp' => now()->toIso8601String(),
            ...$this->toArray(),
        ];
    }

    // ── Metadata ───────────────────────────────────────────────────────────────

    public function setMeta(string $key, mixed $value): static
    {
        $meta = $this->meta();
        $meta[$key] = $value;
        $this->storage->put($this->instance.'_meta', $meta);

        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta(), $key, $default);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function meta(): array
    {
        $meta = $this->storage->get($this->instance.'_meta', []);

        return is_array($meta) ? $meta : [];
    }

    public function forgetMeta(string $key): static
    {
        $meta = $this->meta();
        unset($meta[$key]);
        $this->storage->put($this->instance.'_meta', $meta);

        return $this;
    }

    /**
     * The items of the current instance, keyed by row id.
     *
     * @return Collection<string, CartItem>
     *
     * @throws CartStorageException When the storage holds something other than cart items.
     */
    protected function getContent(): Collection
    {
        $content = $this->storage->get($this->instance);

        if ($content === null) {
            return new Collection;
        }

        $items = $content instanceof Collection ? CartContentCodec::cartItems($content) : null;

        if ($items === null) {
            throw new CartStorageException(sprintf('The cart storage key [%s] does not hold a collection of cart items.', $this->instance));
        }

        return $items;
    }

    /**
     * The cart-level options of the current instance.
     *
     * @return Collection<array-key, mixed>
     */
    protected function getCartInfo(): Collection
    {
        $info = $this->storage->get($this->getCartInstance());

        // The session driver stores a Collection, the database driver returns the decoded array.
        return new Collection(is_array($info) || $info instanceof Collection ? $info : []);
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    private function createCartItem(
        mixed $id,
        mixed $name,
        ?string $subtitle,
        ?int $qty,
        ?float $price,
        ?float $totalPrice,
        ?string $vatFcCode,
        ?string $productFcCode,
        ?float $vat,
        ?string $urlImg,
        Carbon $createdAt,
        Carbon $updatedAt,
        array $options
    ): CartItem {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id);
            $cartItem->setQuantity(Coerce::number($name ?: 1));
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($cartItem->qty);
        } else {
            if (! is_numeric($price)) {
                throw new \InvalidArgumentException(__('laravel-cart::cart.invalid_price'));
            }
            if (! is_int($id) && ! is_string($id)) {
                throw new \InvalidArgumentException(__('laravel-cart::cart.invalid_id'));
            }
            // A missing quantity is rejected by setQuantity() below.
            $cartItem = CartItem::fromAttributes(
                $id, Coerce::string($name), $subtitle ?? '', $qty ?? 0, $price, $totalPrice,
                $vatFcCode ?? '', $productFcCode ?? '', $vat, $urlImg ?? '', $createdAt, $updatedAt, $options
            );
            $cartItem->setQuantity($qty ?? 0);
        }

        return $cartItem;
    }

    /**
     * @param  array<array-key, mixed>  $item
     */
    private function isMulti(array $item): bool
    {
        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    private function storedCart(int|string $identifier, ?string $instance = null): Builder
    {
        return $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)
            ->when($instance !== null, fn (Builder $query) => $query->where('instance', $instance));
    }

    private function getConnection(): Connection
    {
        return app(DatabaseManager::class)->connection($this->getConnectionName());
    }

    private function getTableName(): string
    {
        return CartConfig::string('cart.database.table', 'cart');
    }

    private function getConnectionName(): string
    {
        return CartConfig::connection() ?? app(DatabaseManager::class)->getDefaultConnection();
    }

    private function hasMetaColumn(): bool
    {
        try {
            return Schema::connection($this->getConnectionName())
                ->hasColumn($this->getTableName(), 'meta');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<array-key, array{id: mixed, name?: mixed, subtitle?: string|null, qty?: int|null, price?: float|null, totalPrice?: float|null, vat?: float|null}>  $items
     * @return Collection<string, CartItem>
     */
    public function addBatch(array $items): Collection
    {
        foreach ($items as $item) {
            $this->addItem(
                $item['id'],
                $item['name'] ?? null,
                $item['subtitle'] ?? null,
                $item['qty'] ?? null,
                $item['price'] ?? null,
                $item['totalPrice'] ?? null,
                $item['vat'] ?? null
            );
        }

        return $this->content();
    }

    public function numberFormat(float|int $value, ?int $decimals, ?string $decimalPoint, ?string $thousandSeparator): string
    {
        return number_format(
            (float) $value,
            $decimals ?? CartConfig::decimals(),
            $decimalPoint ?? CartConfig::decimalPoint(),
            $thousandSeparator ?? CartConfig::thousandSeparator(),
        );
    }

    // -------------------------------------------------------------------------
    // Item-level coupon API
    // -------------------------------------------------------------------------

    /**
     * The item-level coupons applied in the cart, keyed by coupon code.
     *
     * @return array<string, CouponSummary>
     */
    public function coupons(): array
    {
        $coupons = [];

        foreach ($this->getContent() as $cartItem) {
            foreach ($cartItem->appliedCoupons as $coupon) {
                $coupons[$coupon->couponCode] = (object) [
                    'rowId' => $cartItem->rowId,
                    'couponCode' => $coupon->couponCode,
                    'couponType' => $coupon->couponType,
                    'couponValue' => $coupon->couponValue,
                ];
            }
        }

        return $coupons;
    }

    /**
     * @return Collection<string, CartCoupon>
     */
    public function getCoupons(): Collection
    {
        return collect($this->coupons())->map(fn (object $c): CartCoupon => new CartCoupon(
            hash: $c->couponCode,
            code: $c->couponCode,
            type: $c->couponType,
            value: $c->couponValue,
        ));
    }

    public function hasCoupon(string $couponCode): bool
    {
        return array_key_exists($couponCode, $this->coupons());
    }

    /**
     * Apply a coupon to a specific cart item (item-level coupon).
     *
     * @deprecated Use addItemCoupon() for item-level coupons or addCoupon() for cart-level coupons.
     */
    #[\Deprecated('Use addItemCoupon() for item-level coupons or addCoupon() for cart-level coupons.', since: '4.0')]
    public function applyCoupon(
        ?string $rowId,
        string $couponCode,
        string $couponType,
        float $couponValue
    ): void {
        $this->addItemCoupon($rowId, $couponCode, $couponType, $couponValue);
    }

    /**
     * Apply a coupon to a specific cart item (item-level coupon).
     * Non-deprecated equivalent of the legacy applyCoupon() method.
     * A null row id applies it as a legacy global discount item.
     */
    public function addItemCoupon(
        ?string $rowId,
        string $couponCode,
        string $couponType,
        float $couponValue
    ): void {
        if (! is_null($rowId)) {
            $cartItem = $this->get($rowId);

            $cartItem->applyCoupon($couponCode, $couponType, $couponValue);

            $content = $this->getContent();

            $content->put($cartItem->rowId, $cartItem);

            $this->storage->put($this->instance, $content);
        } else {
            $this->applyGlobalCoupon($couponCode, $couponType, $couponValue);
        }
    }

    public function detachCoupon(string $rowId, string $couponCode): void
    {
        $cartItem = $this->get($rowId);

        $cartItem->detachCoupon($couponCode);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->storage->put($this->instance, $content);

        if ($cartItem->name === 'discountCartItem') {
            $this->remove($rowId);
        }
    }

    public function hasCoupons(): bool
    {
        return count($this->coupons()) > 0;
    }

    public function hasGlobalCoupon(): bool
    {
        foreach ($this->coupons() as $coupon) {
            if ($coupon->couponType === 'global') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return CouponSummary|null
     */
    public function getCoupon(string $couponCode): mixed
    {
        return $this->coupons()[$couponCode] ?? null;
    }

    /**
     * Remove a specific per-item coupon by coupon code.
     *
     * @throws InvalidCouponHashException
     */
    public function removeCoupon(string $couponCode): static
    {
        $content = $this->getContent();
        $found = false;

        foreach ($content as $cartItem) {
            if (isset($cartItem->appliedCoupons[$couponCode])) {
                $this->detachCoupon($cartItem->rowId, $couponCode);
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new InvalidCouponHashException(
                __('laravel-cart::cart.coupon_not_found', ['code' => $couponCode])
            );
        }

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.coupon_removed', $couponCode);
        }

        return $this;
    }

    public function removeAllCoupons(): static
    {
        $content = $this->getContent();

        foreach ($content as $cartItem) {
            if ($cartItem->hasCoupons()) {
                foreach (array_keys($cartItem->appliedCoupons) as $code) {
                    $cartItem->detachCoupon($code);
                }
                $content->put($cartItem->rowId, $cartItem);
            }
        }

        $this->storage->put($this->instance, $content);

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.coupons_cleared');
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Cart-level coupon API
    // -------------------------------------------------------------------------

    /**
     * Attach a coupon to the current cart instance (cart-level, not item-level).
     *
     *
     * @throws CouponAlreadyAppliedException If the same hash is already present.
     * @throws InvalidCouponException If validation fails (expired, min cart amount not met).
     */
    public function addCoupon(string|CartCoupon|Couponable $coupon): static
    {
        if (is_string($coupon)) {
            $coupon = new CartCoupon(
                hash: md5($coupon),
                code: $coupon,
                type: 'fixed',
                value: 0.0,
                isGlobal: true,
            );
        } elseif (! $coupon instanceof CartCoupon) {
            $coupon = new CartCoupon(
                hash: $coupon->getHash(),
                code: $coupon->getCode(),
                type: $coupon->getCouponType()->value,
                value: $coupon->getCouponValue(),
                isGlobal: true,
                expiresAt: $coupon->getCouponExpiresAt(),
                usageLimit: $coupon->getCouponUsageLimit(),
                minCartAmount: $coupon->getMinCartAmount(),
            );
        }

        if ($this->globalCoupons->has($coupon->hash)) {
            throw new CouponAlreadyAppliedException($coupon->code);
        }

        if ($coupon->isExpired()) {
            throw new InvalidCouponException(
                $coupon->code,
                __('laravel-cart::cart.coupon_expired', ['code' => $coupon->code])
            );
        }

        $currentTotal = $this->rawTotal();
        if (! $coupon->isApplicableTo($currentTotal)) {
            throw new InvalidCouponException(
                $coupon->code,
                __('laravel-cart::cart.coupon_min_amount', ['code' => $coupon->code, 'amount' => $coupon->minCartAmount])
            );
        }

        $this->globalCoupons->put($coupon->hash, $coupon);
        $this->persistGlobalCoupons();

        $this->events->dispatch(new CouponApplied($coupon, $this->currentInstance()));

        return $this;
    }

    /**
     * Remove a cart-level coupon by its hash or code.
     *
     *
     * @throws CouponNotFoundException If the coupon is not in the cart.
     */
    public function removeCartCoupon(string $hashOrCode): static
    {
        $coupon = $this->findCartCoupon($hashOrCode);

        if ($coupon === null) {
            throw new CouponNotFoundException($hashOrCode);
        }

        $this->globalCoupons->forget($coupon->hash);
        $this->persistGlobalCoupons();

        $this->events->dispatch(new CouponRemovedEvent($coupon, $this->currentInstance()));

        return $this;
    }

    /**
     * Return all cart-level coupons as a Collection of CartCoupon objects.
     *
     * @return Collection<string, CartCoupon>
     */
    public function listCoupons(): Collection
    {
        return $this->globalCoupons;
    }

    /**
     * Return true if a cart-level coupon with the given hash or code is present.
     */
    public function hasCartCoupon(string $hashOrCode): bool
    {
        return $this->findCartCoupon($hashOrCode) !== null;
    }

    /**
     * Synchronise applied cart-level coupons: re-validate and silently remove invalid ones.
     *
     * @return array<int, string> Coupon codes of removed coupons.
     */
    public function syncCoupons(): array
    {
        $removed = [];
        $currentTotal = $this->rawTotal();

        foreach ($this->globalCoupons as $coupon) {
            if ($coupon->isExpired() || ! $coupon->isApplicableTo($currentTotal)) {
                $this->globalCoupons->forget($coupon->hash);
                $removed[] = $coupon->code;
            }
        }

        if ($removed !== []) {
            $this->persistGlobalCoupons();
        }

        return $removed;
    }

    private function findCartCoupon(string $hashOrCode): ?CartCoupon
    {
        return $this->globalCoupons->get($hashOrCode)
            ?? $this->globalCoupons->first(fn (CartCoupon $c): bool => $c->code === $hashOrCode);
    }

    // -------------------------------------------------------------------------
    // Sync
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array{id: mixed, name: string, qty: int, price: float, subtitle?: string, totalPrice?: float, vat?: float, vatFcCode?: string, productFcCode?: string, urlImg?: string, options?: array<string, mixed>}>  $items
     */
    public function sync(array $items): static
    {
        $currentRowIds = $this->content()->keys()->all();
        $newIds = array_column($items, 'id');

        foreach ($currentRowIds as $rowId) {
            $cartItem = $this->get($rowId);
            if (! in_array($cartItem->id, $newIds, strict: true)) {
                $this->remove($rowId);
            }
        }

        foreach ($items as $item) {
            $existing = $this->search(fn (CartItem $c): bool => $c->id === $item['id'])->first();
            if ($existing !== null) {
                $this->update($existing->rowId, $item['qty']);
            } else {
                $this->addItem(
                    $item['id'],
                    $item['name'],
                    $item['subtitle'] ?? '',
                    $item['qty'],
                    $item['price'],
                    $item['totalPrice'] ?? $item['price'],
                    $item['vat'] ?? 0.0,
                    $item['vatFcCode'] ?? '',
                    $item['productFcCode'] ?? '',
                    $item['urlImg'] ?? '',
                    null,
                    null,
                    $item['options'] ?? []
                );
            }
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Legacy global coupon API
    // -------------------------------------------------------------------------

    /**
     * @param  'percentage'|'fixed'  $type
     */
    public function addGlobalCoupon(
        string $couponHash,
        string $code,
        string $type,
        float|int $value,
    ): static {
        $coupon = new CartCoupon(
            hash: $couponHash,
            code: $code,
            type: $type,
            value: (float) $value,
            isGlobal: true,
        );

        $this->globalCoupons->put($couponHash, $coupon);
        $this->persistGlobalCoupons();

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.global_coupon_added', $coupon);
        }

        return $this;
    }

    /**
     * @throws InvalidCouponHashException
     */
    public function removeGlobalCoupon(string $couponHash): static
    {
        $coupon = $this->globalCoupons->get($couponHash);

        if ($coupon === null) {
            throw new InvalidCouponHashException(__('laravel-cart::cart.coupon_not_found', ['code' => $couponHash]));
        }

        $this->globalCoupons->forget($couponHash);
        $this->persistGlobalCoupons();

        if (config('cart.use_legacy_events', true)) {
            $this->events->dispatch('cart.global_coupon_removed', $coupon);
        }

        return $this;
    }

    /**
     * @return Collection<string, CartCoupon>
     */
    public function getGlobalCoupons(): Collection
    {
        return $this->globalCoupons;
    }

    /**
     * @param  numeric-string  $total
     */
    public function globalCouponDiscount(string $total): string
    {
        $remaining = $total;
        $totalDiscount = '0';

        $sorted = $this->globalCoupons->sortBy(fn (CartCoupon $c): int => $c->isPercentage() ? 0 : 1);

        foreach ($sorted as $coupon) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            if ($coupon->isPercentage()) {
                $discount = bcdiv(bcmul($remaining, (string) $coupon->value, 4), '100', 4);
            } else {
                $discount = bccomp((string) $coupon->value, $remaining, 4) > 0
                    ? $remaining
                    : (string) $coupon->value;
            }

            $totalDiscount = bcadd($totalDiscount, $discount, 4);
            $remaining = bcsub($remaining, $discount, 4);
        }

        return number_format((float) $totalDiscount, 2, '.', '');
    }

    /**
     * @deprecated Use addGlobalCoupon() instead.
     */
    #[\Deprecated('Use addGlobalCoupon() instead', since: '4.0')]
    public function applyGlobalCoupon(
        string $couponCode,
        string $couponType,
        float $couponValue
    ): void {
        $discount_value = 0.0;
        $originalTotalPrice = $this->originalTotalPrice();
        switch ($couponType) {
            case 'fixed':
                $discount_value = $couponValue;
                break;
            case 'percentage':
                $discount_value = $this->formatFloat($originalTotalPrice * $couponValue / 100);
                break;
        }

        $cartItem = $this->addItem(
            md5((string) Carbon::now()),
            'discountCartItem',
            '',
            1,
            0,
            0,
            0
        );

        $cartItem->applyCoupon(
            $couponCode,
            'global',
            $discount_value
        );

        $content = $this->getContent();
        $content->put($cartItem->rowId, $cartItem);
        $this->storage->put($this->instance, $content);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        $options = $this->getCartInfo()->get(self::CART_OPTIONS_KEY, []);

        return is_array($options) ? $options : [];
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $content = $this->getCartInfo();

        $content->put(self::CART_OPTIONS_KEY, $options);

        $this->storage->put($this->getCartInstance(), $content);
    }

    public function getOptionsByKey(int|string $key, mixed $default_value = null): mixed
    {
        $options = $this->getOptions();

        return Arr::has($options, [$key]) ? Arr::get($options, $key) : $default_value;
    }

    private function getCartInstance(): string
    {
        return $this->instance.'_cart_info';
    }

    /**
     * The cart-level coupons persisted for the current instance, keyed by hash.
     *
     * @return Collection<string, CartCoupon>
     *
     * @throws CartStorageException When the storage holds something other than cart coupons.
     */
    private function storedGlobalCoupons(): Collection
    {
        $key = $this->instance.'_global_coupons';
        $stored = $this->storage->get($key) ?? new Collection;
        $coupons = [];

        foreach ($stored instanceof Collection ? $stored : [$stored] as $coupon) {
            if (! $coupon instanceof CartCoupon) {
                throw new CartStorageException(sprintf('The cart storage key [%s] does not hold a collection of cart coupons.', $key));
            }

            $coupons[$coupon->hash] = $coupon;
        }

        return new Collection($coupons);
    }

    /**
     * Enforce configured cart limits before persisting a change.
     *
     * @param  Collection<string, CartItem>  $content  The cart content including the new/updated item.
     * @param  CartItem  $cartItem  The item being added or updated.
     *
     * @throws CartLimitExceededException
     */
    private function enforceCartLimits(Collection $content, CartItem $cartItem): void
    {
        $maxQty = CartConfig::nullableNumber('cart.limits.max_qty_per_item');
        if ($maxQty !== null && $cartItem->qty > (int) $maxQty) {
            throw new CartLimitExceededException(
                __('laravel-cart::cart.limit_max_qty'),
                (int) $maxQty,
                $cartItem->qty,
            );
        }

        $maxItems = CartConfig::nullableNumber('cart.limits.max_items');
        if ($maxItems !== null && $content->count() > (int) $maxItems) {
            throw new CartLimitExceededException(
                __('laravel-cart::cart.limit_max_items'),
                (int) $maxItems,
                $content->count(),
            );
        }

        $maxTotal = CartConfig::nullableNumber('cart.limits.max_total');
        if ($maxTotal !== null) {
            $total = $content->reduce(function (float $total, CartItem $item): float {
                return $total + $item->totalPrice + (float) (($item->qty - 1) * $item->originalTotalPrice);
            }, 0.0);

            if ($total > (float) $maxTotal) {
                throw new CartLimitExceededException(
                    __('laravel-cart::cart.limit_max_total'),
                    (float) $maxTotal,
                    $total,
                );
            }
        }
    }

    private function formatFloat(float $value): float
    {
        return CartConfig::round($value);
    }

    private function persistGlobalCoupons(): void
    {
        $this->storage->put($this->instance.'_global_coupons', $this->globalCoupons);
    }
}
