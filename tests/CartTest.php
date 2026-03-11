<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;
use OfflineAgency\LaravelCart\Tests\Fixtures;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel;

function getCart(): Cart
{
    $session = app('session');
    $events = app('events');

    return new Cart($session, $events);
}

function setConfigFormat(int $decimals, string $decimalPoint, string $thousandSeparator): void
{
    config(['cart.format.decimals' => $decimals]);
    config(['cart.format.decimal_point' => $decimalPoint]);
    config(['cart.format.thousand_separator' => $thousandSeparator]);
}

it('has a default instance', function (): void {
    $cart = getCart();

    expect($cart->currentInstance())->toBe(Cart::DEFAULT_INSTANCE);
});

it('can have multiple instances', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->instance('wishlist')->add(1, 'Wishlist item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
    $this->assertItemsInCart(1, $cart->instance('wishlist'));
});

it('can add an item', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    expect($cart->count())->toEqual(1);

    Event::assertDispatched('cart.added');
});

it('will return the cart item of the added item', function (): void {
    Event::fake();

    $cart = getCart();

    $cartItem = $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    expect($cartItem)->toBeInstanceOf(CartItem::class);
    expect($cartItem->rowId)->toEqual('027c91341fd5cf4d2579b49c4b6a90da');

    Event::assertDispatched('cart.added');
});

it('can add multiple buyable items at once', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    expect($cart->count())->toEqual(2);

    Event::assertDispatched('cart.added');
});

it('will return an array of cart items when you add multiple items at once', function (): void {
    Event::fake();

    $cart = getCart();

    $cartItems = $cart->addBatch([
        [
            'id' => 1,
            'name' => '1st Cart item',
            'subtitle' => 'This is a simple description',
            'qty' => 1,
            'price' => 10.00,
            'totalPrice' => 12.22,
            'vat' => 2.22,
        ],
        [
            'id' => 2,
            'name' => '2nd Cart item',
            'subtitle' => 'This is a simple description',
            'qty' => 1,
            'price' => 10.00,
            'totalPrice' => 12.22,
            'vat' => 2.22,
        ],
    ]);

    expect($cartItems)->toBeInstanceOf(Collection::class);
    expect($cartItems)->toHaveCount(2);
    expect($cartItems)->each->toBeInstanceOf(CartItem::class);

    Event::assertDispatched('cart.added');
});

it('can add an item from attributes', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    expect($cart->count())->toEqual(1);

    Event::assertDispatched('cart.added');
});

it('can add an item from an array', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->addBatch([
        [
            'id' => 1,
            'name' => 'Cart item',
            'subtitle' => 'This is a simple description',
            'qty' => 1,
            'price' => 10.00,
            'totalPrice' => 12.22,
            'vat' => 2.22,
        ],
    ]);

    expect($cart->count())->toEqual(1);

    Event::assertDispatched('cart.added');
});

it('can add multiple array items at once', function (): void {
    Event::fake();

    $cart = getCart();

    $result = $cart->add([
        [
            'id' => 1,
            'name' => '1st Cart item',
            'subtitle' => 'This is a simple description',
            'qty' => 1,
            'price' => 10.00,
            'totalPrice' => 12.22,
            'vat' => 2.22,
            'urlImg' => 'https://ecommerce.test/images/item-name.png',
            'vatFcCode' => '0',
            'productFcCode' => '0',
        ],
        [
            'id' => 2,
            'name' => '2nd Cart item',
            'subtitle' => 'This is a simple description',
            'qty' => 1,
            'price' => 10.00,
            'totalPrice' => 12.22,
            'vat' => 2.22,
            'urlImg' => 'https://ecommerce.test/images/item-name.png',
            'vatFcCode' => '0',
            'productFcCode' => '0',
        ],
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveCount(2);
    expect($result)->each->toBeInstanceOf(CartItem::class);

    Event::assertDispatched('cart.added', 2);
});

it('can add an item with options', function (): void {
    Event::fake();

    $cart = getCart();

    $options = ['size' => 'XL', 'color' => 'red'];

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22, '', '', '', null, null, $options);

    $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

    expect($cartItem)->toBeInstanceOf(CartItem::class);
    expect($cartItem->options->size)->toEqual('XL');
    expect($cartItem->options->color)->toEqual('red');

    Event::assertDispatched('cart.added');
});

it('will validate the identifier', function (): void {
    $cart = getCart();

    expect(fn () => $cart->add(null, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22))
        ->toThrow(InvalidArgumentException::class, 'Please supply a valid identifier.');
});

it('will validate the quantity', function (): void {
    $cart = getCart();

    expect(fn () => $cart->add(1, 'Cart item', 'This is a simple description', null, 10.00, 12.22, 2.22))
        ->toThrow(InvalidArgumentException::class, 'Please supply a valid quantity.');
});

it('will validate the price', function (): void {
    $cart = getCart();

    expect(fn () => $cart->add(null, 'Cart item', 'This is a simple description', 1, null))
        ->toThrow(InvalidArgumentException::class);
});

it('will update the cart if the item already exists in the cart', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $this->assertItemsInCart(2, $cart);
    $this->assertRowsInCart(1, $cart);
});

it('will keep updating the quantity when an item is added multiple times', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $this->assertItemsInCart(3, $cart);
    $this->assertRowsInCart(1, $cart);
});

it('can update the quantity of an existing item in the cart', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

    $this->assertItemsInCart(2, $cart);
    $this->assertRowsInCart(1, $cart);

    Event::assertDispatched('cart.updated');
});

it('can update an existing item in the cart from a buyable', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

    $this->assertItemsInCart(1, $cart);
    expect($cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name)->toEqual('Different description');

    Event::assertDispatched('cart.updated');
});

it('can update an existing item in the cart from an array', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

    $this->assertItemsInCart(1, $cart);
    expect($cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name)->toEqual('Different description');

    Event::assertDispatched('cart.updated');
});

it('will throw an exception if a row id was not found', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    expect(fn () => $cart->update('none-existing-row-id', 2))
        ->toThrow(InvalidRowIDException::class, 'The cart does not contain rowId none-existing-row-id');
});

it('will regenerate the row id if the options changed', function (): void {
    $cart = getCart();

    $cartItem = $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $initialRowId = $cartItem->rowId;

    $cart->update($initialRowId, ['options' => ['color' => 'blue']]);

    $this->assertItemsInCart(1, $cart);
    $this->assertRowsInCart(1, $cart);

    $newItem = $cart->content()->first();

    expect($newItem->rowId)->not->toEqual($initialRowId);
    expect($newItem->options->color)->toEqual('blue');
});

it('will add the item to an existing row if the options changed to an existing row id', function (): void {
    $cart = getCart();

    $cartItem1 = $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cartItem2 = $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22, '', '', '', null, null, ['color' => 'red']);

    $this->assertRowsInCart(2, $cart);

    $cart->update($cartItem1->rowId, ['options' => ['color' => 'red']]);

    $this->assertItemsInCart(2, $cart);
    $this->assertRowsInCart(1, $cart);
});

it('can remove an item from the cart', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->remove('027c91341fd5cf4d2579b49c4b6a90da');

    $this->assertItemsInCart(0, $cart);
    $this->assertRowsInCart(0, $cart);

    Event::assertDispatched('cart.removed');
});

it('will remove the item if its quantity was set to zero', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

    $this->assertItemsInCart(0, $cart);
    $this->assertRowsInCart(0, $cart);

    Event::assertDispatched('cart.removed');
});

it('will remove the item if its quantity was set negative', function (): void {
    Event::fake();

    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cart->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

    $this->assertItemsInCart(0, $cart);
    $this->assertRowsInCart(0, $cart);

    Event::assertDispatched('cart.removed');
});

it('can get an item from the cart by its row id', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem)->toBeInstanceOf(CartItem::class);
});

it('can get the content of the cart', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $content = $cart->content();

    expect($content)->toBeInstanceOf(Collection::class);
    expect($content)->toHaveCount(2);
});

it('will return an empty collection if the cart is empty', function (): void {
    $cart = getCart();

    $content = $cart->content();

    expect($content)->toBeInstanceOf(Collection::class);
    expect($content)->toHaveCount(0);
});

it('will include the tax and subtotal when converted to an array', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $content = $cart->content();

    expect($content)->toBeInstanceOf(Collection::class);

    $first = $content->get('07d5da5550494c62daf9993cf954303f');
    expect($first)->not->toBeNull();
    expect($first->toArray())->toMatchArray([
        'rowId' => '07d5da5550494c62daf9993cf954303f',
        'id' => 1,
        'qty' => 1,
        'name' => 'First Cart item',
        'subtitle' => 'This is a simple description',
        'originalPrice' => 1000.0,
        'originalTotalPrice' => 1200.00,
        'originalVat' => 200.00,
        'price' => 1000.0,
        'totalPrice' => 1200.00,
        'vat' => 200.00,
        'vatLabel' => 'Iva Inclusa',
        'vatRate' => 20.00,
        'vatFcCode' => '0',
        'productFcCode' => '0',
        'discountValue' => 0.0,
        'urlImg' => 'https://ecommerce.test/images/item-name.png',
        'options' => ['size' => 'XL', 'color' => 'red'],
        'associatedModel' => null,
        'model' => null,
        'appliedCoupons' => [],
    ]);

    $second = $content->get('13e04d556bd1d42c1d940962999e405a');
    expect($second)->not->toBeNull();
    expect($second->toArray())->toMatchArray([
        'rowId' => '13e04d556bd1d42c1d940962999e405a',
        'id' => 2,
        'name' => 'Second Cart item',
        'subtitle' => 'This is a simple description',
        'qty' => 1,
        'originalPrice' => 1000.0,
        'originalTotalPrice' => 1200.00,
        'originalVat' => 200.00,
        'price' => 1000.00,
        'totalPrice' => 1200.00,
        'vat' => 200.00,
        'vatLabel' => 'Iva Inclusa',
        'vatRate' => 20.00,
        'vatFcCode' => '0',
        'productFcCode' => '0',
        'discountValue' => 0.0,
        'urlImg' => 'https://ecommerce.test/images/item-name.png',
        'options' => ['size' => 'XL', 'color' => 'red'],
        'associatedModel' => null,
        'model' => null,
        'appliedCoupons' => [],
    ]);
    expect($cart->totalVatLabel())->toEqual('Iva Inclusa');
});

it('can destroy a cart', function (): void {
    $cart = getCart();

    $cart->add(1, 'Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);

    $this->assertItemsInCart(1, $cart);

    $cart->destroy();

    $this->assertItemsInCart(0, $cart);
});

it('can get the total price of the cart content', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 10.00, 12.22, 2.22);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 10.00, 12.22, 2.22);

    $this->assertItemsInCart(3, $cart);
    expect($cart->total())->toEqualWithDelta(36.66, 0.0001);
    expect($cart->subtotal())->toEqual(30.00);
    expect($cart->vat())->toEqualWithDelta(6.66, 0.0001);
});

it('can return a formatted total', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00);

    $this->assertItemsInCart(3, $cart);
    // total() returns a float; use numberFormat() to get a locale-formatted string
    expect($cart->total())->toBe(3600.00);
    expect($cart->numberFormat($cart->total(), 2, ',', '.'))->toEqual('3.600,00');
});

it('excludes discount cart items from subtotal', function (): void {
    $cart = getCart();

    $cart->add(1, 'Test Item', 'Description', 1, 100.00, 122.00, 22.00);

    expect($cart->subtotal())->toEqual(100.00);

    $cart->applyCoupon(null, 'GLOBAL_DISCOUNT', 'fixed', 10.00);

    expect($cart->subtotal())->toEqual(100.00);

    $discountItem = $cart->search(function ($cartItem): bool {
        return $cartItem->name === 'discountCartItem';
    })->first();

    expect($discountItem)->not->toBeNull();
});

it('can search the cart for a specific item', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00);

    $cartItem = $cart->search(function ($cartItem, $rowId): bool {
        return $cartItem->name == 'Second Cart item';
    });

    expect($cartItem)->toBeInstanceOf(Collection::class);
    expect($cartItem)->toHaveCount(1);
    expect($cartItem->first())->toBeInstanceOf(CartItem::class);
    expect($cartItem->first()->id)->toEqual(2);
});

it('can search the cart for a specific item with options', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '', '', '', null, null, ['color' => 'blue']);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00, '', '', '', null, null, ['color' => 'red']);

    $cartItem = $cart->search(function ($cartItem, $rowId): bool {
        return $cartItem->options->color == 'red';
    });

    expect($cartItem)->toBeInstanceOf(Collection::class);
    expect($cartItem)->toHaveCount(1);
    expect($cartItem->first())->toBeInstanceOf(CartItem::class);
    expect($cartItem->first()->id)->toEqual(2);
});

it('will associate the cart item with a model when you add a buyable', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00)
        ->associate('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel');

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->associatedModel)->toEqual('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel');
});

it('can associate the cart item with a model', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00);

    $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->associatedModel)->toEqual('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel');
});

it('will throw an exception when a non existing model is being associated', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00);

    expect(fn () => $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel'))
        ->toThrow(UnknownModelException::class, 'The supplied model SomeModel does not exist.');
});

it('can get the associated model of a cart item', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00);

    $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel);

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->model)->toBeInstanceOf(ProductModel::class);
    expect($cartItem->model->someValue)->toEqual('Some value');
});

it('can calculate the subtotal of a cart item', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->totalPrice)->toEqual(1200.00);
    expect($cartItem->price)->toEqual(1000.00);
    expect($cartItem->vat)->toEqual(200.00);
});

it('can return a formatted subtotal', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cart->subtotal())->toEqual('3000');
    expect($cart->vat())->toEqual('600.0');
    expect($cart->total())->toEqual('3600.0');
});

it('can return the calculated vat', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->vat)->toEqual(200.00);
    expect($cart->vat())->toEqual(600.00);
});

it('can calculate the total tax for all cart items', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00);

    expect($cart->vat())->toEqual(1000.00);
});

it('can return the subtotal', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00);

    expect($cart->subtotal)->toEqual(5000.00);
});

it('can return formatted subtotal', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 2, 1000.00, 1200.00, 200.00);

    // subtotal() returns a float; format with numberFormat()
    expect($cart->subtotal())->toBe(5000.00);
    expect($cart->numberFormat($cart->subtotal(), 2, ',', ''))->toEqual('5000,00');
});

it('can return cart formatted numbers by config values', function (): void {
    setConfigFormat(2, ',', '');

    $cart = getCart();

    // Add 1 + 2 = 3 units of the same product (qty is merged)
    $cart->add(new BuyableProduct(1, 'Some title', 'This is a simple description', 1, 1000, 1220, 220), 1);
    $cart->add(new BuyableProduct(1, 'Some title', 'This is a simple description', 1, 1000, 1220, 220), 2);

    // subtotal(), vat(), total() always return floats; magic getters do the same
    expect($cart->subtotal())->toBe(3000.0);
    expect($cart->vat())->toBe(660.0);
    expect($cart->total())->toBe(3660.0);

    // Magic getter aliases
    expect($cart->subtotal)->toBe(3000.0);
    expect($cart->tax)->toBe(660.0);
    expect($cart->total)->toBe(3660.0);

    // numberFormat() respects the config decimal_point and thousand_separator
    expect($cart->numberFormat($cart->subtotal(), null, null, null))->toBe('3000,00');
    expect($cart->numberFormat($cart->total(), null, null, null))->toBe('3660,00');
});

it('can return cart item formatted numbers by config values', function (): void {
    setConfigFormat(2, ',', '');

    $cart = getCart();

    $cart->add(new BuyableProduct(1, 'Some title', 'This is a simple description', 2, 10.00, 12.22, 2.22), 2);

    $cartItem = $cart->content()->first();

    // CartItem exposes properties, not methods; format them with numberFormat()
    expect($cartItem->numberFormat($cartItem->price, 2, ',', ''))->toEqual('10,00');
    expect($cartItem->numberFormat($cartItem->vat, 2, ',', ''))->toEqual('2,22');
    expect($cartItem->numberFormat($cartItem->totalPrice, 2, ',', ''))->toEqual('12,22');

    // Computed magic getters
    expect($cartItem->numberFormat($cartItem->subtotal, 2, ',', ''))->toEqual('20,00');
    expect($cartItem->numberFormat($cartItem->total, 2, ',', ''))->toEqual('20,00');
});

it('can store the cart in a database', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    Event::fake();

    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cart->store($identifier = 123);

    $serialized = serialize($cart->content());

    $this->assertDatabaseHas('cart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

    Event::assertDispatched('cart.stored');
});

it('will throw an exception when a cart was already stored using the specified identifier', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    Event::fake();

    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cart->store($identifier = 123);

    expect(fn () => $cart->store($identifier))
        ->toThrow(CartAlreadyStoredException::class, 'A cart with identifier 123 was already stored.');

    Event::assertDispatched('cart.stored');
});

it('can restore a cart from the database', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    Event::fake();

    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00);

    $cart->store($identifier = 123);

    $cart->destroy();

    $this->assertItemsInCart(0, $cart);

    $cart->restore($identifier);

    $this->assertItemsInCart(3, $cart);

    $this->assertDatabaseMissing('cart', ['identifier' => $identifier, 'instance' => 'default']);

    Event::assertDispatched('cart.restored');
});

it('will just keep the current instance if no cart with the given identifier was stored', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $cart = getCart();

    $cart->restore($identifier = 123);

    $this->assertItemsInCart(0, $cart);
});

it('can calculate all values', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 3, 1000.00, 1200.00, 200.00, '0', '10', 'https://ecommerce.test/images/item-name.png');

    $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

    expect($cartItem->name)->toEqual('First Cart item');
    expect($cartItem->subtitle)->toEqual('This is a simple description');
    expect($cartItem->qty)->toEqual(3);
    expect($cartItem->price)->toEqual(1000);
    expect($cartItem->totalPrice)->toEqual(1200.00);
    expect($cartItem->vat)->toEqual(200.00);
    expect($cartItem->vatFcCode)->toEqual('0');
    expect($cartItem->productFcCode)->toEqual('10');
    expect($cartItem->urlImg)->toEqual('https://ecommerce.test/images/item-name.png');

    expect($cart->subtotal())->toEqual(3000);
    expect($cart->total())->toEqual(3600.00);
    expect($cart->vat())->toEqual(600.00);

    $cartItem->applyCoupon('BLACK_FRIDAY_PERCENTAGE_2021', 'fixed', 500000);
    expect($cart->total())->toEqual(0.0);
});

it('will destroy the cart when the user logs out and the config setting was set to true', function (): void {
    $this->app['config']->set('cart.destroy_on_logout', true);

    $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock): void {
        $mock->shouldReceive('forget')->once()->with('cart');
    }));

    $user = Mockery::mock(Authenticatable::class);

    event(new Logout('', $user));
});

it('will add a fixed coupon to a cart item', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_FIXED_2021', 'fixed', 100);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(1);
    expect($cart->coupons()['BLACK_FRIDAY_FIXED_2021'])->toEqual((object) [
        'rowId' => '07d5da5550494c62daf9993cf954303f',
        'couponCode' => 'BLACK_FRIDAY_FIXED_2021',
        'couponType' => 'fixed',
        'couponValue' => 100,
    ]);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
    expect($coupon->couponCode)->toEqual('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toEqual('fixed');
    expect($coupon->couponValue)->toEqual(100);
    expect($cartItem->price)->toEqual(916.67);
    expect($cartItem->vat)->toEqual(183.33);
    expect($cartItem->totalPrice)->toEqual(1100.00);
    expect($cartItem->discountValue)->toEqual(100);
});

it('will add a percentage coupon to a cart item', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(1);
    expect($cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021'])->toEqual((object) [
        'rowId' => '07d5da5550494c62daf9993cf954303f',
        'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
        'couponType' => 'percentage',
        'couponValue' => 50,
    ]);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
    expect($coupon->couponCode)->toEqual('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponType)->toEqual('percentage');
    expect($coupon->couponValue)->toEqual(50);
    expect($cartItem->price)->toEqual(500.00);
    expect($cartItem->vat)->toEqual(100.00);
    expect($cartItem->totalPrice)->toEqual(600.00);
    expect($cartItem->discountValue)->toEqual(600.00);
});

it('can remove a coupon after product was removed from cart', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cart->coupons())->toHaveCount(1);

    $cart->remove($cartItem->rowId);

    expect($cart->coupons())->toBeEmpty();
});

it('can detach a coupon of a cart item', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(1);
    expect($cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021'])->toEqual((object) [
        'rowId' => '07d5da5550494c62daf9993cf954303f',
        'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
        'couponType' => 'percentage',
        'couponValue' => 50,
    ]);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
    expect($coupon->couponCode)->toEqual('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponType)->toEqual('percentage');
    expect($coupon->couponValue)->toEqual(50);
    expect($cartItem->price)->toEqual(500.00);
    expect($cartItem->vat)->toEqual(100.00);
    expect($cartItem->totalPrice)->toEqual(600.00);
    expect($cartItem->discountValue)->toEqual(600.00);

    $cart->detachCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021');

    expect($cart->coupons())->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cart->coupons())->toBeEmpty();

    $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

    expect($cartItem->appliedCoupons)->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cartItem->appliedCoupons)->toBeEmpty();
});

it('can detect if has coupons', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(1);
    expect($cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021'])->toEqual((object) [
        'rowId' => '07d5da5550494c62daf9993cf954303f',
        'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
        'couponType' => 'percentage',
        'couponValue' => 50,
    ]);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
    expect($coupon->couponCode)->toEqual('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponType)->toEqual('percentage');
    expect($coupon->couponValue)->toEqual(50);
    expect($cartItem->price)->toEqual(500.00);
    expect($cartItem->vat)->toEqual(100.00);
    expect($cartItem->totalPrice)->toEqual(600.00);
    expect($cartItem->discountValue)->toEqual(600.00);

    expect($cart->hasCoupons())->toBeTrue();
    expect($cartItem->hasCoupons())->toBeTrue();

    $cart->detachCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021');

    expect($cart->coupons())->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cart->coupons())->toBeEmpty();
    expect($cart->hasCoupons())->toBeFalse();

    $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

    expect($cartItem->appliedCoupons)->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cartItem->appliedCoupons)->toBeEmpty();
    expect($cartItem->hasCoupons())->toBeFalse();
});

it('can return all applied coupons', function (): void {
    $cart = getCart();
    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);
    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_FIXED_2021', 'fixed', 10);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(2);
    expect($cart->coupons())->toEqual([
        'BLACK_FRIDAY_PERCENTAGE_2021' => (object) [
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
            'couponType' => 'percentage',
            'couponValue' => 50,
        ],
        'BLACK_FRIDAY_FIXED_2021' => (object) [
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'couponCode' => 'BLACK_FRIDAY_FIXED_2021',
            'couponType' => 'fixed',
            'couponValue' => 10,
        ],
    ]);
});

it('can return a coupon by its code', function (): void {
    $cart = getCart();
    $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);
    $cart->applyCoupon('07d5da5550494c62daf9993cf954303f', 'BLACK_FRIDAY_FIXED_2021', 'fixed', 10);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(2);
    expect($cart->coupons())->toEqual([
        'BLACK_FRIDAY_PERCENTAGE_2021' => (object) [
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
            'couponType' => 'percentage',
            'couponValue' => 50,
        ],
        'BLACK_FRIDAY_FIXED_2021' => (object) [
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'couponCode' => 'BLACK_FRIDAY_FIXED_2021',
            'couponType' => 'fixed',
            'couponValue' => 10,
        ],
    ]);

    $coupon = $cart->getCoupon('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponCode)->toEqual('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponType)->toEqual('percentage');
    expect($coupon->couponValue)->toEqual(50);
});

it('can calculate cart totals after applied item coupon', function (): void {
    $cart = getCart();

    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);
    $cartItem = $cart->add(1, 'First Cart item', 'This is a simple description', 1, 1000.00, 1200.00, 200.00, '0', '0', 'https://ecommerce.test/images/item-name.png', null, null, ['size' => 'XL', 'color' => 'red']);

    $this->assertItemsInCart(2, $cart);
    $this->assertRowsInCart(1, $cart);

    $cart->applyCoupon($cartItem->rowId, 'BLACK_FRIDAY_FIXED_2021', 'fixed', 400);

    expect($cart->coupons())->toBeArray();
    expect($cart->coupons())->toHaveCount(1);
    expect($cart->subtotal())->toEqual(1666.67);
    expect($cart->total())->toEqual(2000);
    expect($cart->vat())->toEqual(333.33);
});

it('can set and get options on cart', function (): void {
    $cart = getCart();
    $cart->setOptions(['test' => 'test']);

    expect($cart->getOptions())->toEqual(['test' => 'test']);

    $cart->getOptionsByKey('test');
    expect($cart->getOptionsByKey('test'))->toEqual('test');

    $cart->getOptionsByKey('option_not_existing_with_default_value', false);
    expect($cart->getOptionsByKey('option_not_existing_with_default_value'))->toEqual(false);

    $cart->getOptionsByKey('option_not_existing_without_default_value');
    expect($cart->getOptionsByKey('option_not_existing_without_default_value'))->toBeNull();
});

it('will remove options when cart is destroyed', function (): void {
    $cart = getCart();
    $cart->setOptions(['test' => 'test']);

    expect($cart->getOptions())->toEqual(['test' => 'test']);

    $cart->destroy();

    expect($cart->getOptions())->toEqual([]);
});

it('can calculate original total price with decimals', function (): void {
    $cart = getCart();

    $cart->add(1, 'Test Item 1', 'This is a simple description', 2, 50.00, 100.00, 20.00);
    $cart->add(2, 'Test Item 2', 'Another item', 1, 30.00, 30.00, 6.00);

    $totalPrice = $cart->originalTotalPrice(2);

    expect($totalPrice)->toEqual(230.0);
});

it('formats numbers correctly', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Item 1', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formattedPrice = $cartItem->numberFormat(1000.00, 2, '.', ',');
    expect($formattedPrice)->toEqual('1,000.00');
});

it('can detach a coupon from cart item', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $cart->applyCoupon('027c91341fd5cf4d2579b49c4b6a90da', 'BLACK_FRIDAY_FIXED_2021', 'fixed', 10);

    $cart->detachCoupon('027c91341fd5cf4d2579b49c4b6a90da', 'BLACK_FRIDAY_FIXED_2021');

    expect($cartItem->price)->toEqual(1000.00);
});

it('can check if cart has coupons', function (): void {
    $cart = getCart();
    $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    expect($cart->hasCoupons())->toBeFalse();

    $cart->applyCoupon('027c91341fd5cf4d2579b49c4b6a90da', 'BLACK_FRIDAY', 'percentage', 50);

    expect($cart->hasCoupons())->toBeTrue();
});

it('can format float values', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formattedValue = $cartItem->formatFloat(1200.00);
    expect($formattedValue)->toEqual(1200.00);
});

it('can be bought trait returns identifier using get key', function (): void {
    $product = new Fixtures\ProductWithTrait(123);

    $identifier = $product->getBuyableIdentifier();

    expect($identifier)->toEqual(123);
});

it('can be bought trait returns identifier using id property', function (): void {
    $product = new class
    {
        use CanBeBought;

        public $id = 456;
    };

    $identifier = $product->getBuyableIdentifier();

    expect($identifier)->toEqual(456);
});

it('can be bought trait returns description from name property', function (): void {
    $product = new Fixtures\ProductWithTrait(1, 'Product Name');

    $description = $product->getBuyableDescription();

    expect($description)->toEqual('Product Name');
});

it('can be bought trait returns description from title property', function (): void {
    $product = new class
    {
        use CanBeBought;

        public $id = 1;

        public $title = 'Product Title';
    };

    $description = $product->getBuyableDescription();

    expect($description)->toEqual('Product Title');
});

it('can be bought trait returns description from description property', function (): void {
    $product = new class
    {
        use CanBeBought;

        public $id = 1;

        public $description = 'Product Description';
    };

    $description = $product->getBuyableDescription();

    expect($description)->toEqual('Product Description');
});

it('can be bought trait returns null when no description property exists', function (): void {
    $product = new class
    {
        use CanBeBought;

        public $id = 1;
    };

    $description = $product->getBuyableDescription();

    expect($description)->toBeNull();
});

it('can be bought trait returns price from price property', function (): void {
    $product = new Fixtures\ProductWithTrait(1, null, null, null, 99.99);

    $price = $product->getBuyablePrice();

    expect($price)->toEqual(99.99);
});

it('can be bought trait returns null when no price property exists', function (): void {
    $product = new class
    {
        use CanBeBought;

        public $id = 1;
    };

    $price = $product->getBuyablePrice();

    expect($price)->toBeNull();
});

it('cart item number format formats value correctly', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formatted = $cartItem->numberFormat(1234.56, 2, '.', ',');

    expect($formatted)->toEqual('1,234.56');
});

it('cart item number format handles different decimal separators', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formatted = $cartItem->numberFormat(1234.56, 2, ',', '.');

    expect($formatted)->toEqual('1.234,56');
});

it('cart item number format handles different decimal places', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formatted = $cartItem->numberFormat(1234.5678, 3, '.', ',');

    expect($formatted)->toEqual('1,234.568');
});

it('cart item number format handles zero decimal places', function (): void {
    $cart = getCart();
    $cartItem = $cart->add(1, 'Test Item', 'Description', 1, 1000.00, 1200.00, 200.00);

    $formatted = $cartItem->numberFormat(1234.56, 0, '.', ',');

    expect($formatted)->toEqual('1,235');
});

it('can format number using cart helper', function (): void {
    $cart = getCart();

    $formatted = $cart->numberFormat(1234.5678, 2, '.', ',');

    expect($formatted)->toEqual('1,234.57');
});

it('can format number using config defaults', function (): void {
    $cart = getCart();

    $formatted = $cart->numberFormat(1234.5678, null, null, null);

    expect($formatted)->toEqual('1,234.57');
});

it('can apply a global fixed coupon', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 2, 100.00, 120.00, 20.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 1, 50.00, 60.00, 10.00);

    $initialTotal = $cart->total();
    expect($initialTotal)->toEqual(300.00);

    $cart->applyCoupon(null, 'GLOBAL_FIXED_2024', 'fixed', 50.00);

    expect($cart->hasCoupons())->toBeTrue();
    expect($cart->hasGlobalCoupon())->toBeTrue();

    $coupons = $cart->coupons();
    expect($coupons)->toHaveCount(1);

    $finalTotal = $cart->total();
    expect($finalTotal)->toEqual(250.00);
});

it('can apply a global percentage coupon', function (): void {
    $cart = getCart();

    $cart->add(1, 'First Cart item', 'This is a simple description', 2, 100.00, 120.00, 20.00);
    $cart->add(2, 'Second Cart item', 'This is a simple description', 1, 50.00, 60.00, 10.00);

    $initialTotal = $cart->total();
    expect($initialTotal)->toEqual(300.00);

    $cart->applyCoupon(null, 'GLOBAL_PERCENTAGE_2024', 'percentage', 15);

    expect($cart->hasCoupons())->toBeTrue();
    expect($cart->hasGlobalCoupon())->toBeTrue();

    $coupons = $cart->coupons();
    expect($coupons)->toHaveCount(1);

    $finalTotal = $cart->total();

    expect($finalTotal)->toBeLessThan($initialTotal);
    expect($finalTotal)->toBeGreaterThan(0);
});

it('can access totals via magic properties', function (): void {
    $cart = getCart();

    $cart->add(1, 'Test Item', 'Description', 2, 50.00, 60.00, 10.00);

    expect($cart->total)->toEqual($cart->total());
    expect($cart->total)->toEqual(120.00);

    expect($cart->tax)->toEqual($cart->vat());
    expect($cart->tax)->toEqual(20.00);

    expect($cart->subtotal)->toEqual($cart->subtotal());
    expect($cart->subtotal)->toEqual(100.00);

    expect($cart->nonExistentProperty)->toBeNull();
});

it('can add a buyable item with quantity', function (): void {
    $cart = getCart();
    $buyable = new BuyableProduct(1, 'Test Product', 'Subtitle', 1, 10.00);

    $cartItem = $cart->add($buyable, 5);

    expect($cartItem->qty)->toEqual(5);
    expect($cartItem->name)->toEqual('Test Product');
    expect($cartItem->price)->toEqual(10.00);

    expect($cartItem->associatedModel)->toEqual(BuyableProduct::class);
    expect($cartItem->model)->toBe($buyable);
});

it('removes discount cart item when detaching global coupon', function (): void {
    $cart = getCart();
    $cart->add(1, 'Item', 'Desc', 1, 100.00, 100.00, 20.00);

    $cart->applyCoupon(null, 'GLOBAL_COUPON', 'fixed', 10.00);

    $discountItem = $cart->search(function ($item): bool {
        return $item->name === 'discountCartItem';
    })->first();

    expect($discountItem)->not->toBeNull();
    expect($discountItem->appliedCoupons)->toHaveKey('GLOBAL_COUPON');

    $cart->detachCoupon($discountItem->rowId, 'GLOBAL_COUPON');

    $discountItemAfter = $cart->search(function ($item): bool {
        return $item->name === 'discountCartItem';
    })->first();

    expect($discountItemAfter)->toBeNull();
});

it('returns false for has global coupon when no global coupon is applied', function (): void {
    $cart = getCart();

    expect($cart->hasGlobalCoupon())->toBeFalse();

    $cartItem = $cart->add(1, 'Item', 'Desc', 1, 100.00, 100.00, 20.00);
    $cart->applyCoupon($cartItem->rowId, 'ITEM_COUPON', 'fixed', 10.00);

    expect($cart->hasCoupons())->toBeTrue();
    expect($cart->hasGlobalCoupon())->toBeFalse();
});

it('stores createdAt and updatedAt on a cart item', function (): void {
    $before = Carbon::now()->subSecond();
    $cart = getCart();
    $cart->add('1', 'Product', 'Subtitle', 1, 10.00, 10.00, 1, 'FC1', 'PC1', 'img.jpg');
    $item = $cart->content()->first();

    expect($item->createdAt)->toBeInstanceOf(Carbon::class);
    expect($item->updatedAt)->toBeInstanceOf(Carbon::class);
    expect($item->createdAt->greaterThanOrEqualTo($before))->toBeTrue();
});

it('accepts explicit createdAt and updatedAt on add', function (): void {
    $createdAt = Carbon::parse('2024-01-01 12:00:00');
    $updatedAt = Carbon::parse('2024-06-01 12:00:00');
    $cart = getCart();
    $cart->add('1', 'Product', 'Subtitle', 1, 10.00, 10.00, 1, 'FC1', 'PC1', 'img.jpg', $createdAt, $updatedAt);
    $item = $cart->content()->first();

    expect($item->createdAt->eq($createdAt))->toBeTrue();
    expect($item->updatedAt->eq($updatedAt))->toBeTrue();
});

it('isAlreadyAdded returns true when item with matching id and model is in cart', function (): void {
    $cart = getCart();
    $cart->add('42', 'Product', 'Subtitle', 1, 10.00, 10.00, 1, 'FC1', 'PC1', 'img.jpg');
    $rowId = $cart->content()->keys()->first();
    $cart->associate($rowId, ProductModel::class);

    expect($cart->isAlreadyAdded('42', ProductModel::class))->toBeTrue();
    expect($cart->isAlreadyAdded('99', ProductModel::class))->toBeFalse();
});

it('isAlreadyAdded returns false for empty cart', function (): void {
    $cart = getCart();

    expect($cart->isAlreadyAdded('1', ProductModel::class))->toBeFalse();
});

it('searchById returns the CartItem when found', function (): void {
    $cart = getCart();
    $cart->add('10', 'Product', 'Subtitle', 1, 10.00, 10.00, 1, 'FC1', 'PC1', 'img.jpg');
    $rowId = $cart->content()->keys()->first();
    $cart->associate($rowId, ProductModel::class);

    $found = $cart->searchById('10', ProductModel::class);

    expect($found)->toBeInstanceOf(CartItem::class);
    expect($found->id)->toEqual('10');
});

it('searchById returns null when model does not match', function (): void {
    $cart = getCart();
    $cart->add('10', 'Product', 'Subtitle', 1, 10.00, 10.00, 1, 'FC1', 'PC1', 'img.jpg');

    expect($cart->searchById('10', 'NonExistentModel'))->toBeNull();
});

it('searchById returns null for empty cart', function (): void {
    $cart = getCart();

    expect($cart->searchById('999', ProductModel::class))->toBeNull();
});
