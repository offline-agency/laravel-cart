<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Exceptions\CartExpiredException;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Exceptions\InvalidDriverException;
use OfflineAgency\LaravelCart\Resources\CartItemResource;
use OfflineAgency\LaravelCart\Resources\CartResource;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

it('builds a default cart expired message', function () {
    $previous = new RuntimeException('prev');
    $e = new CartExpiredException('abc', '', 5, $previous);

    expect($e->identifier)->toBe('abc')
        ->and($e->getMessage())->toBe('The cart for identifier [abc] has expired.')
        ->and($e->getCode())->toBe(5)
        ->and($e->getPrevious())->toBe($previous)
        ->and((new CartExpiredException('abc', 'Custom'))->getMessage())->toBe('Custom');
});

it('builds a default cart limit exceeded message', function () {
    $e = new CartLimitExceededException('max_items', 10, 11);

    expect($e->limitType)->toBe('max_items')
        ->and($e->limit)->toBe(10)
        ->and($e->attempted)->toBe(11)
        ->and($e->getMessage())->toBe('Cart limit exceeded: max_items (max: 10).')
        ->and((new CartLimitExceededException('max_items', 10, 11, 'Custom'))->getMessage())->toBe('Custom');
});

it('builds a default invalid driver message', function () {
    $e = new InvalidDriverException('redis');

    expect($e->driver)->toBe('redis')
        ->and($e->getMessage())->toBe('The cart storage driver [redis] is not supported.')
        ->and((new InvalidDriverException('redis', 'Custom'))->getMessage())->toBe('Custom');
});

it('throws an invalid driver exception for unsupported storage drivers', function () {
    config(['cart.storage.driver' => 'redis']);
    $this->app->forgetInstance(CartStorage::class);

    $this->app->make(CartStorage::class);
})->throws(InvalidDriverException::class, 'The cart storage driver [redis] is not supported.');

it('resolves the database storage driver', function () {
    config(['cart.storage.driver' => 'database']);
    $this->app->forgetInstance(CartStorage::class);

    expect($this->app->make(CartStorage::class))->toBeInstanceOf(DatabaseStorage::class);
});

it('transforms a cart item into an array resource', function () {
    $item = new CartItem(1, 'Item', 'Sub', 2, 10.0, 12.2, '', '', 2.2);

    $data = (new CartItemResource($item))->toArray(Request::create('/'));

    expect($data)->toBe($item->toArray());
});

it('transforms a cart into an array resource', function () {
    /** @var Cart $cart */
    $cart = $this->app->make('cart');
    $cart->add(1, 'Item', 'Sub', 1, 10.0, 12.2, 2.2);

    $data = (new CartResource($cart))->toArray(Request::create('/'));

    expect($data)->toBe($cart->toArray());
});
