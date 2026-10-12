<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use OfflineAgency\LaravelCart\Http\Middleware\CartMergeMiddleware;
use OfflineAgency\LaravelCart\Http\Middleware\CartRestoreMiddleware;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();

    $this->artisan('migrate', ['--database' => 'testing']);

    $this->next = fn (Request $request): Response => new Response('ok');
});

function middlewareCovRequest(?int $userId = null): Request
{
    $request = Request::create('/');
    $request->setUserResolver(fn () => $userId === null ? null : new GenericUser(['id' => $userId]));

    return $request;
}

function middlewareCovStoreCartFor(mixed $cart, int $userId): void
{
    $cart->add('99', 'Stored Product', '', 2, 10.0, 12.2, 2.2);
    $cart->store($userId);
    $cart->destroy();
}

// ── CartMergeMiddleware ────────────────────────────────────────────────────

it('merge middleware does nothing when merge_on_login is disabled', function (): void {
    config(['cart.middleware.merge_on_login' => false]);
    middlewareCovStoreCartFor($this->cart, 1);

    $response = (new CartMergeMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(0);
});

it('merge middleware does nothing for guests', function (): void {
    config(['cart.middleware.merge_on_login' => true]);

    $response = (new CartMergeMiddleware($this->cart))->handle(middlewareCovRequest(), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(0);
});

it('merge middleware restores and merges the stored cart once per session', function (): void {
    config(['cart.middleware.merge_on_login' => true]);
    middlewareCovStoreCartFor($this->cart, 1);

    $this->cart->add('1', 'Guest Product', '', 1, 5.0, 6.1, 1.1);

    $middleware = new CartMergeMiddleware($this->cart);
    $response = $middleware->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(3)
        ->and(session()->has('cart_guest_merged_'.session()->getId()))->toBeTrue();

    // Second request in the same session must not merge again
    $this->cart->destroy();
    $middleware->handle(middlewareCovRequest(1), $this->next);

    expect($this->cart->count())->toBe(0);
});

it('merge middleware swallows restore failures', function (): void {
    config([
        'cart.middleware.merge_on_login' => true,
        'cart.database.connection' => 'cov-missing-connection',
    ]);

    $response = (new CartMergeMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and(session()->has('cart_guest_merged_'.session()->getId()))->toBeTrue();
});

// ── CartRestoreMiddleware ──────────────────────────────────────────────────

it('restore middleware does nothing when auto_restore is disabled', function (): void {
    config(['cart.middleware.auto_restore' => false]);
    middlewareCovStoreCartFor($this->cart, 1);

    $response = (new CartRestoreMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(0);
});

it('restore middleware does nothing for guests', function (): void {
    config(['cart.middleware.auto_restore' => true]);

    $response = (new CartRestoreMiddleware($this->cart))->handle(middlewareCovRequest(), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(0);
});

it('restore middleware restores the stored cart when the session cart is empty', function (): void {
    config(['cart.middleware.auto_restore' => true]);
    middlewareCovStoreCartFor($this->cart, 1);

    $response = (new CartRestoreMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(2);
});

it('restore middleware skips restoring when the session cart is not empty', function (): void {
    config(['cart.middleware.auto_restore' => true]);
    middlewareCovStoreCartFor($this->cart, 1);

    $this->cart->add('1', 'Guest Product', '', 1, 5.0, 6.1, 1.1);

    (new CartRestoreMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($this->cart->count())->toBe(1);
});

it('restore middleware swallows restore failures', function (): void {
    config([
        'cart.middleware.auto_restore' => true,
        'cart.database.connection' => 'cov-missing-connection',
    ]);

    $response = (new CartRestoreMiddleware($this->cart))->handle(middlewareCovRequest(1), $this->next);

    expect($response->getContent())->toBe('ok')
        ->and($this->cart->count())->toBe(0);
});
