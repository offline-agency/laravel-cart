<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait InteractsWithCartDatabase
{
    private function cartQuery(): Builder
    {
        return DB::connection($this->connectionName())
            ->table($this->tableName());
    }

    private function tableName(): string
    {
        return config('cart.database.table', 'cart');
    }

    private function connectionName(): string
    {
        return config('cart.database.connection') ?? config('database.default');
    }

    private function deserializeContent(string $content): Collection
    {
        try {
            $result = unserialize($content);

            return $result instanceof Collection ? $result : collect();
        } catch (\Throwable) {
            return collect();
        }
    }

    private function decodeCoupons(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
