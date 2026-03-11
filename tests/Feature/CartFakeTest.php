<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Facades\Cart as CartFacade;

beforeEach(function (): void {
    $this->app->make('cart')->destroy();
});

it('Cart::fake() returns a Cart instance', function (): void {
    $cart = CartFacade::fake();

    expect($cart)->toBeInstanceOf(Cart::class);
});

it('Cart::fake() swaps the container binding so Facade resolves the fake', function (): void {
    $fake = CartFacade::fake();

    CartFacade::add('1', 'Fake Product', '', 1, 9.99);

    expect(CartFacade::count())->toBe(1)
        ->and($fake->count())->toBe(1);
});

it('Cart::fake() operations work without database interaction', function (): void {
    CartFacade::fake();

    CartFacade::add('1', 'Alpha', '', 2, 10.0, 12.2, 2.2);
    CartFacade::add('2', 'Beta', '', 1, 5.0, 6.1, 1.1);

    expect(CartFacade::count())->toBe(3)
        ->and(CartFacade::uniqueCount())->toBe(2)
        ->and(CartFacade::isEmpty())->toBeFalse();
});

it('Cart::fake() can be used without running migrations', function (): void {
    CartFacade::fake();

    expect(fn () => CartFacade::add('1', 'Item', '', 1, 5.0))->not->toThrow(Exception::class);
});
