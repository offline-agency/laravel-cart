<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Testing;

use Illuminate\Contracts\Events\Dispatcher;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use PHPUnit\Framework\Assert as PHPUnit;

class CartFake extends Cart
{
    /** @var array<class-string, list<object>> */
    private array $dispatched = [];

    public function __construct(CartStorage $storage, Dispatcher $events)
    {
        // Create a recording wrapper
        $recorder = new class($events, $this->dispatched) implements Dispatcher
        {
            /** @var array<class-string, list<object>> */
            private array $dispatched; // @phpstan-ignore property.onlyWritten

            public function __construct(
                private readonly Dispatcher $inner,
                array &$dispatched,
            ) {
                $this->dispatched = &$dispatched;
            }

            public function dispatch($event, $payload = [], $halt = false)
            {
                if (is_object($event)) {
                    $this->dispatched[get_class($event)][] = $event;
                }

                return $this->inner->dispatch($event, $payload, $halt);
            }

            public function listen($events, $listener = null): void
            {
                $this->inner->listen($events, $listener);
            }

            public function hasListeners($eventName): bool
            {
                return $this->inner->hasListeners($eventName);
            }

            public function subscribe($subscriber): void
            {
                $this->inner->subscribe($subscriber);
            }

            public function until($event, $payload = [])
            {
                return $this->inner->until($event, $payload);
            }

            public function push($event, $payload = []): void
            {
                $this->inner->push($event, $payload);
            }

            public function flush($event): void
            {
                $this->inner->flush($event);
            }

            public function forget($event): void
            {
                $this->inner->forget($event);
            }

            public function forgetPushed(): void
            {
                $this->inner->forgetPushed();
            }
        };

        parent::__construct($storage, $recorder);
    }

    /**
     * Assert that the given event was dispatched.
     *
     * @param  class-string  $eventClass
     */
    public function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        PHPUnit::assertTrue(
            isset($this->dispatched[$eventClass]) && count($this->dispatched[$eventClass]) > 0,
            "The expected [{$eventClass}] event was not dispatched."
        );

        if ($callback !== null) {
            $matching = array_filter($this->dispatched[$eventClass], $callback);
            PHPUnit::assertNotEmpty(
                $matching,
                "The expected [{$eventClass}] event was dispatched, but no matching callback was found."
            );
        }
    }

    /**
     * Assert that the given event was not dispatched.
     *
     * @param  class-string  $eventClass
     */
    public function assertNotDispatched(string $eventClass): void
    {
        PHPUnit::assertFalse(
            isset($this->dispatched[$eventClass]) && count($this->dispatched[$eventClass]) > 0,
            "The unexpected [{$eventClass}] event was dispatched."
        );
    }

    /**
     * Assert the given event was dispatched exactly the given number of times.
     *
     * @param  class-string  $eventClass
     */
    public function assertDispatchedTimes(string $eventClass, int $times = 1): void
    {
        $actual = count($this->dispatched[$eventClass] ?? []);

        PHPUnit::assertSame(
            $times,
            $actual,
            "Expected [{$eventClass}] to be dispatched {$times} time(s), but was dispatched {$actual} time(s)."
        );
    }
}
