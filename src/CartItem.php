<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use OfflineAgency\LaravelCart\Contracts\Buyable;

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

    public $tax = 0.0;

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
        $totalPrice,
        $vatFcCode,
        $productFcCode,
        $vat,
        $urlImg,
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null,
        array $options = []
    ) {
        if (empty($id)) {
            throw new InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Please supply a valid name.');
        }

        $this->rowId = $this->generateRowId($id, $options);
        $this->id = $id;
        $this->qty = $qty;
        $this->name = $name;
        $this->subtitle = $subtitle;
        $this->originalPrice = floatval($price);
        $this->originalTotalPrice = floatval($totalPrice);
        $this->originalVat = floatval($vat);
        $this->price = floatval($price);
        $this->totalPrice = floatval($totalPrice);
        $this->vat = floatval($vat);
        $this->vatLabel = $this->vat > 0 ? 'Iva Inclusa' : 'Esente Iva';
        $this->vatRate = $this->price > 0 ? $this->formatFloat(100 * $this->vat / $this->price) : 0;
        $this->vatFcCode = $vatFcCode;
        $this->productFcCode = $productFcCode;
        $this->urlImg = $urlImg;
        $this->options = new CartItemOptions($options);
        // default values
        $this->discountValue = 0.0;
        $this->associatedModel = null;
        $this->model = null;
        $this->appliedCoupons = [];
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param  int|float  $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty)) {
            throw new InvalidArgumentException('Please supply a valid quantity. Provided: '.$qty);
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

        $this->rowId = $this->generateRowId($this->id, $this->options->all());
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
            $item->getVatFcCode(),
            $item->getProductFcCode(),
            $item->getVat(),
            $item->getUrlImg(),
            $item->getOptions()
        );
    }

    /**
     * Create a new instance from the given array.
     */
    public static function fromArray(array $attributes): CartItem
    {
        $options = Arr::get($attributes, 'options', []);

        return new self(
            $attributes['id'],
            $attributes['name'],
            $attributes['subtitle'],
            $attributes['qty'],
            $attributes['price'],
            $attributes['totalPrice'],
            $attributes['vatFcCode'],
            $attributes['productFcCode'],
            $attributes['vat'],
            $attributes['urlImg'],
            $options
        );
    }

    /**
     *  * Create a new instance from the given attributes.
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
            'options' => $this->options->toArray(),
            'associatedModel' => $this->associatedModel,
            'model' => $this->model,
            'appliedCoupons' => $this->appliedCoupons,
        ];
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
        return (float) number_format(
            $value, // the number to format
            2, // how many decimal points
            '.', // decimal separator
            '' // thousands separator, set it to blank
        );
    }
}
