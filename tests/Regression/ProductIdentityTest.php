<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\IdentityLookupModel;

it('does not alias distinct product identifiers for one model after reload', function (string $driver, int|string $stored, int|string $different): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $item = $cart->add(new BuyableProduct(id: $stored));
    $cart->associate($item->rowId, BuyableProduct::class);
    $cart = resolveCart();
    expect($cart->isAlreadyAdded($different, BuyableProduct::class))->toBeFalse()
        ->and($cart->searchById($different, BuyableProduct::class))->toBeNull()
        ->and($cart->get($item->rowId)->is(new BuyableProduct(id: $different)))->toBeFalse()
        ->and($cart->searchById($stored, BuyableProduct::class)?->rowId)->toBe($item->rowId);
})->with(['session', 'database'])->with([
    ['sku-red', 'sku-blue'], ['SKU', 'sku'],
    ['01906ca0-aaaa-7000-8000-000000000001', '01906ca0-aaaa-7000-8000-000000000002'],
    ['01JABCDEF0123456789012345A', '01JABCDEF0123456789012345B'],
    ['12-red', '12-blue'], ['01', 1], ['00', 0], ['00', '0'],
    ['1e2', 100], ['0e1', '0e2'], [' 42', 42],
    ['9223372036854775808', '9223372036854775809'],
]);

it('preserves integer and identical decimal string identities across variants and sync', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $item = $cart->add(new BuyableProduct(id: 42));
    $cart->associate($item->rowId, BuyableProduct::class);
    $same = $cart->add(new BuyableProduct(id: '42'));
    expect($same->rowId)->toBe($item->rowId)->and($cart->uniqueCount())->toBe(1);
    $cart = resolveCart();
    expect($cart->isAlreadyAdded('42', BuyableProduct::class))->toBeTrue()
        ->and($cart->searchById(42, BuyableProduct::class)?->rowId)->toBe($item->rowId)
        ->and($cart->get($item->rowId)->is(new BuyableProduct(id: '42')))->toBeTrue();
    $cart->sync([
        ['id' => '42', 'name' => 'Same', 'qty' => 3, 'price' => 10.0],
        ['id' => '042', 'name' => 'Leading zero', 'qty' => 1, 'price' => 10.0],
        ['id' => '42', 'name' => 'Variant', 'qty' => 1, 'price' => 10.0, 'options' => ['size' => 'L']],
    ]);
    expect($cart->uniqueCount())->toBe(3)->and($cart->get($item->rowId)->qty)->toBe(3);
})->with(['session', 'database']);

it('retains legacy rejection of zero row identifiers without aliasing zero lookup to a SKU', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $row = $cart->add(new BuyableProduct(id: 'sku-red'));
    $cart->associate($row->rowId, BuyableProduct::class);
    foreach ([0, '0'] as $zero) {
        expect($cart->isAlreadyAdded($zero, BuyableProduct::class))->toBeFalse()
            ->and($cart->searchById($zero, BuyableProduct::class))->toBeNull();
        expect(fn () => $cart->add(id: $zero, name: 'Zero', qty: 1, price: 1.0))->toThrow(InvalidArgumentException::class);
    }
    expect($cart->uniqueCount())->toBe(1);
})->with(['session', 'database']);

it('passes the unchanged canonical product key into associated model lookup', function (string $driver, string $id): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $row = $cart->add(id: $id, name: 'Lookup', qty: 1, price: 10.0);
    $cart->associate($row->rowId, IdentityLookupModel::class);
    $loaded = resolveCart()->get($row->rowId);
    expect($loaded->model)->toBe(IdentityLookupModel::class);
    $model = $loaded->__get('model'); // Audit the existing legacy resolver without changing the public raw field.
    expect($model)->toBeInstanceOf(IdentityLookupModel::class);
    if ($model instanceof IdentityLookupModel) {
        expect($model->selectedId)->toBe($id);
    }
})->with(['session', 'database'])->with(['sku-red', '042']);
