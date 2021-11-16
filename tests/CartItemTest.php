<?php

namespace OfflineAgency\Tests\LaravelCart;

use Illuminate\Foundation\Application;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartServiceProvider;
use Orchestra\Testbench\TestCase;

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
      'First Cart item',
      'This is a simple description',
      1,
      1000.00,
      1200.22,
      200.22,
      '0',
      '10',
      'https://ecommerce.test/images/item-name.png',
      ['size' => 'XL', 'color' => 'red']
    );

    $cartItem->setQuantity(2);

    $this->assertEquals([
      'id' => 1,
      'name' => 'First Cart item',
      'subtitle' => 'This is a simple description',
      'price' => 1000.00,
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
      'First Cart item',
      'This is a simple description',
      1,
      1000.00,
      1200.22,
      200.22,
      '0',
      '10',
      'https://ecommerce.test/images/item-name.png',
      ['size' => 'XL', 'color' => 'red']
    );
    $cartItem->setQuantity(2);

    $this->assertJson($cartItem->toJson());

    $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"First Cart item","qty":2,"price":1000,"vatFcCode":200.22,"0":"productFcCode","1":"0","$vat":10,"$urlImg":"https:\/\/ecommerce.test\/images\/item-name.png","options":{"size":"XL","color":"red"},"tax":null,"subtotal":null}';

    $this->assertEquals($json, $cartItem->toJson());
  }
}
