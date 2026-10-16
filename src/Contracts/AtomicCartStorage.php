<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Contracts;

use Closure;

/** Optional coordination for package-managed read/modify/write operations. */
interface AtomicCartStorage extends CartStorage
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    public function atomic(string $instance, Closure $operation): mixed;

    public function afterCommit(Closure $callback): void;
}
