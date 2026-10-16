# Post-discount tax allocation (unreleased)

`Cart::payableTaxBreakdown()` is additive. It returns a `TaxBreakdown` value with
`precision`, `rows`, `rates` and `totals`; `toArray()` and JSON preserve decimal
strings without locale separators. Existing subtotal(), vat() and vatBreakdown()
retain their before-cart-coupon semantics. This is not fiscal certification.

## Contract

Use one currency/precision context per cart, scale 0–4. Stored unit amounts and
quantities must fit DECIMAL(12,4), with positive quantities and nonnegative amounts;
rates fit DECIMAL(8,4). Aggregate gross/payable must also remain within 99,999,999.9999
to bound the legacy public float boundary. Negative/legacy adjustment rows and inconsistent unit
net/VAT/gross amounts are unsupported here and raise TaxAllocationException. The
legacy payable API continues to support its existing adjustments.

Item coupons apply once per row first. The allocator consumes the same actual cart
coupon discount as total(): percentage coupons precede fixed coupons, insertion
order within a type is retained, remaining gross is capped at zero. Eligibility
remains the existing add/sync policy; this read does not expire/remove coupons or
silently revalidate their minimum. Call syncCoupons() explicitly when needed.

Gross is the allocation basis, including zero-rated rows. First reconcile exact
row gross contributions to the existing rounded cart gross using largest remainders.
Expose any difference from each independently rounded row as roundingAdjustment.
Then allocate the rounded cart discount proportionally to those reconciled gross
amounts in minor units, capped by each row. Largest fractional remainders receive
one minor unit; ties use ascending rowId, independent of insertion order. Zero
amounts receive no allocation. Oversized discounts reduce all rows to zero.

After allocation, retain each row's effective net/gross ratio (including existing
item-coupon rounding), round net with cart.format.rounding_mode, and assign the
remaining gross to VAT. Rate totals are sums of rows, never independently rerounded.
Thus gross = net + VAT, and gross = grossBeforeDiscount + roundingAdjustment - discount.
With no cart coupon, original row net is retained at configured precision, subject
to the disclosed gross rounding residual. Fractional quantities follow the existing
first-discounted-unit plus (qty - 1) base-unit rule; reject combinations producing
negative row contributions instead of returning an invented allocation.

BCMath computes finite schema-scale inputs exactly through the 8-place unit/quantity
products, integer quotient/remainder apportionment, and a 24-place net-ratio division
(20 guard places beyond the maximum output scale). Only the public float boundary
is converted once to schema-scale decimal strings. Existing rounded payable/discount
values are the reconciliation targets; no full-package exact Money claim is made.
The read does not persist content/coupons/timestamps or emit mutation events; normal
storage read/expiry behavior still applies. Concurrent checkout requires the
application's existing ownership/transaction boundary.

## Mixed-rate example

| Row | Before net / VAT / gross | Cart discount | After net / VAT / gross |
| --- | --- | --- | --- |
| A, 22% | 100.00 / 22.00 / 122.00 | 12.20 | 90.00 / 19.80 / 109.80 |
| B, 10% | 100.00 / 10.00 / 110.00 | 11.00 | 90.00 / 9.00 / 99.00 |

Fixed gross discount 23.20 yields payable 208.80 and VAT 28.80. See the maintained
[acceptance tests](../../tests/Feature/PayableTaxBreakdownTest.php).

The following canonical source is executed on both built-in drivers. Import CartCoupon
and inject Cart as in [payable-tax.php](../../examples/payable-tax.php).

<!-- example:payable-tax:start -->

```php
$cart->add(id: 1, name: 'A', qty: 1, price: 100.00, totalPrice: 122.00, vat: 22.00);
$cart->add(id: 2, name: 'B', qty: 1, price: 100.00, totalPrice: 110.00, vat: 10.00);
$cart->addCoupon(new CartCoupon(hash: 'gross', code: 'GROSS', type: 'fixed', value: 23.20));
$allocation = $cart->payableTaxBreakdown();
// totals: net '180.00', vat '28.80', gross '208.80', discount '23.20'.
```

<!-- example:payable-tax:end -->
