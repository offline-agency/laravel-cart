<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Examples;

use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;

final class QuickStart
{
    /** @return array{rowId: string, payable: float} */
    public static function run(Cart $cart): array
    {
        // docs:start
        $item = $cart->add(id: 1, name: 'Widget', qty: 2, price: 10.00, totalPrice: 12.20, vat: 2.20);
        $cart->addItemCoupon($item->rowId, 'ROW10', 'percentage', 10.0);
        $cart->update($item->rowId, ['price' => 20.00]);
        // Two units at 24.40, with 2.44 discounted once per row: 46.36.
        $cart->addCoupon(new CartCoupon(hash: 'welcome', code: 'WELCOME', type: 'fixed', value: 1.00));
        $payable = $cart->total(); // 45.36; item tax breakdown is before this cart coupon.
        // docs:end

        return ['rowId' => $item->rowId, 'payable' => $payable];
    }
}
