<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Support;

use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Exceptions\TaxAllocationException;
use OfflineAgency\LaravelCart\TaxBreakdown;
use RoundingMode;

/**
 * @internal
 *
 * @phpstan-import-type Amounts from TaxBreakdown
 * @phpstan-import-type TaxRow from TaxBreakdown
 */
final class TaxAllocator
{
    private readonly int $precision;

    private readonly RoundingMode $mode;

    public function __construct()
    {
        $this->precision = CartConfig::decimals();
        if ($this->precision < 0 || $this->precision > 4) {
            throw new TaxAllocationException('Tax allocation requires a precision from 0 to 4.');
        }
        $mode = CartConfig::roundingMode();
        $this->mode = $mode instanceof RoundingMode ? $mode : match ($mode) {
            PHP_ROUND_HALF_UP => RoundingMode::HalfAwayFromZero,
            PHP_ROUND_HALF_DOWN => RoundingMode::HalfTowardsZero,
            PHP_ROUND_HALF_EVEN => RoundingMode::HalfEven,
            PHP_ROUND_HALF_ODD => RoundingMode::HalfOdd,
        };
    }

    /**
     * @param  Collection<string, CartItem>  $items
     * @param  numeric-string  $discount
     */
    public function allocate(Collection $items, float $gross, string $discount, float $payable): TaxBreakdown
    {
        $gross = $this->decimal($gross);
        $payable = $this->decimal($payable);
        $discount = bcadd($discount, '0', $this->precision);
        if (bccomp(bcsub($gross, $discount, $this->precision), $payable, $this->precision) !== 0) {
            throw new TaxAllocationException('Legacy payable rounding cannot be reconciled; revalidate the cart monetary inputs.');
        }
        $weights = $nets = $rates = [];
        foreach ($items as $rowId => $item) {
            if ($item->name === 'discountCartItem') {
                throw new TaxAllocationException('Tax allocation does not support legacy adjustment rows.');
            }
            $qty = $this->decimal((float) $item->qty);
            if (bccomp($qty, '0', 4) <= 0) {
                throw new TaxAllocationException('Tax allocation requires positive quantities.');
            }
            $net = $this->decimal($item->price);
            $vat = $this->decimal($item->vat);
            $unitGross = $this->decimal($item->totalPrice);
            $baseNet = $this->decimal($item->originalPrice);
            $baseVat = $this->decimal($item->originalVat);
            $baseGross = $this->decimal($item->originalTotalPrice);
            if (bccomp(bcadd($net, $vat, 4), $unitGross, 4) !== 0 || bccomp(bcadd($baseNet, $baseVat, 4), $baseGross, 4) !== 0) {
                throw new TaxAllocationException('Tax allocation requires consistent net + VAT = gross unit amounts.');
            }
            $remaining = bcsub($qty, '1', 4);
            $weights[$rowId] = bcadd($unitGross, bcmul($remaining, $baseGross, 8), 8);
            $nets[$rowId] = bcadd($net, bcmul($remaining, $baseNet, 8), 8);
            if (bccomp($weights[$rowId], '0', 8) < 0 || bccomp($nets[$rowId], '0', 8) < 0 || bccomp($nets[$rowId], $weights[$rowId], 8) > 0) {
                throw new TaxAllocationException('Tax allocation does not support negative row contributions.');
            }
            $rates[$rowId] = $this->decimal((float) $item->vatRate, 9999.9999);
        }
        $before = $this->apportion($this->units($gross), $weights);
        $shares = $this->apportion($this->units($discount), $before);
        $rows = $groups = [];
        $totals = $this->zero();
        foreach ($before as $rowId => $units) {
            $rowGross = $this->amount(bcsub($units, $shares[$rowId], 0));
            $net = bccomp($shares[$rowId], '0', 0) === 0
                ? $this->round($nets[$rowId])
                : $this->round(bcdiv(bcmul($rowGross, $nets[$rowId], 24), $weights[$rowId], 24));
            if (bccomp($net, $rowGross, $this->precision) > 0) {
                $net = $rowGross;
            }
            $amounts = ['net' => $net, 'vat' => bcsub($rowGross, $net, $this->precision), 'gross' => $rowGross, 'discount' => $this->amount($shares[$rowId])];
            $rows[$rowId] = ['rowId' => $rowId, 'rate' => $rates[$rowId], 'grossBeforeDiscount' => $this->round($weights[$rowId]),
                'roundingAdjustment' => bcsub($this->amount($units), $this->round($weights[$rowId]), $this->precision), ...$amounts];
            $group = $groups[$rates[$rowId]] ?? $this->zero();
            foreach ($amounts as $key => $amount) {
                $group[$key] = bcadd($group[$key], $amount, $this->precision);
                $totals[$key] = bcadd($totals[$key], $amount, $this->precision);
            }
            $groups[$rates[$rowId]] = $group;
        }
        ksort($groups, SORT_STRING);

        return new TaxBreakdown($this->precision, $rows, $groups, $totals);
    }

    /** @return numeric-string */
    private function decimal(float $value, float $maximum = 99999999.9999): string
    {
        $decimal = number_format($value, 4, '.', '');
        if (! is_finite($value) || $value < 0 || $value > $maximum || (float) $decimal !== $value) {
            throw new TaxAllocationException('Tax allocation requires finite, nonnegative schema-range amounts with at most four decimal places.');
        }

        return $decimal;
    }

    /** @param numeric-string $value
     * @return numeric-string
     */
    private function round(string $value): string
    {
        return bcround($value, $this->precision, $this->mode);
    }

    /** @param numeric-string $amount
     * @return numeric-string
     */
    private function units(string $amount): string
    {
        return bcmul($amount, bcpow('10', (string) $this->precision, 0), 0);
    }

    /** @param numeric-string $units
     * @return numeric-string
     */
    private function amount(string $units): string
    {
        return bcdiv($units, bcpow('10', (string) $this->precision, 0), $this->precision);
    }

    /**
     * Exact quotient/remainder comparisons avoid floating allocation ratios.
     *
     * @param  numeric-string  $target
     * @param  array<string, numeric-string>  $weights
     * @return array<string, numeric-string>
     */
    private function apportion(string $target, array $weights): array
    {
        ksort($weights, SORT_STRING);
        $sum = '0';
        foreach ($weights as $weight) {
            $sum = bcadd($sum, $weight, 8);
        }
        $allocated = $remainders = [];
        $used = '0';
        foreach ($weights as $id => $weight) {
            $product = bcmul($target, $weight, 8);
            $allocated[$id] = bccomp($sum, '0', 8) === 0 ? '0' : bcdiv($product, $sum, 0);
            $remainders[$id] = bcsub($product, bcmul($allocated[$id], $sum, 8), 8);
            $used = bcadd($used, $allocated[$id], 0);
        }
        uksort($remainders, fn (string $a, string $b): int => bccomp($remainders[$b], $remainders[$a], 8) ?: strcmp($a, $b));
        $left = (int) bcsub($target, $used, 0);
        foreach ($remainders as $id => $remainder) {
            if ($left-- <= 0) {
                break;
            }
            $allocated[$id] = bcadd($allocated[$id], '1', 0);
        }

        return $allocated;
    }

    /** @return Amounts */
    private function zero(): array
    {
        $zero = bcadd('0', '0', $this->precision);

        return ['net' => $zero, 'vat' => $zero, 'gross' => $zero, 'discount' => $zero];
    }
}
