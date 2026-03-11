<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel;

it('can be cast to an array', function (): void {
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

    expect($cartItem->toArray())->toEqual([
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
    ]);
});

it('can be cast to json', function (): void {
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

    expect($cartItem->toJson())->toBeJson();

    $json = '{"rowId":"07d5da5550494c62daf9993cf954303f","id":1,"qty":2,"name":"First Cart item","subtitle":"This is a simple description","originalPrice":1000,"originalTotalPrice":1200,"originalVat":200,"price":1000,"totalPrice":1200,"vat":200,"vatLabel":"Iva Inclusa","vatRate":20,"vatFcCode":"0","discountValue":0,"productFcCode":"0","urlImg":"https:\/\/ecommerce.test\/images\/item-name.png","options":{"size":"XL","color":"red"},"associatedModel":null,"model":null,"appliedCoupons":[]}';

    expect($cartItem->toJson())->toBe($json);
});

it('can apply a coupon percentage', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_PERCENTAGE_2021'];
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_PERCENTAGE_2021');
    expect($coupon->couponType)->toBe('percentage');
    expect($coupon->couponValue)->toEqual(50);
    expect($cartItem->price)->toEqual(500.00);
    expect($cartItem->vat)->toEqual(100.00);
    expect($cartItem->totalPrice)->toEqual(600.00);
    expect($cartItem->discountValue)->toEqual(600.00);
});

it('throws an exception if name is empty', function (): void {
    expect(fn () => new CartItem(
        1,
        '',
        'This is a simple description',
        1,
        1000.00,
        1200.00,
        '0',
        '0',
        200.00,
        'https://ecommerce.test/images/item-name.png',
        ['size' => 'XL', 'color' => 'red']
    ))->toThrow(InvalidArgumentException::class, 'Please supply a valid name.');
});

it('can apply a coupon fixed', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 100);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toBe('fixed');
    expect($coupon->couponValue)->toEqual(100);
    expect($cartItem->price)->toEqual(916.67);
    expect($cartItem->vat)->toEqual(183.33);
    expect($cartItem->totalPrice)->toEqual(1100.00);
    expect($cartItem->discountValue)->toEqual(100);
});

it('can throw an exception with invalid coupon type', function (): void {
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

    expect(fn () => $cartItem->applyCoupon('BLACK_FRIDAY_INVALID_2021', 'not-valid-type', 100))
        ->toThrow(InvalidArgumentException::class, 'Coupon type not handled. Possible values: fixed and percentage');
});

it('can detach a coupon', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 100);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toBe('fixed');
    expect($coupon->couponValue)->toEqual(100);
    expect($cartItem->price)->toEqual(916.67);
    expect($cartItem->vat)->toEqual(183.33);
    expect($cartItem->totalPrice)->toEqual(1100.00);
    expect($cartItem->discountValue)->toEqual(100);

    $cartItem->detachCoupon('BLACK_FRIDAY_FIXED_2021');

    expect($cartItem->appliedCoupons)->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cartItem->appliedCoupons)->toBeEmpty();
});

it('can detect if has coupons', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 100);

    $coupon = $cartItem->appliedCoupons['BLACK_FRIDAY_FIXED_2021'];
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toBe('fixed');
    expect($coupon->couponValue)->toEqual(100);
    expect($cartItem->price)->toEqual(916.67);
    expect($cartItem->vat)->toEqual(183.33);
    expect($cartItem->totalPrice)->toEqual(1100.00);
    expect($cartItem->discountValue)->toEqual(100);
    expect($cartItem->hasCoupons())->toBeTrue();

    $cartItem->detachCoupon('BLACK_FRIDAY_FIXED_2021');

    expect($cartItem->appliedCoupons)->not->toHaveKey('BLACK_FRIDAY_FIXED_2021');
    expect($cartItem->appliedCoupons)->toBeEmpty();
    expect($cartItem->hasCoupons())->toBeFalse();
});

it('can return a coupon by its code', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 100);
    $cartItem->applyCoupon('BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cartItem->appliedCoupons)->toBeArray();
    expect($cartItem->appliedCoupons)->toHaveCount(2);

    $coupon = $cartItem->getCoupon('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toBe('fixed');
    expect($coupon->couponValue)->toEqual(100);
    expect($cartItem->price)->toEqual(416.67);
    expect($cartItem->vat)->toEqual(83.33);
    expect($cartItem->totalPrice)->toEqual(500.00);
    expect($cartItem->discountValue)->toEqual(700.00);
    expect($cartItem->hasCoupons())->toBeTrue();
});

it('can sum discount', function (): void {
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

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 22);

    expect($cartItem->price)->toEqual(81.97);
    expect($cartItem->vat)->toEqual(18.03);
    expect($cartItem->totalPrice)->toEqual(100.0);
    expect($cartItem->discountValue)->toEqual(22);

    $cartItem->applyCoupon('BLACK_FRIDAY_PERCENTAGE_2021', 'percentage', 50);

    expect($cartItem->appliedCoupons)->toBeArray();
    expect($cartItem->appliedCoupons)->toHaveCount(2);

    expect($cartItem->price)->toEqual(31.97);
    expect($cartItem->vat)->toEqual(7.03);
    expect($cartItem->totalPrice)->toEqual(39.0);
    expect($cartItem->discountValue)->toEqual(83.0);

    expect($cartItem->hasCoupons())->toBeTrue();
});

it('can associate model id', function (): void {
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

    $cartItem->associate([
        'associatedModel' => 'OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel',
        'modelId' => 'fake_id',
    ], false);

    expect($cartItem->associatedModel)->toBe('OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel');
    expect($cartItem->model)->toBe('fake_id');
});

it('can resolve the associated model through magic accessor', function (): void {
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

    $cartItem->associate(ProductModel::class);

    unset($cartItem->model);

    expect($cartItem->model)->toBeInstanceOf(ProductModel::class);
});

it('can resolve dynamic values through magic accessor', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        3,
        100.00,
        120.00,
        '0',
        '0',
        20.00,
        'https://example.com/image.png',
        []
    );

    expect($cartItem->id)->toEqual(1);
    expect($cartItem->name)->toBe('Test Item');

    expect($cartItem->tax)->toEqual(0.0);
    expect($cartItem->priceTax)->toEqual(100.00);
    expect($cartItem->subtotal)->toEqual(300.00);
    expect($cartItem->total)->toEqual(300.00);
    expect($cartItem->taxTotal)->toEqual(0.0);

    expect($cartItem->nonExistentProperty)->toBeNull();
});

it('can access dynamic properties', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        1,
        1000.00,
        1200.00,
        '0',
        '123',
        22.00,
        'https://example.com/image.png',
        ['size' => 'L', 'color' => 'red']
    );

    expect($cartItem->totalPrice)->toEqual(1200.00);
    expect($cartItem->price)->toEqual(1000.00);
    expect($cartItem->vat)->toEqual(22.00);
    expect($cartItem->vatLabel)->toBe('Iva Inclusa');

    expect($cartItem->subtotal)->toEqual(1000.00);
});

it('can apply a coupon to cart item', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        1,
        1000.00,
        1200.00,
        '0',
        '123',
        22.00,
        'https://example.com/image.png',
        ['size' => 'L', 'color' => 'red']
    );

    $cartItem->applyCoupon('BLACK_FRIDAY_FIXED_2021', 'fixed', 100);

    expect($cartItem->totalPrice)->toEqual(1100.00);
    expect($cartItem->discountValue)->toEqual(100);

    $coupon = $cartItem->getCoupon('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponCode)->toBe('BLACK_FRIDAY_FIXED_2021');
    expect($coupon->couponType)->toBe('fixed');
    expect($cartItem->hasCoupons())->toBeTrue();

    expect($cartItem->appliedCoupons)->toHaveCount(1);
});

it('can be created from a buyable', function (): void {
    $buyable = new BuyableProduct(
        1,
        'Item name',
        'Item description',
        1,
        10.00,
        12.22,
        2.22,
        '0',
        '0',
        'https://ecommerce.test/images/item-name.png',
        ['size' => 'XL', 'color' => 'red']
    );

    $cartItem = CartItem::fromBuyable($buyable);

    expect($cartItem->id)->toEqual(1);
    expect($cartItem->name)->toBe('Item name');
    expect($cartItem->subtitle)->toBe('Item description');
    expect($cartItem->qty)->toEqual(1);
    expect($cartItem->price)->toEqual(10.00);
    expect($cartItem->totalPrice)->toEqual(12.22);
    expect($cartItem->vat)->toEqual(2.22);
    expect($cartItem->vatFcCode)->toBe('0');
    expect($cartItem->productFcCode)->toBe('0');
    expect($cartItem->urlImg)->toBe('https://ecommerce.test/images/item-name.png');
    expect($cartItem->options->all())->toBe(['size' => 'XL', 'color' => 'red']);
});

it('returns null if associated model does not exist', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        1,
        100.00,
        120.00,
        '0',
        '0',
        20.00,
        'https://example.com/image.png',
        []
    );

    $cartItem->associate('NonExistent\\Model\\Class');

    unset($cartItem->model);

    expect($cartItem->model)->toBeNull();
});

it('can access public properties through magic get', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        1,
        100.00,
        120.00,
        '0',
        '0',
        20.00,
        'https://example.com/image.png',
        []
    );

    expect($cartItem->__get('name'))->toBe('Test Item');
    expect($cartItem->__get('qty'))->toEqual(1);
});

it('calculates tax via magic get', function (): void {
    $cartItem = new CartItem(
        1,
        'Test Item',
        'Description',
        1,
        100.00,
        120.00,
        '0',
        '0',
        20.00,
        'https://example.com/image.png',
        []
    );

    $cartItem->taxRate = 10;
    unset($cartItem->tax);

    expect($cartItem->tax)->toEqual(10.00);
});
