<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Contracts;

interface CartStorage
{
    /**
     * Get a value from storage.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Put a value into storage.
     */
    public function put(string $key, mixed $value): void;

    /**
     * Check if a key exists in storage.
     */
    public function has(string $key): bool;

    /**
     * Remove a key from storage.
     */
    public function remove(string $key): void;
}
