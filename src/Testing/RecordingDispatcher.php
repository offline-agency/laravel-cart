<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Testing;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Event dispatcher decorator used by CartFake: records every object event it
 * dispatches, then forwards all calls to the wrapped dispatcher.
 */
final class RecordingDispatcher implements Dispatcher
{
    /** @var array<class-string, list<object>> */
    private array $dispatched = [];

    public function __construct(
        private readonly Dispatcher $inner,
    ) {}

    /**
     * The recorded events of the given class, in dispatch order.
     *
     * @template TEvent of object
     *
     * @param  class-string<TEvent>  $eventClass
     * @return list<TEvent>
     */
    public function recorded(string $eventClass): array
    {
        return array_values(array_filter(
            $this->dispatched[$eventClass] ?? [],
            fn (object $event): bool => $event instanceof $eventClass,
        ));
    }

    /**
     * @param  string|object  $event
     * @param  mixed  $payload
     * @param  bool  $halt
     */
    public function dispatch($event, $payload = [], $halt = false): mixed
    {
        if (is_object($event)) {
            $this->dispatched[$event::class][] = $event;
        }

        return $this->inner->dispatch($event, $payload, $halt);
    }

    /**
     * @param  Closure|string|array<array-key, mixed>  $events
     * @param  Closure|string|array<array-key, mixed>|null  $listener
     */
    public function listen($events, $listener = null): void
    {
        $this->inner->listen($events, $listener);
    }

    /**
     * @param  string  $eventName
     */
    public function hasListeners($eventName): bool
    {
        return $this->inner->hasListeners($eventName);
    }

    /**
     * @param  object|string  $subscriber
     */
    public function subscribe($subscriber): void
    {
        $this->inner->subscribe($subscriber);
    }

    /**
     * @param  string|object  $event
     * @param  mixed  $payload
     */
    public function until($event, $payload = []): mixed
    {
        return $this->inner->until($event, $payload);
    }

    /**
     * @param  string  $event
     * @param  array<array-key, mixed>  $payload
     */
    public function push($event, $payload = []): void
    {
        $this->inner->push($event, $payload);
    }

    /**
     * @param  string  $event
     */
    public function flush($event): void
    {
        $this->inner->flush($event);
    }

    /**
     * @param  string  $event
     */
    public function forget($event): void
    {
        $this->inner->forget($event);
    }

    public function forgetPushed(): void
    {
        $this->inner->forgetPushed();
    }
}
