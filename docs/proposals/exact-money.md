# Proposal: versioned exact Money model

Status: design only. Display precision and selected BCMath operations do not make the
current public float API exact or multi-currency. The new payable allocation confines
its decimal computation to validated schema-scale inputs; it is not this redesign.

## Proposed public API

```php
// Proposed, not currently callable:
Money::fromDecimal('1.23', 'EUR');  // minor units '123', exponent 2
Money::fromDecimal('123', 'JPY');   // minor units '123', exponent 0
Money::fromDecimal('1.234', 'KWD');  // minor units '1234', exponent 3
$moneyCart->add(product: $product, quantity: '1.5000', unitNet: $money);
```

Use one currency per cart, reject implicit currency mixing and unsupported exponents.
No FX conversion is implied. Validate ISO currency/exponent data through an explicitly
versioned source/library, including historical changes. Prefer decimal-string inputs
and string minor units in JSON so JavaScript does not lose large integers. Quantity
and percentage remain exact dimensionless decimals, not currency minor units.

Compare bounded native integers (fast but overflow-prone), BCMath strings (already
required, explicit scale policy) and a maintained decimal/Money library (strong types
and currency metadata, additional dependency). Choose through real limits/benchmarks,
not by silently casting floats or claiming arbitrary precision without bounds.

## Rounding, representation and migration

Define line vs aggregate rounding, tie mode, coupon ordering, taxable gross allocation,
minor-unit residuals and cash rounding separately. Preserve exact intermediates and
round only at specified boundaries. Test EUR/JPY/KWD examples and fractional quantities
with independently specified values and conservation invariants.

A versioned database representation could store currency, exponent, canonical decimal
quantity and bounded integer-string minor amounts (or a sufficiently wide fixed
DECIMAL with explicit scale). Prefer versioned JSON fields with string amounts;
never expose a minor integer through a lossy JS Number. Define overflow and schema
limits before migrations, and retain the old snapshot decoder during the transition.

Add an opt-in MoneyCart/adapter first; replacing float signatures or changing existing
stored amount interpretation is **major**. Shadow comparison can surface rounding
differences before cutover. Existing floats cannot be converted losslessly into their
unknown original decimal inputs: revalidate authoritative catalog amounts and document
rounding when migrating. Do not infer currency from display formatting.

## Acceptance and scope

Test decimal/minor round trips, negative adjustments, all rounding ties, zero/oversized
coupons, mixed rates, no-coupon parity, JSON/database snapshots, parallel updates and
legacy adapters on both server databases. Reject mixed currencies and overflow; prove
all row/rate/cart invariants using exact strings and final independent fixtures.

Estimated scope: **large**, multiple PRs for value/codec, opt-in engine/adapters and
migration. A default-engine replacement requires its own major release decision and
upgrade examples. Payment, inventory and tax-law certification remain outside scope.
