<?php

namespace OfflineAgency\LaravelCart;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use OfflineAgency\LaravelCart\Contracts\Buyable;

class CartItem implements Arrayable, Jsonable {
	/**
	 * The rowID of the cart item.
	 *
	 * @var string
	 */
	public $rowId;

	/**
	 * The ID of the cart item.
	 *
	 * @var int|string
	 */
	public $id;

	/**
	 * The quantity for this cart item.
	 *
	 * @var int|float
	 */
	public $qty;

	/**
	 * The name of the cart item.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The subtitle of the cart item.
	 *
	 * @var string
	 */
	public $subtitle;

	/**
	 * The price without TAX of the cart item.
	 *
	 * @var float
	 */
	public $price;

	/**
	 * The price with TAX of the cart item.
	 *
	 * @var float
	 */
	public $totalPrice;

	/**
	 * The price with discount of the cart item.
	 *
	 * @var float
	 */
	public $vat;

	/**
	 * The vat label of the cart item
	 *
	 * @var float
	 */
	public $vatLabel;

	/**
	 * The url image of the cart item
	 *
	 * @var float
	 */
	public $urlImg;

	/**
	 * The options for this cart item.
	 *
	 * @var array
	 */
	public $options;

	/**
	 * The FQN of the associated model.
	 *
	 * @var string|null
	 */
	public $associatedModel = null;

	/**
	 * The tax rate for the cart item.
	 *
	 * @var int|float
	 */
  public $taxRate = 0;
  /**
   * @var float|int|mixed|null
   */
  public $priceTax;
  /**
   * @var float|int|mixed|null
   */
  public $tax;
  /**
   * @var float|int|mixed|null
   */
  public $taxTotal;
  /**
   * @var float|int|mixed|null
   */
  public $subtotal;
  /**
   * @var float|int|mixed|null
   */
  public $total;
  /**
   * @var float|int|mixed|null
   */
  public $productFcCode;
  /**
   * @var float|int|mixed|null
   */
  public $vatFcCode;

  /**
   * CartItem constructor.
   *
   * @param int|string $id
   * @param string $name
   * @param string $subtitle
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
		if ( empty( $id ) ) {
			throw new \InvalidArgumentException( 'Please supply a valid identifier.' );
		}
		if ( empty( $name ) ) {
			throw new \InvalidArgumentException( 'Please supply a valid name.' );
		}
		if ( strlen( $price ) < 0 || ! is_numeric( $price ) ) {
			throw new \InvalidArgumentException( 'Please supply a valid price.' );
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
    $this->vatLabel = $this->vat > 0 ? "Iva Inclusa" : "Esente Iva";
    $this->urlImg = $urlImg;
    $this->options = new CartItemOptions($options);
    $this->rowId = $this->generateRowId($id, $options);
	}

  /**
   * Returns the formatted price without TAX.
   *
   * @param int|null $decimals
   * @param string|null $decimalPoint
   * @param string|null $thousandSeparator
   *
   * @return string
   */
	public function price( int $decimals = null, string $decimalPoint = null,string $thousandSeparator = null ) {
		return $this->numberFormat( $this->price, $decimals, $decimalPoint, $thousandSeparator );
	}

	/**
	 * Returns the formatted price with TAX.
	 *
	 * @param int|null $decimals
	 * @param string|null $decimalPoint
	 * @param string|null $thousandSeparator
	 *
	 * @return string
	 */
	public function priceTax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null ): string
  {
		return $this->numberFormat( $this->priceTax, $decimals, $decimalPoint, $thousandSeparator );
	}

	/**
	 * Returns the formatted subtotal.
	 * Subtotal is price for whole CartItem without TAX
	 *
	 * @param int|null $decimals
	 * @param string|null $decimalPoint
	 * @param string|null $thousandSeparator
	 *
	 * @return string
	 */
	public function subtotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null ): string
  {
		return $this->numberFormat( $this->subtotal, $decimals, $decimalPoint, $thousandSeparator );
	}

	/**
	 * Returns the formatted total.
	 * Total is price for whole CartItem with TAX
	 *
	 * @param int|null $decimals
	 * @param string|null $decimalPoint
	 * @param string|null $thousandSeparator
	 *
	 * @return float
	 */
	public function total(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null ) {
		return $this->numberFormat( $this->total, $decimals, $decimalPoint, $thousandSeparator );
	}

	/**
	 * Returns the formatted tax.
	 *
	 * @param int|null $decimals
	 * @param string|null $decimalPoint
	 * @param string|null $thousandSeparator
	 *
	 * @return string
	 */
	public function tax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null ) {
		return $this->numberFormat( $this->tax, $decimals, $decimalPoint, $thousandSeparator );
	}

	/**
	 * Returns the formatted tax.
	 *
	 * @param int|null $decimals
	 * @param string|null $decimalPoint
	 * @param string|null $thousandSeparator
	 *
	 * @return float
	 */
	public function taxTotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null ): float
  {
		return $this->taxTotal;
	}

	/**
	 * Set the quantity for this cart item.
	 *
	 * @param int|float $qty
	 */
	public function setQuantity( $qty ) {

		if ( empty( $qty ) || ! is_numeric( $qty ) ) {
			throw new \InvalidArgumentException( 'Please supply a valid quantity. Provided: ' . $qty );
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
	public function updateFromBuyable( Buyable $item ) {
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
	public function updateFromArray( array $attributes ) {
		$this->id       = Arr::get( $attributes, 'id', $this->id );
		$this->qty      = Arr::get( $attributes, 'qty', $this->qty );
		$this->name     = Arr::get( $attributes, 'name', $this->name );
		$this->price    = Arr::get( $attributes, 'price', $this->price );
		$this->priceTax = $this->price + $this->tax;
		$this->options  = new CartItemOptions( Arr::get( $attributes, 'options', $this->options ) );

		$this->rowId = $this->generateRowId( $this->id, $this->options->all() );
	}

	/**
	 * Associate the cart item with the given model.
	 *
	 * @param mixed $model
	 *
	 * @return CartItem
	 */
	public function associate( $model ): CartItem
  {
		$this->associatedModel = is_string( $model ) ? $model : get_class( $model );

		return $this;
	}

	/**
	 * Set the tax rate.
	 *
	 * @param int|float $taxRate
	 *
	 * @return CartItem
	 */
	public function setTaxRate( $taxRate ): CartItem
  {
		$this->taxRate = $taxRate;

		return $this;
	}

	/**
	 * Get an attribute from the cart item or get the associated model.
	 *
	 * @param string $attribute
	 *
	 * @return mixed
	 */
	public function __get(string $attribute ) {
		if ( property_exists( $this, $attribute ) ) {
			return $this->{$attribute};
		}

		if ( $attribute === 'priceTax' ) {
			return $this->price + $this->tax;
		}

		if ( $attribute === 'subtotal' ) {
			return $this->qty * $this->price;
		}

		if ( $attribute === 'total' ) {
			return $this->qty * ( $this->priceTax );
		}

		if ( $attribute === 'tax' ) {
			return $this->price * ( $this->taxRate / 100 );
		}

		if ( $attribute === 'taxTotal' ) {
			return $this->tax * $this->qty;
		}

		if ( $attribute === 'model' && isset( $this->associatedModel ) ) {
			return with( new $this->associatedModel )->find( $this->id );
		}

		return null;
	}

  /**
   * Create a new instance from a Buyable.
   *
   * @param Buyable $item
   * @return CartItem
   */
	public static function fromBuyable( Buyable $item ): CartItem
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
	public static function fromArray( array $attributes ): CartItem
  {
		$options = Arr::get( $attributes, 'options', [] );

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
  ): CartItem
  {
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
	 * @param array $options
	 *
	 * @return string
	 */
	protected function generateRowId( string $id, array $options ): string
  {
		ksort( $options );

		return md5( $id . serialize( $options ) );
	}

	/**
	 * Get the instance as an array.
	 *
	 * @return array
	 */
	public function toArray(): array
  {
    return [
      'rowId' => $this->rowId,
      'id' => $this->id,
      'name' => $this->name,
      'subtitle' => $this->subtitle,
      'qty' => $this->qty,
      'price' => $this->price,
      'vatFcCode' => $this->vatFcCode,
      'productFcCode' => $this->productFcCode,
      'vat' => $this->vat,
      'urlImg' => $this->urlImg,
      'options' => $this->options->toArray()
    ];
	}

	/**
	 * Convert the object to its JSON representation.
	 *
	 * @param int $options
	 *
	 * @return string
	 */
	public function toJson( $options = 0 ): string
  {
		return json_encode( $this->toArray(), $options );
	}

	/**
	 * Get the formatted number.
	 *
	 * @param float $value
	 * @param int $decimals
	 * @param string $decimalPoint
	 * @param string $thousandSeparator
	 *
	 * @return string
	 */
	private function numberFormat(float $value, int $decimals, string $decimalPoint, string $thousandSeparator ): string
  {
    return number_format( $value, $decimals, $decimalPoint, $thousandSeparator );
	}
}
