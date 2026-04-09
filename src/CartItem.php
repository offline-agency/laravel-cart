<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;

/**
 * @property-read mixed $model
 * @property-read float $priceTax
 * @property-read float $subtotal
 * @property-read float $total
 * @property-read float $tax
 * @property-read float $taxTotal
 */
class CartItem implements Arrayable, Jsonable
{
    public $rowId;

    public $id;

    public $qty;

    public $name;

    public $subtitle;

    public $originalPrice;

    public $originalTotalPrice;

    public $originalVat;

    public $price;

    public $totalPrice;

    public $vat;

    public $vatLabel;

    public $vatRate;

    public $vatFcCode;

    public $discountValue;

    public $productFcCode;

    public $urlImg;

    public Carbon $createdAt;

    public Carbon $updatedAt;

    public $options;

    public $associatedModel;

    public $model;

    public $appliedCoupons;

    /** @deprecated Use $vat instead. Kept for backward compat with __get computed properties. */
    public $tax = 0.0;

    /** @deprecated Use $vatRate instead. Kept for backward compat with __get computed properties. */
    public $taxRate = 0.0;

    /**
     * CartItem constructor.
     *
     * @param  int|string  $id
     * @param  Carbon|null  $createdAt
     * @param  Carbon|null  $updatedAt
     */
    public function __construct(
        $id,
        string $name,
        string $subtitle,
        $qty,
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
        $this->originalPrice = floatval($price);
        $computedTotalPrice = $totalPrice !== null ? floatval($totalPrice) : $this->formatFloat(floatval($price) + floatval($vat));
        $this->originalTotalPrice = $computedTotalPrice;
        $this->originalVat = floatval($vat);
        $this->price = floatval($price);
        $this->totalPrice = $computedTotalPrice;
        $this->vat = floatval($vat);

        // Auto-apply default tax rate when no explicit VAT is provided
        if ($this->vat === 0.0 && $this->price > 0 && $vat === null) {
            $defaultRate = (float) config('cart.tax.default_rate', 0.0);
            if ($defaultRate > 0) {
                $behavior = (string) config('cart.tax.tax_behavior', 'inclusive');
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
                $this->vatRate = $defaultRate;
                $this->taxRate = $defaultRate;
            }
        }

        $this->vatLabel = $this->vat > 0
            ? __('laravel-cart::cart.tax_included')
            : __('laravel-cart::cart.tax_exempt');

        if (! isset($this->vatRate)) {
            $this->vatRate = $this->price > 0 ? $this->formatFloat(100 * $this->vat / $this->price) : 0;
        }
        if (! isset($this->taxRate) || $this->taxRate === 0.0) {
            $this->taxRate = $this->vatRate;
        }
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
     *
     * @param  int|float  $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty)) {
            throw new InvalidArgumentException(__('laravel-cart::cart.invalid_qty', ['qty' => $qty]));
        }

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @return void
     */
    public function updateFromBuyable(Buyable $item)
    {
        $this->id = $item->getId();
        $this->name = $item->getName();
        $this->price = $item->getPrice();
        $this->vat = $item->getVat();
    }

    /**
     * Update the cart item from an array.
     *
     * @return void
     */
    public function updateFromArray(array $attributes)
    {
        $this->id = Arr::get($attributes, 'id', $this->id);
        $this->qty = Arr::get($attributes, 'qty', $this->qty);
        $this->name = Arr::get($attributes, 'name', $this->name);
        $this->price = Arr::get($attributes, 'price', $this->price);
        $this->options = new CartItemOptions(Arr::get($attributes, 'options', $this->options));

        $this->rowId = $this->generateRowId((string) $this->id, $this->options->all());
    }

    /**
     * Associate the cart item with a given model or something else.
     *
     * @return $this
     */
    public function associate($item, bool $is_model = true): CartItem
    {
        if ($is_model) {
            $this->associatedModel = is_string($item) ? $item : get_class($item);
            $this->model = $item;

            return $this;
        }

        $this->associatedModel = Arr::has($item, 'associatedModel') ? Arr::get($item, 'associatedModel') : null;
        $this->model = Arr::has($item, 'modelId') ? Arr::get($item, 'modelId') : null;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @return mixed
     */
    public function __get(string $attribute)
    {
        $properties = get_object_vars($this);

        if ($attribute === 'model' && isset($this->associatedModel)) {
            $associatedModel = $this->associatedModel;

            if (! class_exists($associatedModel)) {
                return null;
            }

            return (new $associatedModel)->find($this->id);
        }

        if (array_key_exists($attribute, $properties)) {
            return $this->{$attribute};
        }

        if ($attribute === 'priceTax') {
            return $this->price + $this->tax;
        }

        if ($attribute === 'subtotal') {
            return $this->qty * $this->price;
        }

        if ($attribute === 'total') {
            return $this->qty * $this->priceTax;
        }

        if ($attribute === 'tax') {
            return $this->price * ($this->taxRate / 100);
        }

        if ($attribute === 'taxTotal') {
            return $this->tax * $this->qty;
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     */
    public static function fromBuyable(Buyable $item): CartItem
    {
        return new self(
            $item->getId(),
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
     */
    public static function fromArray(array $attributes): CartItem
    {
        $options = Arr::get($attributes, 'options', []);

        $createdAt = isset($attributes['createdAt']) && $attributes['createdAt'] instanceof Carbon
            ? $attributes['createdAt']
            : Carbon::now();

        $updatedAt = isset($attributes['updatedAt']) && $attributes['updatedAt'] instanceof Carbon
            ? $attributes['updatedAt']
            : Carbon::now();

        return new self(
            $attributes['id'],
            (string) $attributes['name'],
            (string) ($attributes['subtitle'] ?? ''),
            $attributes['qty'],
            (float) $attributes['price'],
            isset($attributes['totalPrice']) ? (float) $attributes['totalPrice'] : null,
            (string) ($attributes['vatFcCode'] ?? ''),
            (string) ($attributes['productFcCode'] ?? ''),
            isset($attributes['vat']) ? (float) $attributes['vat'] : null,
            (string) ($attributes['urlImg'] ?? ''),
            $createdAt,
            $updatedAt,
            $options
        );
    }

    /**
     * Create a new instance from the given attributes.
     *
     * @param  Carbon|null  $createdAt
     * @param  Carbon|null  $updatedAt
     */
    public static function fromAttributes(
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
     */
    protected function generateRowId(string $id, array $options): string
    {
        ksort($options);

        return md5($id.serialize($options));
    }

    /**
     * Get the instance as an array.
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
        return $this->id == $buyable->getId()
            && $this->associatedModel === get_class($buyable);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the formatted number.
     */
    public function numberFormat(float $value, int $decimals, string $decimalPoint, string $thousandSeparator): string
    {
        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * @return array|\ArrayAccess|mixed|null
     */
    public function getCoupon(
        string $couponCode
    ) {
        $coupons = $this->appliedCoupons;

        return Arr::has($coupons, $couponCode)
            ? Arr::get($coupons, $couponCode)
            : null;
    }

    public function applyCoupon(
        string $couponCode,
        string $couponType,
        float $couponValue
    ): CartItem {
        $this->appliedCoupons[$couponCode] = (object) [
            'couponCode' => $couponCode,
            'couponType' => $couponType,
            'couponValue' => $couponValue,
        ];

        switch ($couponType) {
            case 'fixed':
                $this->totalPrice = $this->formatFloat($this->totalPrice - $couponValue);
                $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
                $this->vat = $this->formatFloat($this->price * $this->vatRate / 100);

                $this->discountValue = $this->formatFloat($this->discountValue + $couponValue);

                $this->appliedCoupons[$couponCode]->discountValue = $couponValue;
                break;
            case 'percentage':
                $discountValue = $this->formatFloat($this->originalTotalPrice * $couponValue / 100);

                $this->totalPrice = $this->formatFloat($this->totalPrice - $discountValue);
                $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
                $this->vat = $this->formatFloat($this->price * $this->vatRate / 100);

                $this->discountValue = $this->formatFloat($this->discountValue + $discountValue);

                $this->appliedCoupons[$couponCode]->discountValue = $discountValue;
                break;
            case 'global':
                $totalPrice = $couponValue;
                $price = $this->formatFloat($totalPrice * 100 / (100 + $this->vatRate));
                $vat = $this->formatFloat($price * $this->vatRate / 100);

                $this->totalPrice = $this->formatFloat($totalPrice * -1);
                $this->price = $this->formatFloat($price * -1);
                $this->vat = $this->formatFloat($vat * -1);

                $this->discountValue = $this->formatFloat($this->discountValue + $couponValue);

                $this->appliedCoupons[$couponCode]->discountValue = $couponValue;
                break;
            default:
                throw new InvalidArgumentException('Coupon type not handled. Possible values: fixed and percentage');
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function detachCoupon(
        string $couponCode
    ): CartItem {
        $coupon = $this->appliedCoupons[$couponCode];
        $discountValue = $coupon->discountValue;

        unset($this->appliedCoupons[$couponCode]);

        $this->totalPrice = $this->formatFloat($this->totalPrice + $discountValue);
        $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
        $this->vat = $this->formatFloat($this->price * $this->vatRate / 100);

        $this->discountValue = $this->formatFloat($this->discountValue - $discountValue);

        return $this;
    }

    public function hasCoupons(): bool
    {
        return count($this->appliedCoupons) > 0;
    }

    public function formatFloat(float $value): float
    {
        $decimals = (int) config('cart.format.decimals', 2);

        /** @var int<0,4> $mode */
        $mode = (int) config('cart.format.rounding_mode', PHP_ROUND_HALF_UP);

        return round($value, $decimals, $mode);
    }
}
