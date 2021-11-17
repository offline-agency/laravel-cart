<?php

namespace OfflineAgency\LaravelCart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use OfflineAgency\LaravelCart\Contracts\Buyable;

class CartItem implements Arrayable, Jsonable
{
    public $rowId;
    public $id;
    public $qty;
    public $name;
    public $subtitle;

    public $price;
    public $totalPrice;

    public $vat;
    public $vatLabel;
    public $vatRate;
    public $vatFcCode;

    public $urlImg;

    public $options;

    public $associatedModel;
    public $model;
    public $productFcCode;

    public $discountValue;
    public $discountCode;
    public $discountDescription;
    public $discountRate;

    public $couponCode;
    public $couponType;
    public $couponValue;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param string     $subtitle
     * @param $qty
     * @param float $price
     * @param $totalPrice
     * @param $vatFcCode
     * @param $productFcCode
     * @param $vat
     * @param $urlImg
     * @param array $options
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
        array $options = []
    ) {
        if (empty($id)) {
            throw new InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new InvalidArgumentException('Please supply a valid name.');
        }
        if (strlen($price) < 0 || !is_numeric($price)) {
            throw new InvalidArgumentException('Please supply a valid price.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->subtitle = $subtitle;
        $this->qty = $qty;
        $this->price = floatval($price);
        $this->totalPrice = floatval($totalPrice);
        $this->vatFcCode = $vatFcCode;
        $this->productFcCode = $productFcCode;
        $this->vat = floatval($vat);
        $this->vatLabel = $this->vat > 0 ? 'Iva Inclusa' : 'Esente Iva';
        $this->vatRate = $this->formatFloat( 100 * $this->vat / $this->totalPrice);
        $this->urlImg = $urlImg;
        $this->options = new CartItemOptions($options);
        $this->rowId = $this->generateRowId($id, $options);
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty) || !is_numeric($qty)) {
            throw new InvalidArgumentException('Please supply a valid quantity. Provided: '.$qty);
        }

        $this->qty = $qty;
    }

    /**
     * Update the cart item from a Buyable.
     *
     * @param Buyable $item
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
     * @param array $attributes
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
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     *
     * @return CartItem
     */
    public function associate($model): CartItem
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);
        $this->model = $model;

        return $this;
    }

    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     *
     * @return mixed
     */
    public function __get(string $attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'priceTax') {
            return $this->price + $this->tax;
        }

        if ($attribute === 'subtotal') {
            return $this->qty * $this->price;
        }

        if ($attribute === 'total') {
            return $this->qty * ($this->priceTax);
        }

        if ($attribute === 'tax') {
            return $this->price * ($this->taxRate / 100);
        }

        if ($attribute === 'taxTotal') {
            return $this->tax * $this->qty;
        }

        if ($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel())->find($this->id);
        }

        return null;
    }

    /**
     * Create a new instance from a Buyable.
     *
     * @param Buyable $item
     *
     * @return CartItem
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
            $item->getSubtitle(),
            $item->getOptions()
        );
    }

    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     *
     * @return CartItem
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
     *
     * @param $id
     * @param $name
     * @param $subtitle
     * @param $qty
     * @param $price
     * @param $totalPrice
     * @param $vatFcCode
     * @param $productFcCode
     * @param $vat
     * @param $urlImg
     * @param array $options
     *
     * @return CartItem
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
     *
     * @param string $id
     * @param array  $options
     *
     * @return string
     */
    protected function generateRowId(string $id, array $options): string
    {
        ksort($options);

        return md5($id.serialize($options));
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'rowId'         => $this->rowId,
            'id'            => $this->id,
            'name'          => $this->name,
            'subtitle'      => $this->subtitle,
            'qty'           => $this->qty,
            'price'         => $this->price,
            'vatFcCode'     => $this->vatFcCode,
            'productFcCode' => $this->productFcCode,
            'vat'           => $this->vat,
            'urlImg'        => $this->urlImg,
            'options'       => $this->options->toArray(),
        ];
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param int $options
     *
     * @return string
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get the formatted number.
     *
     * @param float  $value
     * @param int    $decimals
     * @param string $decimalPoint
     * @param string $thousandSeparator
     *
     * @return string
     */
    private function numberFormat(float $value, int $decimals, string $decimalPoint, string $thousandSeparator): string
    {
        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

  /**
   * @param string $couponCode
   * @param string $couponType
   * @param float $couponValue
   * @return CartItem
   */
    public function applyCoupon(
        string $couponCode,
        string $couponType,
        float $couponValue
    ): CartItem
    {
        $this->couponCode = $couponCode;
        $this->couponType = $couponType;
        $this->couponValue = $couponValue;

        if($couponType === 'fixed'){
          $this->discountValue = $couponValue;
          $this->discountRate = $this->formatFloat(100 * $couponValue / $this->totalPrice);

          $this->totalPrice = $this->totalPrice - $couponValue;
          $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
          $this->vat = $this->formatFloat($this->price * $this->vatRate / 100);
        }else if($couponType === 'percentage'){
          $discountValue = $this->formatFloat($this->totalPrice * $couponValue / 100);
          $this->discountValue = $discountValue;
          $this->discountRate = $couponValue;

          $this->totalPrice =  $this->formatFloat($this->totalPrice - $discountValue);
          $this->price = $this->formatFloat($this->totalPrice * 100 / (100 + $this->vatRate));
          $this->vat = $this->formatFloat($this->price * $this->vatRate / 100);
        }else{
          throw new InvalidArgumentException('Coupon type not handled. Possible values: fixed and percentage');
        }
        return $this;
    }

  /**
   * @param float $value
   * @return float
   */
  private function formatFloat(float $value): float
  {
    return (float) number_format(
      $value, // the number to format
      2, // how many decimal points
      ".", // decimal separator
      "" // thousands separator, set it to blank
    );
  }
}
