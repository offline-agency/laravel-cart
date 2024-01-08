<?php

namespace OfflineAgency\LaravelCart\Tests;

use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Application;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Mockery;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartServiceProvider;
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel;
use Orchestra\Testbench\TestCase;
use TypeError;

class CartTest extends TestCase
{
    use CartAssertions;

    /**
     * Set the package service provider.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cart.database.connection', 'testing');

        $app['config']->set('session.driver', 'array');

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->afterResolving('migrator', function ($migrator) {
            $migrator->path(realpath(__DIR__.'/../database/migrations'));
        });
    }

    /** @test */
    public function it_has_a_default_instance()
    {
        $cart = $this->getCart();

        $this->assertEquals(Cart::DEFAULT_INSTANCE, $cart->currentInstance());
    }

    /** @test */
    public function it_can_have_multiple_instances()
    {
        $cart = $this->getCart();

        $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->instance('wishlist')->add(
            1,
            'Wishlist item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertItemsInCart(1, $cart->instance(Cart::DEFAULT_INSTANCE));
        $this->assertItemsInCart(1, $cart->instance('wishlist'));
    }

    /** @test */
    public function it_can_add_an_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_the_cart_item_of_the_added_item()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('027c91341fd5cf4d2579b49c4b6a90da', $cartItem->rowId);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_buyable_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_will_return_an_array_of_cart_items_when_you_add_multiple_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItems = $cart->addBatch(
            [
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
            ]
        );

        $this->assertInstanceOf(Collection::class, $cartItems);
        $this->assertCount(2, $cartItems);
        $this->assertContainsOnlyInstancesOf(CartItem::class, $cartItems);

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_attributes()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->addBatch(
            [
                [
                    'id' => 1,
                    'name' => 'Cart item',
                    'subtitle' => 'This is a simple description',
                    'qty' => 1,
                    'price' => 10.00,
                    'totalPrice' => 12.22,
                    'vat' => 2.22,
                ],
            ]
        );

        $this->assertEquals(1, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_multiple_array_items_at_once()
    {
        Event::fake();

        $cart = $this->getCart();

        $cart->addBatch(
            [
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
            ]
        );

        $this->assertEquals(2, $cart->count());

        Event::assertDispatched('cart.added');
    }

    /** @test */
    public function it_can_add_an_item_with_options()
    {
        Event::fake();

        $cart = $this->getCart();

        $options = ['size' => 'XL', 'color' => 'red'];

        $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22,
            '',
            '',
            '',
            $options
        );

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        $this->assertInstanceOf(CartItem::class, $cartItem);
        $this->assertEquals('XL', $cartItem->options->size);
        $this->assertEquals('red', $cartItem->options->color);

        Event::assertDispatched('cart.added');
    }

    /**
     * @test
     */
    public function it_will_validate_the_identifier()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Please supply a valid identifier.');

        $cart = $this->getCart();

        $cart->add(
            null,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
    }

    /**
     * @test
     */
    public function it_will_validate_the_quantity()
    {
        $this->expectException(TypeError::class);
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            null,
            10.00,
            12.22,
            2.22
        );
    }

    /**
     * @test
     */
    public function it_will_validate_the_price()
    {
        $this->expectException(TypeError::class);
        $cart = $this->getCart();

        $cart->add(
            null,
            'Cart item',
            'This is a simple description',
            1,
            null
        );
    }

    /** @test */
    public function it_will_update_the_cart_if_the_item_already_exists_in_the_cart()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_will_keep_updating_the_quantity_when_an_item_is_added_multiple_times()
    {
        $cart = $this->getCart();

        $item = new BuyableProduct();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertItemsInCart(3, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_update_the_quantity_of_an_existing_item_in_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 2);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_a_buyable()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', new BuyableProduct(1, 'Different description'));

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    /** @test */
    public function it_can_update_an_existing_item_in_the_cart_from_an_array()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', ['name' => 'Different description']);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('Different description', $cart->get('027c91341fd5cf4d2579b49c4b6a90da')->name);

        Event::assertDispatched('cart.updated');
    }

    /**
     * @test
     */
    public function it_will_throw_an_exception_if_a_row_id_was_not_found()
    {
        $this->expectException(InvalidRowIDException::class);
        $this->expectExceptionMessage('The cart does not contain rowId none-existing-row-id');

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('none-existing-row-id', 2);
    }

    /** @test */
    public function it_will_regenerate_the_row_id_if_the_options_changed()
    {
        $this->markTestIncomplete();
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 1);

        $this->assertItemsInCart(1, $cart);
        $this->assertEquals('7e70a1e9aaadd18c72921a07aae5d011', $cart->content()->first()->rowId);
        $this->assertEquals('blue', $cart->get('7e70a1e9aaadd18c72921a07aae5d011')->options->color);
    }

    /** @test */
    public function it_will_add_the_item_to_an_existing_row_if_the_options_changed_to_an_existing_row_id()
    {
        $this->markTestIncomplete();
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('7e70a1e9aaadd18c72921a07aae5d011', ['options' => ['color' => 'red']]);

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);
    }

    /** @test */
    public function it_can_remove_an_item_from_the_cart()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->remove('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_to_zero()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', 0);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_will_remove_the_item_if_its_quantity_was_set_negative()
    {
        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cart->update('027c91341fd5cf4d2579b49c4b6a90da', -1);

        $this->assertItemsInCart(0, $cart);
        $this->assertRowsInCart(0, $cart);

        Event::assertDispatched('cart.removed');
    }

    /** @test */
    public function it_can_get_an_item_from_the_cart_by_its_row_id()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(CartItem::class, $cartItem);
    }

    /** @test */
    public function it_can_get_the_content_of_the_cart()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(2, $content);
    }

    /** @test */
    public function it_will_return_an_empty_collection_if_the_cart_is_empty()
    {
        $cart = $this->getCart();

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertCount(0, $content);
    }

    /** @test */
    public function it_will_include_the_tax_and_subtotal_when_converted_to_an_array()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $content = $cart->content();

        $this->assertInstanceOf(Collection::class, $content);
        $this->assertEquals([
            '07d5da5550494c62daf9993cf954303f' => [
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
                'options' => [
                    'size' => 'XL',
                    'color' => 'red',
                ],
                'associatedModel' => null,
                'model' => null,
                'appliedCoupons' => [],
            ],
            '13e04d556bd1d42c1d940962999e405a' => [
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
                'options' => [
                    'size' => 'XL',
                    'color' => 'red',
                ],
                'associatedModel' => null,
                'model' => null,
                'appliedCoupons' => [],
            ],
        ], $content->toArray());
    }

    /** @test */
    public function it_can_destroy_a_cart()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );

        $this->assertItemsInCart(1, $cart);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_get_the_total_price_of_the_cart_content()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            10.00,
            12.22,
            2.22
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            10.00,
            12.22,
            2.22
        );

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals(36.66, $cart->total());
        $this->assertEquals(30.00, $cart->subtotal());
        $this->assertEquals(6.66, $cart->vat());
    }

    /** @test */
    public function it_can_return_a_formatted_total()
    {
        $this->markTestIncomplete();
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00
        );

        $this->assertItemsInCart(3, $cart);
        $this->assertEquals('3.600,00', $cart->total(2, ',', '.'));
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00
        );

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->name == 'Second Cart item';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(2, $cartItem->first()->id);
    }

    /** @test */
    public function it_can_search_the_cart_for_a_specific_item_with_options()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '',
            '',
            '',
            [
                'color' => 'blue',
            ]
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00,
            '',
            '',
            '',
            [
                'color' => 'red',
            ]
        );

        $cartItem = $cart->search(function ($cartItem, $rowId) {
            return $cartItem->options->color == 'red';
        });

        $this->assertInstanceOf(Collection::class, $cartItem);
        $this->assertCount(1, $cartItem);
        $this->assertInstanceOf(CartItem::class, $cartItem->first());
        $this->assertEquals(2, $cartItem->first()->id);
    }

    /** @test */
    public function it_will_associate_the_cart_item_with_a_model_when_you_add_a_buyable()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        )->associate('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel');

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel', $cartItem->associatedModel);
    }

    /** @test */
    public function it_can_associate_the_cart_item_with_a_model()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        );

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel', $cartItem->associatedModel);
    }

    /**
     * @test
     */
    public function it_will_throw_an_exception_when_a_non_existing_model_is_being_associated()
    {
        $this->expectException(UnknownModelException::class);
        $this->expectExceptionMessage('The supplied model SomeModel does not exist.');
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        );

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', 'SomeModel');
    }

    /** @test */
    public function it_can_get_the_associated_model_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00
        );

        $cart->associate('027c91341fd5cf4d2579b49c4b6a90da', new ProductModel());

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertInstanceOf(ProductModel::class, $cartItem->model);
        $this->assertEquals('Some value', $cartItem->model->someValue);
    }

    /** @test */
    public function it_can_calculate_the_subtotal_of_a_cart_item()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(1200.00, $cartItem->totalPrice);
        $this->assertEquals(1000.00, $cartItem->price);
        $this->assertEquals(200.00, $cartItem->vat);
    }

    /** @test */
    public function it_can_return_a_formatted_subtotal()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('3000', $cart->subtotal());
        $this->assertEquals('600.0', $cart->vat());
        $this->assertEquals('3600.0', $cart->total());
    }

    /** @test */
    public function it_can_return_the_calculated_vat()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals(200.00, $cartItem->vat);
        $this->assertEquals(600.00, $cart->vat());
    }

    /** @test */
    public function it_can_calculate_the_total_tax_for_all_cart_items()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00
        );

        $this->assertEquals(1000.00, $cart->vat());
    }

    /** @test */
    public function it_can_return_the_subtotal()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00
        );

        $this->assertEquals(5000.00, $cart->subtotal);
    }

    /** @test */
    public function it_can_return_formatted_subtotal()
    {
        $this->markTestIncomplete();
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );
        $cartItem = $cart->add(
            2,
            'Second Cart item',
            'This is a simple description',
            2,
            1000.00,
            1200.00,
            200.00
        );

        $this->assertEquals('5000,00', $cart->subtotal(2, ',', ''));
    }

    /** @test */
    public function it_can_return_cart_formatted_numbers_by_config_values()
    {
        $this->markTestIncomplete();
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(
            1,
            'Some title',
            'This is a simple description',
            1,
            1000,
            1220,
            220
        ), 1);
        $cart->add(new BuyableProduct(
            1,
            'Some title',
            'This is a simple description',
            1,
            1000,
            1220,
            220
        ), 2);

        $this->assertEquals('2000,00', $cart->subtotal());
        $this->assertEquals('1050,00', $cart->vat());
        $this->assertEquals('6050,00', $cart->total());

        $this->assertEquals('5000,00', $cart->subtotal);
        $this->assertEquals('1050,00', $cart->vat);
        $this->assertEquals('6050,00', $cart->total);
    }

    /** @test */
    public function it_can_return_cartItem_formatted_numbers_by_config_values()
    {
        $this->markTestIncomplete();
        $this->setConfigFormat(2, ',', '');

        $cart = $this->getCart();

        $cart->add(new BuyableProduct(
            1,
            'Some title',
            'my description'
        ), 2);

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('10,00', $cartItem->price());
        $this->assertEquals('0,00', $cartItem->priceTax());
        $this->assertEquals('0,00', $cartItem->subtotal());
        $this->assertEquals('0,00', $cartItem->total());
        $this->assertEquals('0,00', $cartItem->vat());
        $this->assertEquals('840,00', $cartItem->taxTotal());
    }

    /** @test */
    public function it_can_store_the_cart_in_a_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cart->store($identifier = 123);

        $serialized = serialize($cart->content());

        $this->assertDatabaseHas('cart', ['identifier' => $identifier, 'instance' => 'default', 'content' => $serialized]);

        Event::assertDispatched('cart.stored');
    }

    /**
     * @test
     */
    public function it_will_throw_an_exception_when_a_cart_was_already_stored_using_the_specified_identifier()
    {
        $this->expectException(CartAlreadyStoredException::class);
        $this->expectExceptionMessage('A cart with identifier 123 was already stored.');
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cart->store($identifier = 123);

        $cart->store($identifier);

        Event::assertDispatched('cart.stored');
    }

    /** @test */
    public function it_can_restore_a_cart_from_the_database()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        Event::fake();

        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00
        );

        $cart->store($identifier = 123);

        $cart->destroy();

        $this->assertItemsInCart(0, $cart);

        $cart->restore($identifier);

        $this->assertItemsInCart(3, $cart);

        $this->assertDatabaseMissing('cart', ['identifier' => $identifier, 'instance' => 'default']);

        Event::assertDispatched('cart.restored');
    }

    /** @test */
    public function it_will_just_keep_the_current_instance_if_no_cart_with_the_given_identifier_was_stored()
    {
        $this->artisan('migrate', [
            '--database' => 'testing',
        ]);

        $cart = $this->getCart();

        $cart->restore($identifier = 123);

        $this->assertItemsInCart(0, $cart);
    }

    /** @test */
    public function it_can_calculate_all_values()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            3,
            1000.00,
            1200.00,
            200.00,
            '0',
            '10',
            'https://ecommerce.test/images/item-name.png'
        );

        $cartItem = $cart->get('027c91341fd5cf4d2579b49c4b6a90da');

        $this->assertEquals('First Cart item', $cartItem->name);
        $this->assertEquals('This is a simple description', $cartItem->subtitle);
        $this->assertEquals(3, $cartItem->qty);
        $this->assertEquals(1000, $cartItem->price);
        $this->assertEquals(1200.00, $cartItem->totalPrice);
        $this->assertEquals(200.00, $cartItem->vat);
        $this->assertEquals('0', $cartItem->vatFcCode);
        $this->assertEquals('10', $cartItem->productFcCode);
        $this->assertEquals('https://ecommerce.test/images/item-name.png', $cartItem->urlImg);

        $this->assertEquals(3000, $cart->subtotal());
        $this->assertEquals(3600.00, $cart->total());
        $this->assertEquals(600.00, $cart->vat());
    }

    /** @test */
    public function it_will_destroy_the_cart_when_the_user_logs_out_and_the_config_setting_was_set_to_true()
    {
        $this->app['config']->set('cart.destroy_on_logout', true);

        $this->app->instance(SessionManager::class, Mockery::mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('forget')->once()->with('cart');
        }));

        $user = Mockery::mock(Authenticatable::class);

        event(new Logout('', $user));
    }

    /** @test */
    public function it_will_add_a_fixed_coupon_to_a_cart_item()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            100
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(1, $cart->coupons());
        $this->assertEquals(
            (object) [
                'rowId' => '07d5da5550494c62daf9993cf954303f',
                'couponCode' => 'BLACK_FRIDAY_FIXED_2021',
                'couponType' => 'fixed',
                'couponValue' => 100,
            ],
            $cart->coupons()['BLACK_FRIDAY_FIXED_2021']
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
        $this->assertEquals('BLACK_FRIDAY_FIXED_2021', $coupon->couponCode);
        $this->assertEquals('fixed', $coupon->couponType);
        $this->assertEquals(100, $coupon->couponValue);
        $this->assertEquals(916.67, $cartItem->price);
        $this->assertEquals(183.33, $cartItem->vat);
        $this->assertEquals(1100.00, $cartItem->totalPrice);
        $this->assertEquals(100, $cartItem->discountValue);
    }

    /** @test */
    public function it_will_add_a_percentage_coupon_to_a_cart_item()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(1, $cart->coupons());
        $this->assertEquals(
            (object) [
                'rowId' => '07d5da5550494c62daf9993cf954303f',
                'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
                'couponType' => 'percentage',
                'couponValue' => 50,
            ],
            $cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021']
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
        $this->assertEquals('BLACK_FRIDAY_PERCENTAGE_2021', $coupon->couponCode);
        $this->assertEquals('percentage', $coupon->couponType);
        $this->assertEquals(50, $coupon->couponValue);
        $this->assertEquals(500.00, $cartItem->price);
        $this->assertEquals(100.00, $cartItem->vat);
        $this->assertEquals(600.00, $cartItem->totalPrice);
        $this->assertEquals(600.00, $cartItem->discountValue);
    }

    /** @test */
    public function it_can_remove_a_coupon_after_product_was_removed_from_cart()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertCount(1, $cart->coupons());

        $cart->remove($cartItem->rowId);

        $this->assertEmpty($cart->coupons());
    }

    /** @test */
    public function it_can_detach_a_coupon_of_a_cart_item()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(1, $cart->coupons());
        $this->assertEquals(
            (object) [
                'rowId' => '07d5da5550494c62daf9993cf954303f',
                'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
                'couponType' => 'percentage',
                'couponValue' => 50,
            ],
            $cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021']
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
        $this->assertEquals('BLACK_FRIDAY_PERCENTAGE_2021', $coupon->couponCode);
        $this->assertEquals('percentage', $coupon->couponType);
        $this->assertEquals(50, $coupon->couponValue);
        $this->assertEquals(500.00, $cartItem->price);
        $this->assertEquals(100.00, $cartItem->vat);
        $this->assertEquals(600.00, $cartItem->totalPrice);
        $this->assertEquals(600.00, $cartItem->discountValue);

        $cart->detachCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021'
        );

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cart->coupons());
        $this->assertEmpty($cart->coupons());

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cartItem->appliedCoupons);
        $this->assertEmpty($cartItem->appliedCoupons);
    }

    /** @test */
    public function it_can_detect_if_has_coupons()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(1, $cart->coupons());
        $this->assertEquals(
            (object) [
                'rowId' => '07d5da5550494c62daf9993cf954303f',
                'couponCode' => 'BLACK_FRIDAY_PERCENTAGE_2021',
                'couponType' => 'percentage',
                'couponValue' => 50,
            ],
            $cart->coupons()['BLACK_FRIDAY_PERCENTAGE_2021']
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
        $this->assertEquals('BLACK_FRIDAY_PERCENTAGE_2021', $coupon->couponCode);
        $this->assertEquals('percentage', $coupon->couponType);
        $this->assertEquals(50, $coupon->couponValue);
        $this->assertEquals(500.00, $cartItem->price);
        $this->assertEquals(100.00, $cartItem->vat);
        $this->assertEquals(600.00, $cartItem->totalPrice);
        $this->assertEquals(600.00, $cartItem->discountValue);

        $this->assertTrue($cart->hasCoupons());
        $this->assertTrue($cartItem->hasCoupons());

        $cart->detachCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021'
        );

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cart->coupons());
        $this->assertEmpty($cart->coupons());
        $this->assertFalse($cart->hasCoupons());

        $cartItem = $cart->get('07d5da5550494c62daf9993cf954303f');

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cartItem->appliedCoupons);
        $this->assertEmpty($cartItem->appliedCoupons);
        $this->assertFalse($cartItem->hasCoupons());
    }

    /** @test */
    public function it_can_return_all_applied_coupons()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            10
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(2, $cart->coupons());
        $this->assertEquals(
            [
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
            ],
            $cart->coupons()
        );
    }

    /** @test */
    public function it_can_return_a_coupon_by_its_code()
    {
        $cart = $this->getCart();
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $cart->applyCoupon(
            '07d5da5550494c62daf9993cf954303f',
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            10
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(2, $cart->coupons());
        $this->assertEquals(
            [
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
            ],
            $cart->coupons()
        );

        $coupon = $cart->getCoupon('BLACK_FRIDAY_PERCENTAGE_2021');
        $this->assertEquals('BLACK_FRIDAY_PERCENTAGE_2021', $coupon->couponCode);
        $this->assertEquals('percentage', $coupon->couponType);
        $this->assertEquals(50, $coupon->couponValue);
    }

    /** @test */
    public function it_can_calculate_cart_totals_after_applied_item_coupon()
    {
        $cart = $this->getCart();

        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );
        $cartItem = $cart->add(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            200.00,
            '0',
            '0',
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $this->assertItemsInCart(2, $cart);
        $this->assertRowsInCart(1, $cart);

        $cart->applyCoupon(
            $cartItem->rowId,
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            400
        );

        $this->assertIsArray($cart->coupons());
        $this->assertCount(1, $cart->coupons());
        $this->assertEquals(1666.67, $cart->subtotal());
        $this->assertEquals(2000, $cart->total());
        $this->assertEquals(333.33, $cart->vat());
    }

    /** @test */
    public function it_can_set_and_get_options_on_cart()
    {
        $cart = $this->getCart();
        $cart->setOptions(['test' => 'test']);

        $this->assertEquals(['test' => 'test'], $cart->getOptions());

        $cart->getOptionsByKey('test');
        $this->assertEquals('test', $cart->getOptionsByKey('test'));

        $cart->getOptionsByKey('option_not_existing_with_default_value', false);
        $this->assertEquals(false, $cart->getOptionsByKey('option_not_existing_with_default_value'));

        $cart->getOptionsByKey('option_not_existing_without_default_value');
        $this->assertNull($cart->getOptionsByKey('option_not_existing_without_default_value'));
    }

    /** @test */
    public function it_will_remove_options_when_cart_is_destroyed()
    {
        $cart = $this->getCart();
        $cart->setOptions(['test' => 'test']);

        $this->assertEquals(['test' => 'test'], $cart->getOptions());

        $cart->destroy();

        $this->assertEquals([], $cart->getOptions());
    }

    /**
     * Get an instance of the cart.
     *
     * @return Cart
     */
    private function getCart(): Cart
    {
        $session = $this->app->make('session');
        $events = $this->app->make('events');

        return new Cart($session, $events);
    }

    /**
     * Set the config number format.
     *
     * @param  int  $decimals
     * @param  string  $decimalPoint
     * @param  string  $thousandSeparator
     */
    private function setConfigFormat(int $decimals, string $decimalPoint, string $thousandSeparator)
    {
        $this->app['config']->set('cart.format.decimals', $decimals);
        $this->app['config']->set('cart.format.decimal_point', $decimalPoint);
        $this->app['config']->set('cart.format.thousand_separator', $thousandSeparator);
    }
}
