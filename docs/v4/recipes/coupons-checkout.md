# Server coupon validation and checkout revalidation

Unreleased executable recipe: [CheckoutValidation.php](../../../examples/CheckoutValidation.php).
Copy it into your application support namespace and inject Cart plus two server-side
resolvers. The catalog resolver receives a stored CartItem and returns current
`net`, `vat`, `gross` and integer `available` stock for its authorized product/variant.
The coupon resolver receives a code and the actual authenticated owner ID, selects
the current campaign definition and checks customer eligibility. It must throw a
ValidationException for unknown, inactive, unauthorized or exhausted campaigns.
Never build a CartCoupon from browser discount values, usage limits or owner IDs.

This example's code policy is uppercase A–Z/0–9/underscore/hyphen, length 1–40;
adapt it to your catalog. The service obtains its owner from auth()->id(), not a
caller-supplied argument. An authenticated endpoint should validate the selector,
call applyCoupon($request->string('code')->toString()), and convert CartException
errors into the application's 422 validation response as in the API recipe.

<!-- example:checkout-coupon:start -->

```php
// This resolver checks current campaign/customer eligibility using server data.
$coupon = ($this->eligibleCoupon)($code, $owner);
$this->cart->addCoupon($coupon); // Package checks definition, expiry and gross minimum.
```

<!-- example:checkout-coupon:end -->

## Revalidate before checkout

<!-- example:checkout-validate:start -->

```php
// Reject stale state without partially repricing the cart or reserving stock.
foreach ($this->cart->content() as $item) {
    if (! is_finite((float) $item->qty) || $item->qty <= 0) {
        throw ValidationException::withMessages(['quantity' => 'Checkout requires positive finite product quantities.']);
    }
    $current = ($this->catalog)($item);
    if ($item->qty > $current['available']) {
        throw ValidationException::withMessages(['quantity' => 'Availability changed. Refresh the cart.']);
    }
    foreach ([[$item->originalPrice, $current['net']], [$item->originalVat, $current['vat']], [$item->originalTotalPrice, $current['gross']]] as [$saved, $trusted]) {
        if (number_format($saved, 4, '.', '') !== number_format($trusted, 4, '.', '')) {
            throw ValidationException::withMessages(['cart' => 'Catalog prices changed. Refresh the cart.']);
        }
    }
}
$gross = (float) $this->cart->numberFormat($this->cart->total() + $this->cart->discount(), null, '.', '');
foreach ($this->cart->listCoupons() as $stored) {
    $current = ($this->eligibleCoupon)($stored->code, $owner);
    if ($current->isExpired() || ! $current->isApplicableTo($gross) || $current->toArray() !== $stored->toArray()) {
        throw ValidationException::withMessages(['coupon' => 'Coupon eligibility changed. Remove and reapply it.']);
    }
}
```

<!-- example:checkout-validate:end -->

The validation is read-only: it rejects changed catalog prices/stock and stale
coupon eligibility/definitions instead of partially repricing the cart. Refresh
trusted prices through explicit update(), and remove/reapply a changed coupon.
The gross minimum follows the existing rounded payable-plus-discount basis.
It does not silently remove expired coupons; Cart::syncCoupons() is the separate
explicit cleanup operation.

This is a current-state check, **not a redemption ledger or reservation**. usageLimit
is metadata. Counting prior redemptions in a resolver alone cannot prevent two
concurrent checkouts from consuming the last use. In the application's order flow,
authorize ownership again, recheck catalog/campaign state under its transaction,
reserve stock and coupon usage through their authoritative systems, calculate trusted
payable amounts, and authorize/capture payment there. Do not mark orders paid from
browser totals. Those inventory/order/payment services are not supplied by this cart.

[CheckoutValidationRecipeTest.php](../../../tests/Feature/CheckoutValidationRecipeTest.php)
executes the source on both drivers and verifies actual owner selection, rejection
of stale stock/prices, expiry/minimum/customer/definition checks and unchanged state.
The package does not claim those tests exercise a production redemption/payment system.

This product checkout policy rejects nonpositive/nonfinite quantities. Legacy
adjustment rows remain supported by the cart where documented; they need a
separate application checkout policy. Positive fractional quantities are accepted
when your catalog availability policy permits them.
