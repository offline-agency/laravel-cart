<?php

namespace OfflineAgency\LaravelCart;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Session\SessionManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Events\Dispatcher;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;

class Cart
{
    const DEFAULT_INSTANCE = 'default';

    /**
     * Instance of the session manager.
     *
     * @var SessionManager
     */
    private $session;

    /**
     * Instance of the event dispatcher.
     *
     * @var Dispatcher
     */
    private $events;

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Cart constructor.
     *
     * @param SessionManager      $session
     * @param Dispatcher $events
     */
    public function __construct(SessionManager $session, Dispatcher $events)
    {
        $this->session = $session;
        $this->events = $events;

        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     * @return Cart
     */
    public function instance(string $instance = null): Cart
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance(): string
    {
        return str_replace('cart.', '', $this->instance);
    }

  /**
   * Add an item to the cart.
   *
   * @param mixed $id
   * @param mixed $name
   * @param string|null $subtitle
   * @param int|null $qty
   * @param float|null $price
   * @param float|null $totalPrice
   * @param float|null $vat
   * @param string|null $vatFcCode
   * @param string|null $productFcCode
   * @param string|null $urlImg
   * @param array $options
   * @return array|CartItem|CartItem[]
   */
    public function add(
      $id,
      string $name,
      string $subtitle,
      int $qty,
      float $price,
      float $totalPrice,
      float $vat,
      string $vatFcCode = '',
      string $productFcCode = '',
      string $urlImg = '',
      array $options = []
    )
    {
        if ($this->isMulti($id)) {
            return array_map(function ($item) {
                return $this->add($item);
            }, $id);
        }

        $cartItem = $this->createCartItem(
          $id,
          $name,
          $subtitle,
          $qty,
          $price,
          $totalPrice,
          $vat,
          $urlImg,
          $options
        );

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        $this->events->dispatch('cart.added', $cartItem);

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $qty
     * @return CartItem|void
     */
    public function update(string $rowId, $qty)
    {
        $cartItem = $this->get($rowId);

        if ($qty instanceof Buyable) {
            $cartItem->updateFromBuyable($qty);
        } elseif (is_array($qty)) {
            $cartItem->updateFromArray($qty);
        } else {
            $cartItem->qty = $qty;
        }

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);
            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.updated', $cartItem);

        $this->session->put($this->instance, $content);

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function remove(string $rowId)
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        $this->events->dispatch('cart.removed', $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     * @return CartItem
     */
    public function get(string $rowId)
    {
        $content = $this->getContent();

        if ( ! $content->has($rowId))
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->session->remove($this->instance);
    }

    /**
     * Get the content of the cart.
     *
     * @return Collection
     */
    public function content(): Collection
    {
        if (is_null($this->session->get($this->instance))) {
            return new Collection([]);
        }
        return $this->session->get($this->instance);
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        $content = $this->getContent();

        return $content->sum('qty');
    }

    /**
     * Get the total price of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return float
     */
    public function total(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): float
    {
        return $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + ($cartItem->qty * $cartItem->totalPrice);
        }, 0);
    }

    /**
     * Get the total tax of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return float
     */
    public function tax(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null)
    {
        return $this->getContent()->reduce(function ($tax, CartItem $cartItem) {
          return $tax + ($cartItem->qty * $cartItem->vat);
      }, 0);
    }

    /**
     * Get the subtotal (total - tax) of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return float
     */
    public function subtotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): float
    {
        $content = $this->getContent();

      return $content->reduce(function ($subTotal, CartItem $cartItem) {
          return $subTotal + ($cartItem->qty * $cartItem->price);
      }, 0);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return Collection
     */
    public function search(Closure $search): Collection
    {
        $content = $this->getContent();

        return $content->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     * @return void
     */
    public function associate(string $rowId, $model)
    {
        if(is_string($model) && ! class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Set the tax rate for the cart item with the given rowId.
     *
     * @param string $rowId
     * @param int|float $taxRate
     * @return void
     */
    public function setTax(string $rowId, $taxRate)
    {
        $cartItem = $this->get($rowId);

        $cartItem->setTaxRate($taxRate);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);
    }

    /**
     * Store the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     */
    public function store($identifier)
    {
        $content = $this->getContent();

        if ($this->storedCartWithIdentifierExists($identifier)) {
            throw new CartAlreadyStoredException("A cart with identifier {$identifier} was already stored.");
        }

        $this->getConnection()->table($this->getTableName())->insert([
            'identifier' => $identifier,
            'instance' => $this->currentInstance(),
            'content' => serialize($content)
        ]);

        $this->events->dispatch('cart.stored');
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function restore($identifier)
    {
        if( ! $this->storedCartWithIdentifierExists($identifier)) {
            return;
        }

        $stored = $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->first();

        $storedContent = unserialize($stored->content);

        $currentInstance = $this->currentInstance();

        $this->instance($stored->instance);

        $content = $this->getContent();

        foreach ($storedContent as $cartItem) {
            $content->put($cartItem->rowId, $cartItem);
        }

        $this->events->dispatch('cart.restored');

        $this->session->put($this->instance, $content);

        $this->instance($currentInstance);

        $this->getConnection()->table($this->getTableName())
            ->where('identifier', $identifier)->delete();
    }

    /**
     * Magic method to make accessing the total, tax and subtotal properties possible.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get(string $attribute)
    {
        if($attribute === 'total') {
            return $this->total();
        }

        if($attribute === 'tax') {
            return $this->tax();
        }

        if($attribute === 'subtotal') {
            return $this->subtotal();
        }

        return null;
    }

  /**
   * @return string
   */
  public function totalVatLabel(): string
    {
    	return $this->tax() > 0 ? "Iva Inclusa" : "Esente Iva";
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection
     *
     * @return Collection
     */
    protected function getContent(): Collection
    {
      return $this->session->has($this->instance)
          ? $this->session->get($this->instance)
          : new Collection;
    }


  /**
   *
   * * Create a new CartItem from the supplied attributes.
   *
   *
   * @param $id
   * @param $name
   * @param $subtitle
   * @param $qty
   * @param $price
   * @param $totalPrice
   * @param $vat
   * @param $urlImg
   * @param array $options
   *
   * @return CartItem
   */
	private function createCartItem(
    $id,
    $name,
    $subtitle,
    $qty,
    $price,
    $totalPrice,
    $vat,
    $urlImg,
    array $options
  ): CartItem
  {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id, []);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes($id, $name,$subtitle, $price, $totalPrice, $vat, $urlImg, $options);
            $cartItem->setQuantity($qty);
        }

        $cartItem->setTaxRate(config('cart.tax'));

        return $cartItem;
    }

    /**
     * Check if the item is a multidimensional array or an array of Buyable.
     *
     * @param mixed $item
     * @return bool
     */
    private function isMulti($item): bool
    {
        if ( ! is_array($item)) return false;

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * @param $identifier
     * @return bool
     */
    private function storedCartWithIdentifierExists($identifier): bool
    {
        return $this->getConnection()->table($this->getTableName())->where('identifier', $identifier)->exists();
    }

    /**
     * Get the database connection.
     *
     * @return Connection
     */
    private function getConnection(): Connection
    {
        $connectionName = $this->getConnectionName();

        return app(DatabaseManager::class)->connection($connectionName);
    }

    /**
     * Get the database table name.
     *
     * @return string
     */
    private function getTableName(): string
    {
        return config('cart.database.table', 'cart');
    }

    /**
     * Get the database connection name.
     *
     * @return string
     */
    private function getConnectionName(): string
    {
        $connection = config('cart.database.connection');

        return is_null($connection) ? config('database.default') : $connection;
    }

    /**
     * Get the Formatted number
     *
     * @param $value
     * @param $decimals
     * @param $decimalPoint
     * @param $thousandSeparator
     * @return string
     */
    private function numberFormat($value, $decimals, $decimalPoint, $thousandSeparator): string
    {
        if(is_null($decimals)){
            $decimals = is_null(config('cart.format.decimals')) ? 2 : config('cart.format.decimals');
        }
        if(is_null($decimalPoint)){
            $decimalPoint = is_null(config('cart.format.decimal_point')) ? '.' : config('cart.format.decimal_point');
        }
        if(is_null($thousandSeparator)){
          $thousandSeparator = is_null(config('cart.format.thousand_separator')) ? ',' : config('cart.format.thousand_separator');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }
}
