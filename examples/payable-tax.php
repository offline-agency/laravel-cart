<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Examples;

use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\TaxBreakdown;

final class PayableTaxExample
{
    public static function run(Cart $cart): TaxBreakdown
    {
        // docs:start
        $cart->add(id: 1, name: 'A', qty: 1, price: 100.00, totalPrice: 122.00, vat: 22.00);
        $cart->add(id: 2, name: 'B', qty: 1, price: 100.00, totalPrice: 110.00, vat: 10.00);
        $cart->addCoupon(new CartCoupon(hash: 'gross', code: 'GROSS', type: 'fixed', value: 23.20));
        $allocation = $cart->payableTaxBreakdown();
        // totals: net '180.00', vat '28.80', gross '208.80', discount '23.20'.
        // docs:end

        return $allocation;
    }
}
