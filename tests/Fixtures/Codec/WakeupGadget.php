<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures\Codec;

/**
 * Not a cart class: decoding must never run its __wakeup().
 */
final class WakeupGadget
{
    public static bool $wokenUp = false;

    public function __wakeup(): void
    {
        self::$wokenUp = true;
    }
}
