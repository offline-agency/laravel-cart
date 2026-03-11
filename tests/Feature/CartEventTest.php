<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemRemoved;
use OfflineAgency\LaravelCart\Events\CartItemUpdated;
use OfflineAgency\LaravelCart\Events\CartRestored;
use OfflineAgency\LaravelCart\Events\CartStored;
use OfflineAgency\LaravelCart\Events\CouponApplied;
use OfflineAgency\LaravelCart\Events\CouponRemoved;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->app['config']->set('cart.use_legacy_events', true);
    Event::fake();
    $this->app->forgetInstance('cart');
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

// ── use_legacy_events = true (default) ────────────────────────────────────────

it('dispatches CartItemAdded typed event on add (legacy on)', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);

    Event::assertDispatched(CartItemAdded::class);
    Event::assertDispatched('cart.added');
});

it('dispatches CartItemUpdated typed event on update (legacy on)', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $rowId = $this->cart->content()->keys()->first();

    Event::clearResolvedInstances();
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->update($rowId, 2);

    Event::assertDispatched(CartItemUpdated::class);
    Event::assertDispatched('cart.updated');
});

it('dispatches CartItemRemoved typed event on remove (legacy on)', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $rowId = $this->cart->content()->keys()->first();

    Event::clearResolvedInstances();
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->remove($rowId);

    Event::assertDispatched(CartItemRemoved::class);
    Event::assertDispatched('cart.removed');
});

it('dispatches CartStored typed event on store (legacy on)', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-event-test');

    Event::assertDispatched(CartStored::class);
    Event::assertDispatched('cart.stored');
});

it('dispatches CartRestored typed event on restore (legacy on)', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-restore-event');

    Event::clearResolvedInstances();
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->restore('user-restore-event');

    Event::assertDispatched(CartRestored::class);
    Event::assertDispatched('cart.restored');
});

// ── use_legacy_events = false ──────────────────────────────────────────────────

it('dispatches only typed CartItemAdded when use_legacy_events is false', function (): void {
    $this->app['config']->set('cart.use_legacy_events', false);

    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');
    $cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);

    Event::assertDispatched(CartItemAdded::class);
    Event::assertNotDispatched('cart.added');
});

it('dispatches CouponApplied and CouponRemoved events', function (): void {
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');

    $coupon = new CartCoupon(hash: 'h1', code: 'TEST', type: 'fixed', value: 5.0, isGlobal: true);
    $cart->addCoupon($coupon);
    $cart->removeCartCoupon('h1');

    Event::assertDispatched(CouponApplied::class, fn (CouponApplied $e): bool => $e->coupon->code === 'TEST');
    Event::assertDispatched(CouponRemoved::class, fn (CouponRemoved $e): bool => $e->coupon->code === 'TEST');
});

it('CartStored event carries identifier and instance', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('my-user-123');

    Event::assertDispatched(CartStored::class, function (CartStored $e): bool {
        return $e->identifier === 'my-user-123' && $e->instance === 'default';
    });
});
