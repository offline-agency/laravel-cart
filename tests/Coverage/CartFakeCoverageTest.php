<?php

declare(strict_types=1);

use Illuminate\Contracts\Events\Dispatcher;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CartItemRemoved;
use OfflineAgency\LaravelCart\Facades\Cart as CartFacade;
use PHPUnit\Framework\AssertionFailedError;

function cartFakeCovRecorder(Cart $cart): Dispatcher
{
    $property = new ReflectionProperty(Cart::class, 'events');

    /** @var Dispatcher $recorder */
    $recorder = $property->getValue($cart);

    return $recorder;
}

it('records dispatched events and asserts on them', function (): void {
    $fake = CartFacade::fake();

    CartFacade::add('1', 'Fake Product', '', 1, 9.99);
    CartFacade::add('2', 'Other Product', '', 1, 5.0);

    $fake->assertDispatched(CartItemAdded::class);
    $fake->assertDispatched(CartItemAdded::class, fn (CartItemAdded $event): bool => $event->cartItem->name === 'Other Product');
    $fake->assertDispatchedTimes(CartItemAdded::class, 2);
    $fake->assertNotDispatched(CartItemRemoved::class);
});

it('fails assertDispatched when the event was not dispatched', function (): void {
    $fake = CartFacade::fake();

    $fake->assertDispatched(CartItemAdded::class);
})->throws(AssertionFailedError::class, 'was not dispatched');

it('fails assertDispatched when no dispatched event matches the callback', function (): void {
    $fake = CartFacade::fake();
    CartFacade::add('1', 'Fake Product', '', 1, 9.99);

    $fake->assertDispatched(CartItemAdded::class, fn (): bool => false);
})->throws(AssertionFailedError::class, 'no matching callback');

it('fails assertNotDispatched when the event was dispatched', function (): void {
    $fake = CartFacade::fake();
    CartFacade::add('1', 'Fake Product', '', 1, 9.99);

    $fake->assertNotDispatched(CartItemAdded::class);
})->throws(AssertionFailedError::class, 'unexpected');

it('fails assertDispatchedTimes when the count does not match', function (): void {
    $fake = CartFacade::fake();

    $fake->assertDispatchedTimes(CartItemAdded::class, 1);
})->throws(AssertionFailedError::class, 'dispatched 0 time(s)');

it('does not record string events', function (): void {
    $fake = CartFacade::fake();
    $recorder = cartFakeCovRecorder($fake);

    $recorder->dispatch('cov.string.event');

    $fake->assertDispatchedTimes(CartItemAdded::class, 0);
});

it('proxies listener management to the inner dispatcher', function (): void {
    $fake = CartFacade::fake();
    $recorder = cartFakeCovRecorder($fake);
    $calls = [];

    $recorder->listen('cov.event', function (string $payload) use (&$calls): string {
        $calls[] = $payload;

        return 'handled-'.$payload;
    });

    expect($recorder->hasListeners('cov.event'))->toBeTrue()
        ->and($recorder->until('cov.event', ['first']))->toBe('handled-first');

    $recorder->push('cov.event', ['pushed']);
    $recorder->flush('cov.event');

    expect($calls)->toBe(['first', 'pushed']);

    $recorder->push('cov.event', ['never']);
    $recorder->forgetPushed();
    $recorder->flush('cov.event');

    expect($calls)->toBe(['first', 'pushed']);

    $recorder->forget('cov.event');

    expect($recorder->hasListeners('cov.event'))->toBeFalse();
});

it('proxies subscribers to the inner dispatcher', function (): void {
    $fake = CartFacade::fake();
    $recorder = cartFakeCovRecorder($fake);

    $subscriber = new class
    {
        /** @return array<string, string> */
        public function subscribe(Dispatcher $events): array
        {
            return ['cov.subscribed' => 'handle'];
        }

        public function handle(): void {}
    };

    $recorder->subscribe($subscriber);

    expect($recorder->hasListeners('cov.subscribed'))->toBeTrue();
});
