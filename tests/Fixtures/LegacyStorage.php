<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;

/** Custom-driver fixture implementing only the unchanged legacy interface. */
final class LegacyStorage implements CartStorage
{
    public function __construct(private readonly SessionStorage $inner) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->inner->get($key, $default);
    }

    public function put(string $key, mixed $value): void
    {
        $this->inner->put($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->inner->has($key);
    }

    public function remove(string $key): void
    {
        $this->inner->remove($key);
    }
}
