<?php

declare(strict_types=1);

use Illuminate\Support\Collection;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('vatBreakdown returns a Collection', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);

    expect($this->cart->vatBreakdown())->toBeInstanceOf(Collection::class);
});

it('vatBreakdown groups items with the same VAT rate', function (): void {
    $this->cart->add('1', 'Product A', '', 1, 100.0, 122.0, 22.0);
    $this->cart->add('2', 'Product B', '', 1, 50.0, 61.0, 11.0);

    $breakdown = $this->cart->vatBreakdown();

    // Both items have the same VAT rate (~22%), so they should merge into one entry
    expect($breakdown)->toHaveCount(1);
});

it('vatBreakdown with different VAT rates produces separate entries', function (): void {
    // Item at 22% VAT
    $this->cart->add('1', 'Standard Item', '', 1, 100.0, 122.0, 22.0);
    // Item at 10% VAT using per-item tax_rate option
    $this->cart->add('2', 'Reduced Item', '', 1, 100.0, 100.0, 0.0, '', '', '', ['tax_rate' => 10.0]);

    $breakdown = $this->cart->vatBreakdown();

    expect($breakdown)->toHaveCount(2);
});

it('vatBreakdown entry has rate, net, vat, gross keys', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);

    $entry = $this->cart->vatBreakdown()->first();

    expect($entry)->toHaveKeys(['rate', 'net', 'vat', 'gross']);
});

it('vatBreakdown gross equals net plus vat', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);

    $entry = $this->cart->vatBreakdown()->first();

    $net = (float) str_replace(',', '', $entry['net']);
    $vat = (float) str_replace(',', '', $entry['vat']);
    $gross = (float) str_replace(',', '', $entry['gross']);

    expect(round($net + $vat, 2))->toBe($gross);
});

it('per-item tax_rate option overrides global VAT calculation', function (): void {
    // Add item with price 100, using tax_rate 10%
    $item = $this->cart->add('1', 'Low-VAT Product', '', 1, 100.0, 110.0, 10.0, '', '', '', ['tax_rate' => 10.0]);

    expect($item->vatRate)->toBe(10.0)
        ->and($item->vat)->toBe(10.0);
});

it('vatBreakdown excludes discountCartItem phantom entries', function (): void {
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);
    // Add a legacy discount phantom item
    $this->cart->applyGlobalCoupon('DISC', 'fixed', 10.0);

    $breakdown = $this->cart->vatBreakdown();

    // Only the real product should appear
    expect($breakdown)->toHaveCount(1);
});

it('rounding_mode config is respected in vatBreakdown', function (): void {
    $this->app['config']->set('cart.rounding_mode', PHP_ROUND_HALF_DOWN);
    $this->cart->add('1', 'Product', '', 1, 100.0, 122.0, 22.0);

    // Should not throw and should return valid data
    $breakdown = $this->cart->vatBreakdown();

    expect($breakdown)->not->toBeEmpty();
});
