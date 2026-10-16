<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Exceptions\TaxAllocationException;
use OfflineAgency\LaravelCart\Support\TaxAllocator;

it('allocates the independently specified mixed rate gross discount after reload', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $a = $cart->add(id: 1, name: 'A', qty: 1, price: 100.0, totalPrice: 122.0, vat: 22.0);
    $b = $cart->add(id: 2, name: 'B', qty: 1, price: 100.0, totalPrice: 110.0, vat: 10.0);
    $cart->addCoupon(new CartCoupon(hash: 'fixed', code: 'FIXED', type: 'fixed', value: 23.20));
    $cart = resolveCart();
    $result = $cart->payableTaxBreakdown();
    expect($result->rows[$a->rowId]['gross'])->toBe('109.80')
        ->and($result->rows[$a->rowId]['net'])->toBe('90.00')
        ->and($result->rows[$a->rowId]['vat'])->toBe('19.80')
        ->and($result->rows[$b->rowId]['gross'])->toBe('99.00')
        ->and($result->rows[$b->rowId]['net'])->toBe('90.00')
        ->and($result->rows[$b->rowId]['vat'])->toBe('9.00')
        ->and($result->totals)->toBe(['net' => '180.00', 'vat' => '28.80', 'gross' => '208.80', 'discount' => '23.20'])
        ->and($cart->vat())->toBe(32.0)->and($cart->subtotal())->toBe(200.0)->and($cart->total())->toBe(208.8);
})->with(['session', 'database']);

it('retains row coupon semantics and allocates sequential cart coupons', function (): void {
    $cart = resolveCart();
    $a = $cart->add(id: 1, name: 'A', qty: 2, price: 100.0, totalPrice: 122.0, vat: 22.0);
    $b = $cart->add(id: 1, name: 'B variant', qty: 1, price: 100.0, totalPrice: 110.0, vat: 10.0, options: ['size' => 'L']);
    $cart->addItemCoupon($a->rowId, 'ROW10', 'percentage', 10.0);
    $cart->addCoupon(new CartCoupon(hash: 'fixed', code: 'FIXED', type: 'fixed', value: 30.0));
    $cart->addCoupon(new CartCoupon(hash: 'pct', code: 'PCT', type: 'percentage', value: 10.0));
    $cart->addCoupon(new CartCoupon(hash: 'pct2', code: 'PCT2', type: 'percentage', value: 50.0));
    // Row gross 231.80 + 110.00 = 341.80; 10%, then 50%, then 30 = 123.81 payable.
    $result = $cart->payableTaxBreakdown();
    expect($result->totals['gross'])->toBe('123.81')->and($result->totals['discount'])->toBe('217.99')
        ->and($result->rows)->toHaveCount(2)->and($result->rows[$a->rowId]['grossBeforeDiscount'])->toBe('231.80')
        ->and($result->rows[$b->rowId]['grossBeforeDiscount'])->toBe('110.00')
        ->and($cart->total())->toBe(123.81)->and($cart->vat())->toBe(51.8);
    expect(json_decode(json_encode($result, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR))->toBe($result->toArray());
    $sum = '0';
    foreach ($result->rates as $amounts) {
        expect(bcadd($amounts['net'], $amounts['vat'], 2))->toBe($amounts['gross']);
        $sum = bcadd($sum, $amounts['gross'], 2);
    }
    expect($sum)->toBe('123.81');
});

it('assigns odd cents by row identity independently of insertion order including zero rates', function (): void {
    $cart = resolveCart();
    $ids = [];
    foreach ([3, 2, 1] as $id) {
        $ids[] = $cart->add(id: $id, name: 'Cent', qty: 1, price: 0.01)->rowId;
    }
    sort($ids, SORT_STRING);
    $cart->addCoupon(new CartCoupon(hash: 'cent', code: 'CENT', type: 'fixed', value: 0.01));
    $result = $cart->payableTaxBreakdown();
    expect($result->rows[$ids[0]]['discount'])->toBe('0.01')->and($result->rows[$ids[0]]['gross'])->toBe('0.00')
        ->and($result->rows[$ids[1]]['gross'])->toBe('0.01')->and($result->totals['vat'])->toBe('0.00')
        ->and($result->totals['net'])->toBe('0.02');
    $cart->removeCartCoupon('cent');
    $cart->addCoupon(new CartCoupon(hash: 'cap', code: 'CAP', type: 'fixed', value: 100.0));
    expect($cart->payableTaxBreakdown()->totals)->toBe(['net' => '0.00', 'vat' => '0.00', 'gross' => '0.00', 'discount' => '0.03']);
});

it('exposes the existing aggregate rounding residual rather than hiding it', function (): void {
    $cart = resolveCart();
    foreach ([1, 2, 3] as $id) {
        $cart->add(id: $id, name: 'Half cent', qty: 1, price: 0.005, vat: 0.0, totalPrice: 0.005);
    }
    $result = $cart->payableTaxBreakdown();
    $adjustments = array_column($result->rows, 'roundingAdjustment');
    expect($cart->total())->toBe(0.02)->and($result->totals['gross'])->toBe('0.02')
        ->and(array_values(array_filter($adjustments, fn (string $amount): bool => $amount !== '0.00')))->toBe(['-0.01']);
});

it('supports fractional quantity without converting a row coupon into a per unit discount', function (): void {
    $cart = resolveCart();
    $cart->add(['id' => 1, 'name' => 'Weight', 'qty' => 1.5, 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2]);
    $a = $cart->content()->sole();
    $cart->addItemCoupon($a->rowId, 'ONCE', 'fixed', 1.22);
    $result = $cart->payableTaxBreakdown();
    expect($result->totals)->toBe(['net' => '14.00', 'vat' => '3.08', 'gross' => '17.08', 'discount' => '0.00']);
});

it('supports empty zero and mixed zero rated carts', function (): void {
    $cart = resolveCart();
    expect($cart->payableTaxBreakdown()->rows)->toBe([])->and($cart->payableTaxBreakdown()->totals['gross'])->toBe('0.00');
    $zero = $cart->add(id: 1, name: 'Gift', qty: 1, price: 0.0);
    expect($cart->payableTaxBreakdown()->rows[$zero->rowId]['gross'])->toBe('0.00');
    $cart->add(id: 2, name: 'Zero rate', qty: 2, price: 10.0);
    $cart->add(id: 3, name: 'Taxed', qty: 1, price: 10.0, totalPrice: 12.20, vat: 2.2);
    $cart->addCoupon(new CartCoupon(hash: 'half', code: 'HALF', type: 'percentage', value: 50.0));
    $result = $cart->payableTaxBreakdown();
    expect($result->totals)->toBe(['net' => '15.00', 'vat' => '1.10', 'gross' => '16.10', 'discount' => '16.10'])
        ->and($result->rates['0.0000']['gross'])->toBe('10.00');
});

it('uses the configured precision and rounding mode', function (int $precision, int|RoundingMode $mode, string $net, string $vat): void {
    config(['cart.format.decimals' => $precision, 'cart.format.rounding_mode' => $mode]);
    $cart = resolveCart();
    $cart->add(id: 1, name: 'Rounding', qty: 1, price: 1.005, totalPrice: 1.1, vat: 0.095);
    $result = $cart->payableTaxBreakdown();
    expect($result->totals['net'])->toBe($net)->and($result->totals['vat'])->toBe($vat);
})->with([
    [2, PHP_ROUND_HALF_UP, '1.01', '0.09'], [2, PHP_ROUND_HALF_DOWN, '1.00', '0.10'],
    [2, PHP_ROUND_HALF_EVEN, '1.00', '0.10'], [2, PHP_ROUND_HALF_ODD, '1.01', '0.09'],
    [3, RoundingMode::TowardsZero, '1.005', '0.095'], [0, PHP_ROUND_HALF_UP, '1', '0'],
    [4, PHP_ROUND_HALF_UP, '1.0050', '0.0950'],
]);

it('does not persist timestamps content coupon eligibility or mutation events', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 2, price: 10.0);
    $cart->addCoupon(new CartCoupon(hash: 'expires', code: 'EXPIRES', type: 'fixed', value: 1.0, expiresAt: now()->addMinute()));
    $this->travel(2)->minutes();
    $before = serialize($cart->content());
    $session = serialize(session()->all());
    $events = [];
    app('events')->listen('*', function (string $event) use (&$events): void {
        if (! str_starts_with($event, 'OfflineAgency\\LaravelCart\\Events\\') && ! str_starts_with($event, 'cart.')) {
            return;
        }
        $events[] = 'mutation';
    });
    $writes = [];
    app('db')->connection('testing')->listen(function (QueryExecuted $query) use (&$writes): void {
        if (preg_match('/^\s*(update|insert|delete)/i', $query->sql)) {
            $writes[] = $query->sql;
        }
    });
    $result = $cart->payableTaxBreakdown();
    expect($result->totals['gross'])->toBe('19.00')->and(serialize($cart->content()))->toBe($before)
        ->and(serialize(session()->all()))->toBe($session)->and($writes)->toBe([])->and($events)->toBe([])
        ->and($cart->listCoupons())->toHaveCount(1);
})->with(['session', 'database']);

it('rejects unsupported allocation domains explicitly', function (string $case): void {
    $cart = resolveCart();
    $item = $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    if ($case === 'precision') {
        config(['cart.format.decimals' => 5]);
    } elseif ($case === 'negative precision') {
        config(['cart.format.decimals' => -1]);
    } else {
        switch ($case) {
            case 'quantity': $item->qty = 0;
                break;
            case 'adjustment': $item->name = 'discountCartItem';
                break;
            case 'inconsistent': $item->totalPrice = 11.0;
                break;
            default: $item->price = match ($case) {
                'negative' => -1.0, 'nonfinite' => INF, 'subscale' => 1.00001, default => 100000000.0
            };
        }
        // Session storage retains public item objects; this fixture models legacy malformed state.
        session()->put('cart.default', collect([$item->rowId => $item]));
    }
    expect(fn () => $cart->payableTaxBreakdown())->toThrow(TaxAllocationException::class);
})->with(['precision', 'negative precision', 'negative', 'nonfinite', 'subscale', 'range', 'quantity', 'adjustment', 'inconsistent']);

it('rejects fractional coupon rows whose legacy contribution becomes negative', function (): void {
    $cart = resolveCart();
    $cart->add(['id' => 1, 'name' => 'Fraction', 'qty' => 0.5, 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2]);
    $item = $cart->content()->sole();
    $cart->addItemCoupon($item->rowId, 'NINETY', 'percentage', 90.0);
    expect($cart->total())->toBe(0.0);
    expect(fn () => $cart->payableTaxBreakdown())->toThrow(TaxAllocationException::class, 'negative row contributions');
});

it('fails explicitly instead of returning an unreconciled legacy payable target', function (): void {
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $allocator = new TaxAllocator;
    expect(fn () => $allocator->allocate($cart->content(), 10.0, '1.00', 8.0))
        ->toThrow(TaxAllocationException::class, 'cannot be reconciled');
});
