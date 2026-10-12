<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\ProductWithTrait;

it('applies the default tax rate as exclusive when no vat is given', function () {
    config(['cart.tax.default_rate' => 22, 'cart.tax.tax_behavior' => 'exclusive']);

    $item = new CartItem(1, 'Item', 'Sub', 1, 100.0, null);

    expect($item->price)->toBe(100.0)
        ->and($item->vat)->toBe(22.0)
        ->and($item->totalPrice)->toBe(122.0)
        ->and($item->originalTotalPrice)->toBe(122.0)
        ->and($item->originalVat)->toBe(22.0)
        ->and($item->vatRate)->toBe(22.0)
        ->and($item->taxRate)->toBe(22.0);
});

it('applies the default tax rate as inclusive when no vat is given', function () {
    config(['cart.tax.default_rate' => 22, 'cart.tax.tax_behavior' => 'inclusive']);

    $item = new CartItem(1, 'Item', 'Sub', 1, 122.0, null);

    expect($item->vat)->toBe(22.0)
        ->and($item->price)->toBe(100.0)
        ->and($item->originalPrice)->toBe(100.0)
        ->and($item->originalVat)->toBe(22.0)
        ->and($item->vatRate)->toBe(22.0)
        ->and($item->taxRate)->toBe(22.0);
});

it('syncs taxRate from vatRate on unserialization', function () {
    $item = new CartItem(1, 'Item', 'Sub', 1, 100.0, 122.0, '', '', 22.0);
    $item->taxRate = 0.0;
    $item->tax = 0.0;

    $restored = unserialize(serialize($item));

    expect($restored->taxRate)->toBe(22.0)
        ->and($restored->tax)->toBe(22.0);
});

it('determines whether the item represents a given buyable', function () {
    $buyable = new BuyableProduct(5);
    $item = CartItem::fromBuyable($buyable)->associate($buyable);

    expect($item->is($buyable))->toBeTrue()
        ->and($item->is(new BuyableProduct(6)))->toBeFalse()
        ->and($item->is(new ProductWithTrait(5, 'Other', null, null, 1.0)))->toBeFalse();
});
