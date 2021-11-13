<?php

namespace OfflineAgency\Tests\OaLaravelCart;

use Illuminate\Foundation\Application;
use OfflineAgency\OaLaravelCart\CartServiceProvider;
use Orchestra\Testbench\TestCase;
use OfflineAgency\OaLaravelCart\CartItem;

class CartItemTest extends TestCase
{
    /**
     * Set the package service provider.
     *
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [CartServiceProvider::class];
    }

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $cartItem = new CartItem(
          1,
          'Some item',
          'This is a simple description',
          10.00,
          10.00,
          2.22,
          12.22,
          ['size' => 'XL', 'color' => 'red']);
        $cartItem->setQuantity(2);

        $this->assertEquals([
            'id' => 1,
            'name' => 'Some item',
            'price' => 10.00,
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'qty' => 2,
            'options' => [
                'size' => 'XL',
                'color' => 'red'
            ],
            'tax' => 0,
            'subtotal' => 20.00,
        ], $cartItem->toArray());
    }

    /** @test */
    public function it_can_be_cast_to_json()
    {
      $cartItem = new CartItem(
        1,
        'Some item',
        'This is a simple description',
        10.00,
        10.00,
        2.22,
        12.22,
        ['size' => 'XL', 'color' => 'red']);
      $cartItem->setQuantity(2);

        $this->assertJson($cartItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"Some item","qty":2,"price":10,"options":{"size":"XL","color":"red"},"tax":0,"subtotal":20}';

        $this->assertEquals($json, $cartItem->toJson());
    }
}
