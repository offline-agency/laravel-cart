<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Testing;

use Illuminate\Contracts\Events\Dispatcher;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use PHPUnit\Framework\Assert as PHPUnit;

class CartFake extends Cart
{
    private readonly RecordingDispatcher $recorder;

    public function __construct(CartStorage $storage, Dispatcher $events)
    {
        $this->recorder = new RecordingDispatcher($events);

        parent::__construct($storage, $this->recorder);
    }

    /**
     * The dispatcher recording the events this cart dispatches.
     */
    public function recorder(): RecordingDispatcher
    {
        return $this->recorder;
    }

    /**
     * Assert that the given event was dispatched.
     *
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @param  (callable(TEvent): bool)|null  $callback
     */
    public function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        $recorded = $this->recorder->recorded($eventClass);

        PHPUnit::assertTrue(
            count($recorded) > 0,
            "The expected [{$eventClass}] event was not dispatched."
        );

        if ($callback !== null) {
            $matching = array_filter($recorded, $callback);
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
            count($this->recorder->recorded($eventClass)) > 0,
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
        $actual = count($this->recorder->recorded($eventClass));

        PHPUnit::assertSame(
            $times,
            $actual,
            "Expected [{$eventClass}] to be dispatched {$times} time(s), but was dispatched {$actual} time(s)."
        );
    }
}
