<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Examples\QuickStart;
use OfflineAgency\LaravelCart\Resources\CartResource;

it('executes the maintained quick start on both built-in drivers', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    require_once __DIR__.'/../../examples/quick-start.php';
    $cart = resolveCart();
    $result = QuickStart::run($cart);
    expect($result['payable'])->toBe(45.36)
        ->and($cart->subtotal())->toBe(38.0)->and($cart->vat())->toBe(8.36)
        ->and($cart->get($result['rowId'])->total)->toBe(46.36);
    $resource = (new CartResource($cart))->toArray(request());
    expect($resource)->toHaveKey('total', 45.36);
})->with(['session', 'database']);
