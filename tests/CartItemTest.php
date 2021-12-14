<?php

namespace OfflineAgency\Tests\LaravelCart;

use Illuminate\Foundation\Application;
use InvalidArgumentException;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartServiceProvider;
use Orchestra\Testbench\TestCase;

class CartItemTest extends TestCase
{
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

    /** @test */
    public function it_can_be_cast_to_an_array()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->setQuantity(2);

        $this->assertEquals([
            'rowId' => '07d5da5550494c62daf9993cf954303f',
            'id' => 1,
            'qty' => 2,
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
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );
        $cartItem->setQuantity(2);

        $this->assertJson($cartItem->toJson());

        $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"qty":2,"name":"First Cart item","subtitle":"This is a simple description","originalPrice":1000,"originalTotalPrice":1200,"originalVat":200,"price":1000,"totalPrice":1200,"vat":200,"vatLabel":"Iva Inclusa","vatRate":20,"vatFcCode":"0","discountValue":0,"productFcCode":"0","urlImg":"https:\/\/ecommerce.test\/images\/item-name.png","options":{"size":"XL","color":"red"},"associatedModel":null,"model":null,"appliedCoupons":[]}';

        $this->assertEquals($json, $cartItem->toJson());
    }

    /** @test */
    public function it_can_apply_a_coupon_percentage()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
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
    public function it_can_apply_a_coupon_fixed()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            100
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
    public function it_can_throw_an_exception_with_invalid_coupon_type()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon type not handled. Possible values: fixed and percentage');

        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_INVALID_2021',
            'not-valid-type',
            100
        );
    }

    /** @test */
    public function it_can_detach_a_coupon()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            100
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
        $this->assertEquals('BLACK_FRIDAY_FIXED_2021', $coupon->couponCode);
        $this->assertEquals('fixed', $coupon->couponType);
        $this->assertEquals(100, $coupon->couponValue);
        $this->assertEquals(916.67, $cartItem->price);
        $this->assertEquals(183.33, $cartItem->vat);
        $this->assertEquals(1100.00, $cartItem->totalPrice);
        $this->assertEquals(100, $cartItem->discountValue);

        $cartItem->detachCoupon(
            'BLACK_FRIDAY_FIXED_2021'
        );

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cartItem->appliedCoupons);
        $this->assertEmpty($cartItem->appliedCoupons);
    }

    /** @test */
    public function it_can_detect_if_has_coupons()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            100
        );

        $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
        $this->assertEquals('BLACK_FRIDAY_FIXED_2021', $coupon->couponCode);
        $this->assertEquals('fixed', $coupon->couponType);
        $this->assertEquals(100, $coupon->couponValue);
        $this->assertEquals(916.67, $cartItem->price);
        $this->assertEquals(183.33, $cartItem->vat);
        $this->assertEquals(1100.00, $cartItem->totalPrice);
        $this->assertEquals(100, $cartItem->discountValue);
        $this->assertTrue($cartItem->hasCoupons());

        $cartItem->detachCoupon(
            'BLACK_FRIDAY_FIXED_2021'
        );

        $this->assertArrayNotHasKey('BLACK_FRIDAY_FIXED_2021', $cartItem->appliedCoupons);
        $this->assertEmpty($cartItem->appliedCoupons);
        $this->assertFalse($cartItem->hasCoupons());
    }

    /** @test */
    public function it_can_return_a_coupon_by_its_code()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            1000.00,
            1200.00,
            '0',
            '0',
            200.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            100
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertIsArray($cartItem->appliedCoupons);
        $this->assertCount(2, $cartItem->appliedCoupons);

        $coupon = $cartItem->getCoupon('BLACK_FRIDAY_FIXED_2021');
        $this->assertEquals('BLACK_FRIDAY_FIXED_2021', $coupon->couponCode);
        $this->assertEquals('fixed', $coupon->couponType);
        $this->assertEquals(100, $coupon->couponValue);
        $this->assertEquals(458.33, $cartItem->price);
        $this->assertEquals(91.67, $cartItem->vat);
        $this->assertEquals(550.00, $cartItem->totalPrice);
        $this->assertEquals(650.00, $cartItem->discountValue);
        $this->assertTrue($cartItem->hasCoupons());
    }

    /** @test */
    public function it_can_sum_discount()
    {
        $cartItem = new CartItem(
            1,
            'First Cart item',
            'This is a simple description',
            1,
            100.00,
            122.00,
            '0',
            '0',
            22.00,
            'https://ecommerce.test/images/item-name.png',
            ['size' => 'XL', 'color' => 'red']
        );

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_FIXED_2021',
            'fixed',
            22
        );

        $this->assertEquals(81.97, $cartItem->price);
        $this->assertEquals(18.03, $cartItem->vat);
        $this->assertEquals(100, $cartItem->totalPrice);
        $this->assertEquals(22, $cartItem->discountValue);

        $cartItem->applyCoupon(
            'BLACK_FRIDAY_PERCENTAGE_2021',
            'percentage',
            50
        );

        $this->assertIsArray($cartItem->appliedCoupons);
        $this->assertCount(2, $cartItem->appliedCoupons);

        $this->assertEquals(40.98, $cartItem->price);
        $this->assertEquals(9.02, $cartItem->vat);
        $this->assertEquals(50, $cartItem->totalPrice);
        $this->assertEquals(72, $cartItem->discountValue);

        $this->assertTrue($cartItem->hasCoupons());
    }
}
