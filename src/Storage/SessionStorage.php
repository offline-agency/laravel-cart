<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Illuminate\Session\SessionManager;
use OfflineAgency\LaravelCart\Contracts\CartStorage;

class SessionStorage implements CartStorage
{
    public function __construct(
        private readonly SessionManager $session,
    ) {}

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
