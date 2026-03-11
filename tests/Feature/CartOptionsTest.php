<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

// ── getOptions / setOptions ───────────────────────────────────────────────────

it('getOptions returns an empty array on a fresh cart', function (): void {
    expect($this->cart->getOptions())->toBe([]);
});

it('setOptions stores arbitrary key-value data', function (): void {
    $this->cart->setOptions(['channel' => 'web', 'locale' => 'it']);

    expect($this->cart->getOptions())->toBe(['channel' => 'web', 'locale' => 'it']);
});

it('setOptions overwrites previously stored options', function (): void {
    $this->cart->setOptions(['foo' => 'bar']);
    $this->cart->setOptions(['baz' => 'qux']);

    expect($this->cart->getOptions())->toBe(['baz' => 'qux'])
        ->and($this->cart->getOptions())->not->toHaveKey('foo');
});

it('options persist in the session between cart instances', function (): void {
    $this->cart->setOptions(['shipping' => 'express']);

    $cart2 = $this->app->make('cart');

    expect($cart2->getOptions())->toBe(['shipping' => 'express']);
});

// ── getOptionsByKey ───────────────────────────────────────────────────────────

it('getOptionsByKey returns the value for an existing key', function (): void {
    $this->cart->setOptions(['note' => 'gift wrap please']);

    expect($this->cart->getOptionsByKey('note'))->toBe('gift wrap please');
});

it('getOptionsByKey returns null when key does not exist', function (): void {
    $this->cart->setOptions(['a' => '1']);

    expect($this->cart->getOptionsByKey('nonexistent'))->toBeNull();
});

it('getOptionsByKey returns the supplied default when key is absent', function (): void {
    $this->cart->setOptions([]);

    expect($this->cart->getOptionsByKey('missing', 'default-value'))->toBe('default-value');
});

it('getOptionsByKey returns null as default when key is absent and no default given', function (): void {
    expect($this->cart->getOptionsByKey('k'))->toBeNull();
});

// ── Options are cleared on destroy ───────────────────────────────────────────

it('destroy clears all cart options', function (): void {
    $this->cart->setOptions(['promo' => 'blackfriday']);

    $this->cart->destroy();

    expect($this->cart->getOptions())->toBe([]);
});

// ── Options are instance-scoped ───────────────────────────────────────────────

it('options are scoped to the current cart instance', function (): void {
    $this->cart->instance('shopping')->setOptions(['discount_code' => 'SAVE10']);
    $this->cart->instance('wishlist')->setOptions(['discount_code' => 'WISH5']);

    expect($this->cart->instance('shopping')->getOptionsByKey('discount_code'))->toBe('SAVE10')
        ->and($this->cart->instance('wishlist')->getOptionsByKey('discount_code'))->toBe('WISH5');
});

it('options support nested array values', function (): void {
    $this->cart->setOptions(['meta' => ['source' => 'email', 'campaign' => 'spring']]);

    expect($this->cart->getOptionsByKey('meta'))->toBe(['source' => 'email', 'campaign' => 'spring']);
});
