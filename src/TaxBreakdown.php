<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @phpstan-type Amounts array{net: numeric-string, vat: numeric-string, gross: numeric-string, discount: numeric-string}
 * @phpstan-type TaxRow array{rowId: string, rate: numeric-string, grossBeforeDiscount: numeric-string, roundingAdjustment: numeric-string, net: numeric-string, vat: numeric-string, gross: numeric-string, discount: numeric-string}
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaxBreakdown implements Arrayable, JsonSerializable
{
    /**
     * @param  array<string, TaxRow>  $rows
     * @param  array<array-key, Amounts>  $rates
     * @param  Amounts  $totals
     */
    public function __construct(public int $precision, public array $rows, public array $rates, public array $totals) {}

    /** @return array{precision: int, rows: array<string, TaxRow>, rates: array<array-key, Amounts>, totals: Amounts} */
    public function toArray(): array
    {
        return ['precision' => $this->precision, 'rows' => $this->rows, 'rates' => $this->rates, 'totals' => $this->totals];
    }

    /** @return array{precision: int, rows: array<string, TaxRow>, rates: array<array-key, Amounts>, totals: Amounts} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
