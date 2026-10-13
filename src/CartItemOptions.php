<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Illuminate\Support\Collection;

/**
 * @extends Collection<array-key, mixed>
 */
class CartItemOptions extends Collection
{
    /**
     * Get the option by the given key.
     *
     * @param  array-key  $key
     */
    public function __get($key): mixed
    {
        return $this->get($key);
    }
}
