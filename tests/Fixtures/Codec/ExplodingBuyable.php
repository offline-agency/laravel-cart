<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures\Codec;

use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use RuntimeException;

/**
 * A Buyable whose unserialization fails, to exercise decode errors.
 */
class ExplodingBuyable extends BuyableProduct
{
    public function __wakeup(): void
    {
        throw new RuntimeException('boom');
    }
}
