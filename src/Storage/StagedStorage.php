<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Storage;

use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Contracts\CartStorage;

/** @internal Detached candidate values; no writes reach the driver until flush. */
final class StagedStorage implements CartStorage
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var array<string, bool> */
    private array $dirty = [];

    public function __construct(private readonly CartStorage $storage) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = self::copy($this->storage->get($key));
        }

        return $this->values[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
        $this->dirty[$key] = true;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function remove(string $key): void
    {
        $this->values[$key] = null;
        $this->dirty[$key] = true;
    }

    public function flush(): void
    {
        foreach ($this->dirty as $key => $_) {
            if ($this->values[$key] === null) {
                $this->storage->remove($key);
            } else {
                $this->storage->put($key, $this->values[$key]);
            }
        }
    }

    private static function copy(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->map(fn (mixed $entry): mixed => self::copy($entry));
        }
        if ($value instanceof CartItem) {
            $item = clone $value;
            $item->options = clone $value->options;
            $item->createdAt = clone $value->createdAt;
            $item->updatedAt = clone $value->updatedAt;
            $item->appliedCoupons = array_map(fn (object $coupon): object => clone $coupon, $value->appliedCoupons);

            return $item;
        }
        if (is_array($value)) {
            return array_map(fn (mixed $entry): mixed => self::copy($entry), $value);
        }

        return $value;
    }
}
