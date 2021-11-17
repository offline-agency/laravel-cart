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
      '0',
      '0',
      200.22,
      'https://ecommerce.test/images/item-name.png',
      ['size' => 'XL', 'color' => 'red']
    );

    $cartItem->setQuantity(2);

    $this->assertEquals([
      'id' => 1,
      'name' => 'First Cart item',
      'subtitle' => 'This is a simple description',
      'qty' => 2,
      'price' => 1000.00,
      'vatFcCode' => '0',
      'productFcCode' => '0',
      'vat' => 200.22,
      'rowId' => '07d5da5550494c62daf9993cf954303f',
      'urlImg' => 'https://ecommerce.test/images/item-name.png',
      'options' => [
        'size' => 'XL',
        'color' => 'red'
      ]
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
      '0',
      '0',
      200.22,
      'https://ecommerce.test/images/item-name.png',
      ['size' => 'XL', 'color' => 'red']
    );
    $cartItem->setQuantity(2);

    $this->assertJson($cartItem->toJson());

    $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"name":"First Cart item","subtitle":"This is a simple description","qty":2,"price":1000,"vatFcCode":"0","productFcCode":"0","vat":200.22,"urlImg":"https:\/\/ecommerce.test\/images\/item-name.png","options":{"size":"XL","color":"red"}}';

    $this->assertEquals($json, $cartItem->toJson());
  }

  /** @test */
  public function it_can_apply_a_coupon()
  {
    $cartItem = new CartItem(
      1,
      'First Cart item',
      'This is a simple description',
      1,
      1000.00,
      1200.22,
      '0',
      '0',
      200.22,
      'https://ecommerce.test/images/item-name.png',
      ['size' => 'XL', 'color' => 'red']
    );

    $cartItem->applyCoupon(
'BLACK_FRIDAY2021',
'percentage',
      100
    );

    $this->assertEquals('BLACK_FRIDAY2021', $cartItem->couponCode);
    $this->assertEquals('percentage', $cartItem->couponType);
    $this->assertEquals(100, $cartItem->couponValue);
    $this->assertEquals(0, $cartItem->price);
    $this->assertEquals(0, $cartItem->vat);
    $this->assertEquals(0, $cartItem->totalPrice);

    $this->assertEquals(0, $cartItem->discountCode);
    $this->assertEquals(0, $cartItem->discountDescription);
    $this->assertEquals(0, $cartItem->discountRate);
    $this->assertEquals(0, $cartItem->discountValue);

  }
}
