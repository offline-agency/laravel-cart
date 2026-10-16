<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use InvalidArgumentException;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;
use OfflineAgency\LaravelCart\Support\CartConfig;
use OfflineAgency\LaravelCart\Support\Coerce;

/**
 * @phpstan-type AppliedCoupon object{couponCode: string, couponType: string, couponValue: float, discountValue: float}
 *
 * @property-read float $priceTax
 * @property-read float $subtotal
 * @property-read float $total
 * @property-read float $taxTotal
 *
 * @implements Arrayable<string, mixed>
 */
class CartItem implements Arrayable, Jsonable
{
    /** @var string */
    public $rowId;

    /** @var int|string */
    public $id;

    /** @var int|float */
    public $qty;

    /** @var string */
    public $name;

    /** @var string */
    public $subtitle;

    /** @var float */
    public $originalPrice;

    /** @var float */
    public $originalTotalPrice;

    /** @var float */
    public $originalVat;

    /** @var float */
    public $price;

    /** @var float */
    public $totalPrice;

    /** @var float */
    public $vat;

    /** @var string */
    public $vatLabel;

    /** @var int|float */
    public $vatRate;

    /** @var string */
    public $vatFcCode;

    /** @var float */
    public $discountValue;

    /** @var string */
    public $productFcCode;

    /** @var string */
    public $urlImg;

    public Carbon $createdAt;

    public Carbon $updatedAt;

    /** @var CartItemOptions */
    public $options;

    /** @var string|null Class name of the associated model. */
    public $associatedModel;

    /** @var mixed The associated model instance, class name or model id. */
    public $model;

    /** @var array<string, AppliedCoupon> */
    public $appliedCoupons;

    /**
     * @deprecated Use $vat instead. Kept for backward compat with __get computed properties.
     *
     * @var int|float
     */
    public $tax = 0.0;

    /**
     * @deprecated Use $vatRate instead. Kept for backward compat with __get computed properties.
     *
     * @var int|float
     */
    public $taxRate = 0.0;

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function __construct(
        int|string $id,
        string $name,
        string $subtitle,
        int|float $qty,
        float $price,
        ?float $totalPrice,
        string $vatFcCode = '',
        string $productFcCode = '',
        ?float $vat = null,
        string $urlImg = '',
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        array $options = []
    ) {
        foreach ([$price, $totalPrice, $vat, $qty] as $amount) {
            if ($amount !== null && ! is_finite((float) $amount)) {
                throw new InvalidArgumentException('Cart amounts and quantities must be finite.');
            }
        }

        if (empty($id)) {
            throw new InvalidArgumentException(__('laravel-cart::cart.invalid_id'));
        }
        if (empty($name)) {
            throw new InvalidArgumentException(__('laravel-cart::cart.invalid_name'));
        }

        $this->rowId = $this->generateRowId((string) $id, $options);
        $this->id = $id;
        $this->qty = $qty;
        $this->name = $name;
        $this->subtitle = $subtitle;
        $this->originalPrice = $price;
        $computedTotalPrice = $totalPrice ?? $this->formatFloat($price + (float) $vat);
        $this->originalTotalPrice = $computedTotalPrice;
        $this->originalVat = (float) $vat;
        $this->price = $price;
        $this->totalPrice = $computedTotalPrice;
        $this->vat = (float) $vat;
        $vatRate = null;

        // Auto-apply default tax rate when no explicit VAT is provided
        if ($this->vat === 0.0 && $this->price > 0 && $vat === null) {
            $defaultRate = CartConfig::float('cart.tax.default_rate', 0.0);
            if ($defaultRate > 0) {
                $behavior = CartConfig::string('cart.tax.tax_behavior', 'inclusive');
                if ($behavior === 'exclusive') {
                    $this->vat = $this->formatFloat($this->price * $defaultRate / 100);
                    $this->totalPrice = $this->formatFloat($this->price + $this->vat);
                    $this->originalTotalPrice = $this->totalPrice;
                    $this->originalVat = $this->vat;
                } else {
                    $this->vat = $this->formatFloat($this->totalPrice * $defaultRate / (100 + $defaultRate));
                    $this->price = $this->formatFloat($this->totalPrice - $this->vat);
                    $this->originalPrice = $this->price;
                    $this->originalVat = $this->vat;
                }
                $vatRate = $defaultRate;
            }
        }

        $this->vatLabel = $this->vat > 0
            ? __('laravel-cart::cart.tax_included')
            : __('laravel-cart::cart.tax_exempt');

        $this->vatRate = $vatRate ?? ($this->price > 0 ? $this->formatFloat(100 * $this->vat / $this->price) : 0);
        $this->taxRate = $this->vatRate;
        $this->tax = $this->formatFloat($this->price * ($this->taxRate / 100));
        $this->vatFcCode = $vatFcCode;
        $this->productFcCode = $productFcCode;
        $this->urlImg = $urlImg;
        $this->createdAt = $createdAt ?? Carbon::now();
        $this->updatedAt = $updatedAt ?? Carbon::now();
        $this->options = new CartItemOptions($options);
        // default values
        $this->discountValue = 0.0;
        $this->associatedModel = null;
        $this->model = null;
        $this->appliedCoupons = [];
    }

    /**
     * Sync taxRate/tax from vatRate on unserialization of old stored carts.
     */
    public function __wakeup(): void
    {
        if ($this->taxRate === 0.0 && $this->vatRate > 0) {
            $this->taxRate = $this->vatRate;
            $this->tax = $this->formatFloat($this->price * ($this->taxRate / 100));
        }
    }

    /**
     * Set the quantity for this cart item.
     */
    public function setQuantity(int|float $qty): void
    {
        if (empty($qty)) {
            throw new InvalidArgumentException(__('laravel-cart::cart.invalid_qty', ['qty' => $qty]));
        }

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     */
    public function updateFromBuyable(Buyable $item): void
    {
        $this->updateFromArray([
            'id' => $item->getId(), 'name' => $item->getName(),
            'price' => $item->getPrice(), 'totalPrice' => $item->getTotalPrice(),
            'vat' => $item->getVat(), 'options' => $item->getOptions(),
        ]);
    }

    /**
     * Update the cart item from an array.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public function updateFromArray(array $attributes): void
    {
        $this->id = array_key_exists('id', $attributes) ? Coerce::key($attributes['id']) : $this->id;
        $this->qty = array_key_exists('qty', $attributes) ? Coerce::number($attributes['qty']) : $this->qty;
        $this->name = array_key_exists('name', $attributes) ? Coerce::string($attributes['name']) : $this->name;
        $this->reprice($attributes);
        $this->options = new CartItemOptions(array_key_exists('options', $attributes) ? self::options($attributes['options']) : $this->options);

        $this->rowId = $this->generateRowId((string) $this->id, $this->options->all());
    }

    /**
     * Update base unit amounts, then reapply retained coupons in insertion order.
     * Price is always net here; a price-only update retains the existing rate.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function reprice(array $attributes): void
    {
        if (! array_intersect(['price', 'vat', 'totalPrice'], array_keys($attributes))) {
            return;
        }

        $net = array_key_exists('price', $attributes) ? Coerce::float($attributes['price']) : $this->originalPrice;
        $vat = array_key_exists('vat', $attributes) ? Coerce::float($attributes['vat']) : $this->formatFloat($net * $this->vatRate / 100);
        $gross = array_key_exists('totalPrice', $attributes) ? Coerce::float($attributes['totalPrice']) : $this->formatFloat($net + $vat);
        if (! is_finite($net) || ! is_finite($vat) || ! is_finite($gross) || $this->formatFloat($net + $vat) !== $this->formatFloat($gross)) {
            throw new InvalidArgumentException('Net price plus VAT must equal totalPrice at cart precision.');
        }
        $coupons = $this->appliedCoupons;
        $this->originalPrice = $this->price = $net;
        $this->originalVat = $this->vat = $vat;
        $this->originalTotalPrice = $this->totalPrice = $gross;
        if (array_key_exists('vat', $attributes)) {
            $this->vatRate = $net != 0.0 ? $this->formatFloat(100 * $vat / $net) : 0.0;
        }
        $this->taxRate = $this->vatRate;
        $this->tax = $vat;
        $this->vatLabel = $vat > 0 ? __('laravel-cart::cart.tax_included') : __('laravel-cart::cart.tax_exempt');
        $this->discountValue = 0.0;
        $this->appliedCoupons = [];
        foreach ($coupons as $coupon) {
            $this->applyCoupon($coupon->couponCode, $coupon->couponType, $coupon->couponValue);
        }
    }

    /**
     * Associate the cart item with a given model (instance or class name), or,
     * with $is_model false, with the `associatedModel` / `modelId` pair of an array.
     *
     * @param  object|string|array<array-key, mixed>  $item
     * @return $this
     */
    public function associate(object|string|array $item, bool $is_model = true): CartItem
    {
        if ($is_model && ! is_array($item)) {
            $this->associatedModel = is_string($item) ? $item : $item::class;
            $this->model = $item;

            return $this;
        }

        $attributes = is_array($item) ? $item : [];
        $associatedModel = $attributes['associatedModel'] ?? null;

        $this->associatedModel = is_string($associatedModel) ? $associatedModel : null;
        $this->model = $attributes['modelId'] ?? null;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     */
    public function __get(string $attribute): mixed
    {
        $properties = get_object_vars($this);

        if ($attribute === 'model' && isset($this->associatedModel)) {
            $associatedModel = $this->associatedModel;

            if (! class_exists($associatedModel)) {
                return null;
            }

            $model = new $associatedModel;

            return method_exists($model, 'find') ? $model->find($this->id) : null;
        }

        if (array_key_exists($attribute, $properties)) {
            return $this->{$attribute};
        }

        if ($attribute === 'priceTax') {
            return $this->totalPrice;
        }

        if ($attribute === 'subtotal') {
            return $this->formatFloat($this->price + ($this->qty - 1) * $this->originalPrice);
        }

        if ($attribute === 'total') {
            return $this->formatFloat($this->totalPrice + ($this->qty - 1) * $this->originalTotalPrice);
        }

        if ($attribute === 'tax') {
            return $this->price * ($this->taxRate / 100);
        }

        if ($attribute === 'taxTotal') {
            return $this->formatFloat($this->vat + ($this->qty - 1) * $this->originalVat);
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     */
    public static function fromBuyable(Buyable $item): CartItem
    {
        return new self(
            Coerce::key($item->getId()),
            $item->getName(),
            $item->getSubtitle(),
            $item->getQty(),
            $item->getPrice(),
            $item->getTotalPrice(),
            $item instanceof FiscalBuyable ? $item->getVatFcCode() : '',
            $item instanceof FiscalBuyable ? $item->getProductFcCode() : '',
            $item->getVat(),
            $item->getUrlImg(),
            Carbon::now(),
            Carbon::now(),
            $item->getOptions()
        );
    }

    /**
     * Create a new instance from the given array.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public static function fromArray(array $attributes): CartItem
    {
        $createdAt = isset($attributes['createdAt']) && $attributes['createdAt'] instanceof Carbon
            ? $attributes['createdAt']
            : Carbon::now();

        $updatedAt = isset($attributes['updatedAt']) && $attributes['updatedAt'] instanceof Carbon
            ? $attributes['updatedAt']
            : Carbon::now();

        return new self(
            Coerce::key($attributes['id'] ?? null),
            Coerce::string($attributes['name'] ?? null),
            Coerce::string($attributes['subtitle'] ?? ''),
            Coerce::number($attributes['qty'] ?? null),
            Coerce::float($attributes['price'] ?? null),
            isset($attributes['totalPrice']) ? Coerce::float($attributes['totalPrice']) : null,
            Coerce::string($attributes['vatFcCode'] ?? ''),
            Coerce::string($attributes['productFcCode'] ?? ''),
            isset($attributes['vat']) ? Coerce::float($attributes['vat']) : null,
            Coerce::string($attributes['urlImg'] ?? ''),
            $createdAt,
            $updatedAt,
            self::options($attributes['options'] ?? [])
        );
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param  array<array-key, mixed>  $options
     */
    public static function fromAttributes(
        int|string $id,
        string $name,
        string $subtitle,
        int|float $qty,
        float $price,
        ?float $totalPrice,
        string $vatFcCode,
        string $productFcCode,
        ?float $vat,
        string $urlImg,
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        array $options = []
    ): CartItem {
        return new self(
            $id,
            $name,
            $subtitle,
            $qty,
            $price,
            $totalPrice,
            $vatFcCode,
            $productFcCode,
            $vat,
            $urlImg,
            $createdAt,
            $updatedAt,
            $options
        );
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param  array<array-key, mixed>  $options
     */
    protected function generateRowId(string $id, array $options): string
    {
        ksort($options);

        return md5($id.serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rowId' => $this->rowId,
            'id' => $this->id,
            'qty' => $this->qty,
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'originalPrice' => $this->originalPrice,
            'originalTotalPrice' => $this->originalTotalPrice,
            'originalVat' => $this->originalVat,
            'price' => $this->price,
            'totalPrice' => $this->totalPrice,
            'vat' => $this->vat,
            'vatLabel' => $this->vatLabel,
            'vatRate' => $this->vatRate,
            'vatFcCode' => $this->vatFcCode,
            'discountValue' => $this->discountValue,
            'productFcCode' => $this->productFcCode,
            'urlImg' => $this->urlImg,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'options' => $this->options->toArray(),
            'associatedModel' => $this->associatedModel,
            'model' => $this->model,
            'appliedCoupons' => $this->appliedCoupons,
        ];
    }

    /**
     * Determine if the cart item represents the given Buyable.
     */
    public function is(Buyable $buyable): bool
    {
        return (string) $this->id === (string) $buyable->getId()
            && $this->associatedModel === get_class($buyable);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the formatted number.
     */
    public function numberFormat(float $value, int $decimals, string $decimalPoint, string $thousandSeparator): string
    {
        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * @return AppliedCoupon|null
     */
    public function getCoupon(string $couponCode): ?object
    {
        return $this->appliedCoupons[$couponCode] ?? null;
    }

    public function applyCoupon(
        string $couponCode,
        string $couponType,
        float $couponValue
    ): CartItem {
        if (! is_finite($couponValue) || $couponValue < 0 || ($couponType === 'percentage' && $couponValue > 100)) {
            throw new InvalidArgumentException('Coupon value must be finite, non-negative, and at most 100 for percentages.');
        }
        if (isset($this->appliedCoupons[$couponCode])) {
            throw new CouponAlreadyAppliedException($couponCode);
        }
        switch ($couponType) {
            case 'fixed':
                $discountValue = $couponValue;

                $this->totalPrice = $this->formatFloat($this->totalPrice - $couponValue);
                $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
                $this->vat = $this->formatFloat($this->totalPrice - $this->price);
                break;
            case 'percentage':
                $discountValue = $this->formatFloat($this->originalTotalPrice * $couponValue / 100);

                $this->totalPrice = $this->formatFloat($this->totalPrice - $discountValue);
                $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
                $this->vat = $this->formatFloat($this->totalPrice - $this->price);
                break;
            case 'global':
                $discountValue = $couponValue;

                $price = $this->formatFloat($couponValue * 100 / (100 + $this->vatRate));
                $vat = $this->formatFloat($price * $this->vatRate / 100);

                $this->totalPrice = $this->formatFloat($couponValue * -1);
                $this->price = $this->formatFloat($price * -1);
                $this->vat = $this->formatFloat($vat * -1);
                break;
            default:
                throw new InvalidArgumentException('Coupon type not handled. Possible values: fixed and percentage');
        }

        $this->discountValue = $this->formatFloat($this->discountValue + $discountValue);

        $this->appliedCoupons[$couponCode] = (object) [
            'couponCode' => $couponCode,
            'couponType' => $couponType,
            'couponValue' => $couponValue,
            'discountValue' => $discountValue,
        ];

        return $this;
    }

    /**
     * @return $this
     */
    public function detachCoupon(
        string $couponCode
    ): CartItem {
        $discountValue = $this->appliedCoupons[$couponCode]->discountValue;

        unset($this->appliedCoupons[$couponCode]);

        $this->totalPrice = $this->formatFloat($this->totalPrice + $discountValue);
        $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
        $this->vat = $this->formatFloat($this->totalPrice - $this->price);

        $this->discountValue = $this->formatFloat($this->discountValue - $discountValue);

        return $this;
    }

    public function hasCoupons(): bool
    {
        return count($this->appliedCoupons) > 0;
    }

    public function formatFloat(float $value): float
    {
        return CartConfig::round($value);
    }

    /**
     * Options given as an array (or an existing CartItemOptions collection).
     *
     * @return array<array-key, mixed>
     */
    private static function options(mixed $options): array
    {
        if ($options instanceof CartItemOptions) {
            return $options->all();
        }

        if (! is_array($options)) {
            throw new InvalidArgumentException('The cart item options must be an array.');
        }

        return $options;
    }
}
