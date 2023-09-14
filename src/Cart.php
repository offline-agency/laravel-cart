<?php

namespace OfflineAgency\LaravelCart;

use ArrayAccess;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;

class Cart
{
    const DEFAULT_INSTANCE = 'default';
    const CART_OPTIONS_KEY = 'options';

    /**
     * @var
     */
    private $options = [];

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
     * @param SessionManager $session
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
            $vatFcCode,
            $productFcCode,
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
     * @param mixed $qty
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

        if (isset($cartItem->appliedCoupons)) {
            foreach ($cartItem->appliedCoupons as $coupon) {
                $this->removeCoupon($coupon->couponCode);
            }
        }

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

        if (!$content->has($rowId)) {
            throw new InvalidRowIDException("The cart does not contain rowId {$rowId}.");
        }

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        $this->setOptions([]);
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
        $total = $this->getContent()->reduce(function ($total, CartItem $cartItem) {
            return $total + $cartItem->totalPrice + (float)(($cartItem->qty - 1) * $cartItem->originalTotalPrice);
        }, 0);

        return $total < 0
            ? 0
            : $this->formatFloat($total);
    }

    /**
     * Get the total vat of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return float
     */
    public function vat(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): float
    {
        $vat = $this->getContent()->reduce(function ($tax, CartItem $cartItem) {
            return $tax + $cartItem->vat + (float)(($cartItem->qty - 1) * $cartItem->originalVat);
        }, 0);

        return $vat < 0
            ? 0
            : $this->formatFloat($vat);
    }

    /**
     * Get the subtotal (total - vat) of the items in the cart.
     *
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return float
     */
    public function subtotal(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null): float
    {
        $subtotal = $this->getContent()->reduce(function ($subTotal, CartItem $cartItem) {
            $cartItemSubTotal = $cartItem->name !== 'discountCartItem'
                ? $cartItem->price + (float)(($cartItem->qty - 1) * $cartItem->originalPrice)
                : 0;

            return $subTotal + $cartItemSubTotal;
        }, 0);

        return $subtotal < 0
            ? 0
            : $this->formatFloat($subtotal);
    }

    /**
     * @param int|null $decimals
     * @param string|null $decimalPoint
     * @param string|null $thousandSeparator
     * @return mixed
     */
    public function originalTotalPrice(int $decimals = null, string $decimalPoint = null, string $thousandSeparator = null)
    {
        return $this->getContent()->reduce(function ($originalTotalPrice, CartItem $cartItem) {
            return $originalTotalPrice + $cartItem->originalTotalPrice;
        }, 0);
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param Closure $search
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
     * @param mixed $model
     * @return void
     */
    public function associate(string $rowId, $model)
    {
        if (is_string($model) && !class_exists($model)) {
            throw new UnknownModelException("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

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
            'content' => serialize($content),
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
        if (!$this->storedCartWithIdentifierExists($identifier)) {
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

    /**
     * @return string
     */
    public function totalVatLabel(): string
    {
        return $this->vat() > 0 ? 'Iva Inclusa' : 'Esente Iva';
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection.
     *
     * @return Collection
     */
    protected function getContent(): Collection
    {
        return $this->session->has($this->instance)
            ? $this->session->get($this->instance)
            : new Collection();
    }

    /**
     * Get the cart generic info, if there is no cart set yet, return a new empty Collection.
     *
     * @return Collection
     */
    protected function getCartInfo(): Collection
    {
        return $this->session->has($this->getCartInstance())
            ? $this->session->get($this->getCartInstance())
            : new Collection();
    }

    /**
     * * Create a new CartItem from the supplied attributes.
     *
     *
     * @param  $id
     * @param  $name
     * @param  $subtitle
     * @param  $qty
     * @param  $price
     * @param  $totalPrice
     * @param  $vatFcCode
     * @param  $productFcCode
     * @param  $vat
     * @param  $urlImg
     * @param array $options
     * @return CartItem
     */
    private function createCartItem(
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
        array $options
    ): CartItem
    {
        if ($id instanceof Buyable) {
            $cartItem = CartItem::fromBuyable($id);
            $cartItem->setQuantity($name ?: 1);
            $cartItem->associate($id);
        } elseif (is_array($id)) {
            $cartItem = CartItem::fromArray($id);
            $cartItem->setQuantity($id['qty']);
        } else {
            $cartItem = CartItem::fromAttributes(
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
            $cartItem->setQuantity($qty);
        }

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
        if (!is_array($item)) {
            return false;
        }

        return is_array(head($item)) || head($item) instanceof Buyable;
    }

    /**
     * @param  $identifier
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
     * @param array $items
     * @return Collection
     */
    public function addBatch(array $items): Collection
    {
        foreach ($items as $item) {
            $id = Arr::get($item, 'id');
            $name = Arr::get($item, 'name');
            $subtitle = Arr::get($item, 'subtitle');
            $qty = Arr::get($item, 'qty');
            $price = Arr::get($item, 'price');
            $totalPrice = Arr::get($item, 'totalPrice');
            $vat = Arr::get($item, 'vat');

            $this->add(
                $id,
                $name,
                $subtitle,
                $qty,
                $price,
                $totalPrice,
                $vat
            );
        }

        return $this->content();
    }

    /**
     * Get the Formatted number.
     *
     * @param  $value
     * @param  $decimals
     * @param  $decimalPoint
     * @param  $thousandSeparator
     * @return string
     */
    private function numberFormat($value, $decimals, $decimalPoint, $thousandSeparator): string
    {
        if (is_null($decimals)) {
            $decimals = is_null(config('cart.format.decimals')) ? 2 : config('cart.format.decimals');
        }
        if (is_null($decimalPoint)) {
            $decimalPoint = is_null(config('cart.format.decimal_point')) ? '.' : config('cart.format.decimal_point');
        }
        if (is_null($thousandSeparator)) {
            $thousandSeparator = is_null(config('cart.format.thousand_separator')) ? ',' : config('cart.format.thousand_separator');
        }

        return number_format($value, $decimals, $decimalPoint, $thousandSeparator);
    }

    /**
     * @return array
     */
    public function coupons(): array
    {
        return $this->getContent()->reduce(function ($coupons, CartItem $cartItem) {
            foreach ($cartItem->appliedCoupons as $coupon) {
                Arr::set(
                    $coupons,
                    $coupon->couponCode,
                    (object)[
                        'rowId' => $cartItem->rowId,
                        'couponCode' => $coupon->couponCode,
                        'couponType' => $coupon->couponType,
                        'couponValue' => $coupon->couponValue,
                    ]
                );
            }

            return $coupons;
        }, []);
    }

    /**
     * @param  $rowId
     * @param string $couponCode
     * @param string $couponType
     * @param float $couponValue
     */
    public function applyCoupon(
        $rowId,
        string $couponCode,
        string $couponType,
        float $couponValue
    )
    {
        if (!is_null($rowId)) {
            $cartItem = $this->get($rowId);

            $cartItem->applyCoupon(
                $couponCode,
                $couponType,
                $couponValue
            );

            $content = $this->getContent();

            $content->put($cartItem->rowId, $cartItem);

            $this->session->put($this->instance, $content);

            $this->coupons()[$couponCode] = (object)[
                'rowId' => $rowId,
                'couponCode' => $couponCode,
                'couponType' => $couponType,
                'couponValue' => $couponValue,
            ];
        } else {
            $this->applyGlobalCoupon(
                $couponCode,
                $couponType,
                $couponValue
            );
        }
    }

    /**
     * @param  $rowId
     * @param string $couponCode
     */
    public function detachCoupon(
        $rowId,
        string $couponCode
    )
    {
        $cartItem = $this->get($rowId);

        $cartItem->detachCoupon(
            $couponCode
        );

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        $this->session->put($this->instance, $content);

        unset($this->coupons()[$couponCode]);

        if ($cartItem->name === 'discountCartItem') {
            $this->remove($rowId);
        }
    }

    /**
     * @return bool
     */
    public function hasCoupons(): bool
    {
        return count($this->coupons()) > 0;
    }

    /**
     * @return bool
     */
    public function hasGlobalCoupon()
    {
        $coupons = $this->coupons();

        if (count($coupons) > 0) {
            foreach ($coupons as $coupon) {
                if ($coupon->couponType === 'global') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string $couponCode
     * @return array|ArrayAccess|mixed|null
     */
    public function getCoupon(
        string $couponCode
    )
    {
        $coupons = $this->coupons();

        return Arr::has($coupons, $couponCode)
            ? Arr::get($coupons, $couponCode)
            : null;
    }

    /**
     * @param string|null $couponCode
     */
    public function removeCoupon(?string $couponCode)
    {
        if (!is_null($couponCode)) {
            unset($this->coupons()[$couponCode]);
        }
    }

    /**
     * @param float $value
     * @return float
     */
    private function formatFloat(float $value): float
    {
        return (float)number_format(
            $value, // the number to format
            2, // how many decimal points
            '.', // decimal separator
            '' // thousands separator, set it to blank
        );
    }

    /**
     * @param  $couponCode
     * @param  $couponType
     * @param  $couponValue
     */
    private function applyGlobalCoupon(
        $couponCode,
        $couponType,
        $couponValue
    )
    {
        $discount_value = 0;
        $originalTotalPrice = $this->originalTotalPrice();
        switch ($couponType) {
            case 'fixed':
                $discount_value = $couponValue;
                break;
            case 'percentage':
                $discount_value = $this->formatFloat($originalTotalPrice * $couponValue / 100);
                break;
        }

        $cartItem = $this->add(
            md5(Carbon::now()),
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
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        $cart_info = $this->getCartInfo();

        return Arr::has($cart_info, self::CART_OPTIONS_KEY) ? Arr::get($cart_info, self::CART_OPTIONS_KEY) : [];
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $content = $this->getCartInfo();

        $content->put(self::CART_OPTIONS_KEY, $options);

        $this->session->put($this->getCartInstance(), $content);
    }

    /**
     * @return string
     */
    private function getCartInstance(): string
    {
        return $this->instance . '_cart_info';
    }

    /**
     * @param  $key
     * @param  $default_value
     * @return array|ArrayAccess|mixed|null
     */
    public function getOptionsByKey($key, $default_value = null)
    {
        $options = $this->getOptions();

        return Arr::has($options, $key) ? Arr::get($options, $key) : $default_value;
    }
}
