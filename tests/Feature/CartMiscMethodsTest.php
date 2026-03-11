<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

// ── addItemCoupon (non-deprecated replacement for applyCoupon) ─────────────────

it('addItemCoupon applies a fixed coupon to the item', function (): void {
    $item = $this->cart->add('1', 'Shirt', '', 1, 100.0, 122.0, 22.0);

    $this->cart->addItemCoupon($item->rowId, 'SAVE10', 'fixed', 10.0);

    expect($this->cart->get($item->rowId)->totalPrice)->toBe(112.0)
        ->and($this->cart->hasCoupon('SAVE10'))->toBeTrue();
});

it('addItemCoupon applies a percentage coupon to the item', function (): void {
    $item = $this->cart->add('1', 'Shirt', '', 1, 100.0, 122.0, 22.0);

    $this->cart->addItemCoupon($item->rowId, 'PCT10', 'percentage', 10.0);

    // 10% of 122.0 = 12.20 → totalPrice = 109.80
    expect($this->cart->get($item->rowId)->totalPrice)->toBe(109.8);
});

it('addItemCoupon produces the same result as applyCoupon', function (): void {
    $itemA = $this->cart->add('1', 'Product A', '', 1, 100.0, 122.0, 22.0);

    $this->cart->instance('copy')->add('1', 'Product A', '', 1, 100.0, 122.0, 22.0);
    $rowIdB = $this->cart->instance('copy')->content()->keys()->first();

    $this->cart->instance()->addItemCoupon($itemA->rowId, 'SAVE5', 'fixed', 5.0);
    $this->cart->instance('copy')->applyCoupon($rowIdB, 'SAVE5', 'fixed', 5.0);

    expect($this->cart->instance()->get($itemA->rowId)->totalPrice)
        ->toBe($this->cart->instance('copy')->get($rowIdB)->totalPrice);
});

// ── addCoupon with string argument ─────────────────────────────────────────────

it('addCoupon accepts a plain string and creates a CartCoupon', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon('STRINGCODE');

    expect($this->cart->hasCartCoupon('STRINGCODE'))->toBeTrue();
});

it('addCoupon string coupon uses md5 of string as hash', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon('MYCODE');

    $expectedHash = md5('MYCODE');
    expect($this->cart->hasCartCoupon($expectedHash))->toBeTrue();
});

it('addCoupon string coupon defaults to fixed type with zero value', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 100.0, 0.0);

    $this->cart->addCoupon('ZEROCOUPON');

    $coupon = $this->cart->listCoupons()->first();
    expect($coupon->type)->toBe('fixed')
        ->and($coupon->value)->toBe(0.0);
});

// ── Cart::originalTotalPrice ───────────────────────────────────────────────────

it('originalTotalPrice returns the sum of originalTotalPrice × qty for all items', function (): void {
    $this->cart->add('1', 'Item A', '', 2, 50.0, 61.0, 11.0);
    $this->cart->add('2', 'Item B', '', 3, 20.0, 24.4, 4.4);

    // 61.0 * 2 + 24.4 * 3 = 122.0 + 73.2 = 195.2
    expect($this->cart->originalTotalPrice())->toBe(195.2);
});

it('originalTotalPrice equals total when no item-level coupons are applied', function (): void {
    $this->cart->add('1', 'Product', '', 2, 50.0, 61.0, 11.0);

    expect($this->cart->originalTotalPrice())->toBe($this->cart->total());
});

it('originalTotalPrice reflects pre-coupon prices after coupon is applied', function (): void {
    $item = $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $originalTotal = $this->cart->originalTotalPrice();

    $this->cart->addItemCoupon($item->rowId, 'SAVE20', 'fixed', 20.0);

    // totalPrice drops but originalTotalPrice stays the same
    expect($this->cart->originalTotalPrice())->toBe($originalTotal)
        ->and($this->cart->total())->toBeLessThan($originalTotal);
});

// ── Cart::totalVatLabel ────────────────────────────────────────────────────────

it('totalVatLabel returns Iva Inclusa when VAT is present', function (): void {
    $this->cart->add('1', 'Taxed Item', '', 1, 100.0, 122.0, 22.0);

    expect($this->cart->totalVatLabel())->toBe('Iva Inclusa');
});

it('totalVatLabel returns Esente Iva when VAT is zero', function (): void {
    $this->cart->add('1', 'Exempt Item', '', 1, 10.0, 10.0, 0.0);

    expect($this->cart->totalVatLabel())->toBe('Esente Iva');
});

it('totalVatLabel returns Esente Iva on an empty cart', function (): void {
    expect($this->cart->totalVatLabel())->toBe('Esente Iva');
});

// ── Cart::getCoupon (item-level lookup) ────────────────────────────────────────

it('getCoupon returns the coupon object when it exists', function (): void {
    $item = $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $this->cart->addItemCoupon($item->rowId, 'SAVE5', 'fixed', 5.0);

    $coupon = $this->cart->getCoupon('SAVE5');

    expect($coupon)->not->toBeNull()
        ->and($coupon->couponCode)->toBe('SAVE5');
});

it('getCoupon returns null when the coupon does not exist', function (): void {
    expect($this->cart->getCoupon('NOPE'))->toBeNull();
});

// ── Cart::hasGlobalCoupon ──────────────────────────────────────────────────────

it('hasGlobalCoupon returns false when no global type coupon is present', function (): void {
    $item = $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    $this->cart->addItemCoupon($item->rowId, 'ITEM_COUPON', 'fixed', 5.0);

    expect($this->cart->hasGlobalCoupon())->toBeFalse();
});

it('hasGlobalCoupon returns false on a cart with no coupons at all', function (): void {
    expect($this->cart->hasGlobalCoupon())->toBeFalse();
});

// ── Cart::numberFormat ─────────────────────────────────────────────────────────

it('numberFormat formats with supplied parameters', function (): void {
    expect($this->cart->numberFormat(1234567.5, 2, ',', '.'))->toBe('1.234.567,50');
});

it('numberFormat uses config defaults when parameters are null', function (): void {
    config(['cart.format.decimals' => 3]);
    config(['cart.format.decimal_point' => ',']);
    config(['cart.format.thousand_separator' => ' ']);

    expect($this->cart->numberFormat(1000.0, null, null, null))->toBe('1 000,000');
});

it('numberFormat rounds correctly to the requested decimal places', function (): void {
    expect($this->cart->numberFormat(9.9999, 2, '.', ''))->toBe('10.00');
});

// ── Cart::update with Buyable ──────────────────────────────────────────────────

it('update with a Buyable refreshes name and price on the item', function (): void {
    $item = $this->cart->add('1', 'Old Name', '', 1, 50.0, 61.0, 11.0);

    $buyable = new BuyableProduct(
        id: 1,
        name: 'New Name',
        subtitle: 'Updated',
        qty: 1,
        price: 80.0,
        totalPrice: 97.6,
        vat: 17.6,
        vatFcCode: '',
        productFcCode: '',
        urlImg: '',
        options: [],
    );

    $updated = $this->cart->update($item->rowId, $buyable);

    expect($updated->name)->toBe('New Name')
        ->and($updated->price)->toBe(80.0);
});

// ── Cart::update with array ───────────────────────────────────────────────────

it('update with an array modifies the specified attributes', function (): void {
    $item = $this->cart->add('1', 'Shirt', '', 1, 100.0, 122.0, 22.0);

    $updated = $this->cart->update($item->rowId, ['qty' => 3, 'name' => 'Updated Shirt']);

    expect($updated->qty)->toBe(3)
        ->and($updated->name)->toBe('Updated Shirt');
});

it('update with zero qty removes the item and returns null', function (): void {
    $item = $this->cart->add('1', 'Shirt', '', 2, 100.0, 122.0, 22.0);

    $result = $this->cart->update($item->rowId, 0);

    expect($result)->toBeNull()
        ->and($this->cart->isEmpty())->toBeTrue();
});

// ── CartItem static factories ──────────────────────────────────────────────────

it('CartItem::fromArray builds a CartItem from an associative array', function (): void {
    $item = CartItem::fromArray([
        'id' => 10,
        'name' => 'Hat',
        'subtitle' => 'Blue cotton',
        'qty' => 2,
        'price' => 25.0,
        'totalPrice' => 30.5,
        'vatFcCode' => '',
        'productFcCode' => '',
        'vat' => 5.5,
        'urlImg' => '',
        'options' => ['color' => 'blue'],
    ]);

    expect($item->id)->toBe(10)
        ->and($item->name)->toBe('Hat')
        ->and($item->price)->toBe(25.0)
        ->and($item->options['color'])->toBe('blue');
});

it('CartItem::fromAttributes builds a CartItem from individual parameters', function (): void {
    $item = CartItem::fromAttributes(
        id: 99,
        name: 'Jacket',
        subtitle: 'Black',
        qty: 1,
        price: 200.0,
        totalPrice: 244.0,
        vatFcCode: '',
        productFcCode: '',
        vat: 44.0,
        urlImg: '',
        options: ['size' => 'XL'],
    );

    expect($item->id)->toBe(99)
        ->and($item->name)->toBe('Jacket')
        ->and($item->options['size'])->toBe('XL');
});

// ── Exception properties ───────────────────────────────────────────────────────

it('InvalidCouponException carries the coupon code', function (): void {
    try {
        throw new InvalidCouponException('FAIL_CODE');
    } catch (InvalidCouponException $e) {
        expect($e->couponCode)->toBe('FAIL_CODE')
            ->and($e->getMessage())->toContain('FAIL_CODE');
    }
});

it('InvalidCouponException accepts a custom message', function (): void {
    try {
        throw new InvalidCouponException('CODE', 'Custom error message');
    } catch (InvalidCouponException $e) {
        expect($e->getMessage())->toBe('Custom error message');
    }
});
