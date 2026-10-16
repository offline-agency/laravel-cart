<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Closure;
use Illuminate\Session\SessionManager;
use OfflineAgency\LaravelCart\Contracts\AtomicCartStorage;

class SessionStorage implements AtomicCartStorage
{
    public function __construct(
        private readonly SessionManager $session,
    ) {}

    public function atomic(string $instance, Closure $operation): mixed
    {
        $before = $this->session->all();
        try {
            return $operation();
        } catch (\Throwable $exception) {
            $this->session->replace($before);
            throw $exception;
        }
    }

    public function afterCommit(Closure $callback): void
    {
        $callback();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get($key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->session->put($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->session->has($key);
    }

    public function remove(string $key): void
    {
        $this->session->forget($key);
    }
}
