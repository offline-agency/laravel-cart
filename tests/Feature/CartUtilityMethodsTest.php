<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('isEmpty returns true on a fresh cart', function (): void {
    expect($this->cart->isEmpty())->toBeTrue();
});

it('isEmpty returns false after adding an item', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);

    expect($this->cart->isEmpty())->toBeFalse();
});

it('isNotEmpty returns false on a fresh cart', function (): void {
    expect($this->cart->isNotEmpty())->toBeFalse();
});

it('isNotEmpty returns true after adding an item', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);

    expect($this->cart->isNotEmpty())->toBeTrue();
});

it('first returns null on an empty cart', function (): void {
    expect($this->cart->first())->toBeNull();
});

it('first returns the first CartItem when no callback is given', function (): void {
    $this->cart->add('1', 'Product A', '', 1, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Product B', '', 1, 20.0, 24.4, 4.4);

    expect($this->cart->first())->toBeInstanceOf(CartItem::class);
});

it('first with a closure returns matching item', function (): void {
    $this->cart->add('1', 'Widget', '', 1, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Gadget', '', 1, 20.0, 24.4, 4.4);

    $item = $this->cart->first(fn (CartItem $i): bool => $i->name === 'Gadget');

    expect($item)->not->toBeNull()
        ->and($item->name)->toBe('Gadget');
});

it('first with a closure returns null when nothing matches', function (): void {
    $this->cart->add('1', 'Widget', '', 1, 10.0, 12.2, 2.2);

    $item = $this->cart->first(fn (CartItem $i): bool => $i->name === 'Nonexistent');

    expect($item)->toBeNull();
});

it('where returns matching items by attribute', function (): void {
    $this->cart->add('1', 'Widget', '', 1, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Gadget', '', 1, 20.0, 24.4, 4.4);

    $results = $this->cart->where('name', 'Widget');

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Widget');
});

it('where returns empty collection when no items match', function (): void {
    $this->cart->add('1', 'Widget', '', 1, 10.0, 12.2, 2.2);

    $results = $this->cart->where('name', 'Nonexistent');

    expect($results)->toBeInstanceOf(Collection::class)
        ->and($results)->toHaveCount(0);
});

it('uniqueCount returns number of unique rows, not quantity sum', function (): void {
    $this->cart->add('1', 'Product A', '', 5, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'Product B', '', 3, 20.0, 24.4, 4.4);

    // 2 unique rows even though total qty is 8
    expect($this->cart->uniqueCount())->toBe(2)
        ->and($this->cart->count())->toBe(8);
});

it('uniqueCount returns 0 for an empty cart', function (): void {
    expect($this->cart->uniqueCount())->toBe(0);
});

it('uniqueCount returns 1 for a single item', function (): void {
    $this->cart->add('1', 'Product A', '', 10, 10.0, 12.2, 2.2);

    expect($this->cart->uniqueCount())->toBe(1);
});
