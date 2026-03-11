<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Cart;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('adds new items via sync', function (): void {
    $this->cart->sync([
        ['id' => '1', 'name' => 'Product A', 'qty' => 2, 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2],
    ]);

    expect($this->cart->count())->toBe(2);
});

it('removes items not in sync payload', function (): void {
    $this->cart->add('1', 'Product A', '', 2, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Product B', '', 1, 20.0, 24.4, 4.4);

    // Sync with only product 2
    $this->cart->sync([
        ['id' => '2', 'name' => 'Product B', 'qty' => 1, 'price' => 20.0, 'totalPrice' => 24.4, 'vat' => 4.4],
    ]);

    expect($this->cart->count())->toBe(1)
        ->and($this->cart->search(fn ($c) => $c->id === '1'))->toHaveCount(0)
        ->and($this->cart->search(fn ($c) => $c->id === '2'))->toHaveCount(1);
});

it('updates qty for existing items', function (): void {
    $this->cart->add('1', 'Product A', '', 2, 10.0, 12.2, 2.2);

    $this->cart->sync([
        ['id' => '1', 'name' => 'Product A', 'qty' => 5, 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2],
    ]);

    expect($this->cart->count())->toBe(5);
});

it('handles empty sync array clearing cart', function (): void {
    $this->cart->add('1', 'Product A', '', 2, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Product B', '', 1, 20.0, 24.4, 4.4);

    $this->cart->sync([]);

    expect($this->cart->count())->toBe(0);
});

it('returns static for chaining', function (): void {
    expect($this->cart->sync([]))->toBeInstanceOf(Cart::class);
});

it('handles mixed add, update and remove in one sync', function (): void {
    $this->cart->add('1', 'Product A', '', 1, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Product B', '', 1, 20.0, 24.4, 4.4);

    $this->cart->sync([
        ['id' => '1', 'name' => 'Product A', 'qty' => 3, 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2],
        ['id' => '3', 'name' => 'Product C', 'qty' => 1, 'price' => 5.0, 'totalPrice' => 6.1, 'vat' => 1.1],
    ]);

    // Product 1: qty updated to 3; Product 2: removed; Product 3: added
    expect($this->cart->count())->toBe(4)  // 3 + 1
        ->and($this->cart->search(fn ($c) => $c->id === '2'))->toHaveCount(0)
        ->and($this->cart->search(fn ($c) => $c->id === '3'))->toHaveCount(1);
});
