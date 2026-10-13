<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

/**
 * Issue #35: an item added with only id, name, qty and price (no totalPrice,
 * VAT or fiscal codes) had totalPrice 0.0, required fiscal codes, and was
 * not persisted.
 */
beforeEach(function (): void {
    config(['cart.tax.default_rate' => 0.0]);
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('computes totalPrice from price when totalPrice and VAT are omitted', function (): void {
    $cart = resolveCart();

    $item = $cart->add('086285', 'Лопатка барбекю', '', 1, 800.0);

    expect($item)->toBeInstanceOf(CartItem::class)
        ->and($item->totalPrice)->toBe(800.0)
        ->and($item->originalTotalPrice)->toBe(800.0)
        ->and($item->vat)->toBe(0.0)
        ->and($cart->total())->toEqual(800.0);
});

it('computes totalPrice when adding from an attributes array', function (): void {
    $item = resolveCart()->add(['id' => '086285', 'name' => 'Лопатка барбекю', 'qty' => 1, 'price' => 800.0]);

    expect($item)->toBeInstanceOf(CartItem::class)
        ->and($item instanceof CartItem ? $item->totalPrice : null)->toBe(800.0);
});

it('does not require fiscal codes', function (): void {
    $item = resolveCart()->add('086285', 'Лопатка барбекю', '', 1, 800.0);

    expect($item)->toBeInstanceOf(CartItem::class)
        ->and($item->vatFcCode)->toBe('')
        ->and($item->productFcCode)->toBe('');
});

it('stores and restores a minimal item', function (): void {
    $cart = resolveCart();
    $cart->add('086285', 'Лопатка барбекю', '', 1, 800.0);

    $cart->store('issue-35');
    $cart->destroy();
    $cart->restore('issue-35');

    $restored = $cart->content()->first();

    expect($restored)->toBeInstanceOf(CartItem::class)
        ->and($restored instanceof CartItem ? $restored->totalPrice : null)->toBe(800.0);
});

it('persists a minimal item with the database driver', function (): void {
    $storage = fn (): Cart => new Cart(new DatabaseStorage(app(DatabaseManager::class)), app('events'));

    $item = $storage()->add('086285', 'Лопатка барбекю', '', 1, 800.0);

    $loaded = $storage()->get($item->rowId);

    expect($loaded->totalPrice)->toBe(800.0)
        ->and($loaded->name)->toBe('Лопатка барбекю');
});
