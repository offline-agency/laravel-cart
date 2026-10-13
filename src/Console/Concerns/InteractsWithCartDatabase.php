<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Console\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartConfig;
use OfflineAgency\LaravelCart\Support\CartContentCodec;
use OfflineAgency\LaravelCart\Support\Coerce;

trait InteractsWithCartDatabase
{
    private function cartQuery(): Builder
    {
        return DB::connection($this->connectionName())
            ->table($this->tableName());
    }

    private function tableName(): string
    {
        return CartConfig::string('cart.database.table', 'cart');
    }

    private function connectionName(): string
    {
        return CartConfig::connection() ?? DB::getDefaultConnection();
    }

    /**
     * Decode a stored cart content column; null when it cannot be decoded.
     *
     * @return Collection<string, CartItem>|null
     */
    private function decodeContent(mixed $content): ?Collection
    {
        return CartContentCodec::decode(Coerce::nullableString($content));
    }

    /**
     * Decode a stored coupons column into the list of coupon arrays it holds.
     *
     * @return list<array<array-key, mixed>>
     */
    private function decodeCoupons(mixed $json): array
    {
        $json = Coerce::nullableString($json);

        if ($json === null || $json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? array_values(array_filter($decoded, is_array(...))) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
