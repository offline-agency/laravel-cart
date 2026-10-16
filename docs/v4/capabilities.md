# Capability and availability map

Verified 16 October 2026: latest published tag **4.0.3**. PR #50 is open at
`131f2e6`; this follow-up depends on it. **Unreleased means not available from a
stable Composer release yet.** No guessed next version is assigned. Earlier API
presence is not a claim of current PHP/Laravel support on an old package line.

| Use case | Availability | Maintained example/reference | Executed evidence / limit |
| --- | --- | --- | --- |
| Option variants, named wishlist, associated models (S06) | Core APIs verified in 3.1.5; current recipe unreleased | [Variants/wishlist](recipes/variants-wishlist.md), [CartActions](../../examples/CartActions.php) | [Adoption service](../../tests/Feature/AdoptionRecipesTest.php), [model association](../../tests/CartItemTest.php); move is not cross-instance atomic |
| Session/database carts and CartResource | 4.0.0 (verified tag) | [Storage](storage.md), [API recipe](recipes/api-mobile.md) | Resource/owner tests; default active Laravel auth identity |
| Coherent repricing and atomic logical mutations | Unreleased, PR #50 | [Quick start](../../examples/quick-start.php), [money](money-and-tax.md) | [Mutation regressions](../../tests/Regression/MutationCorrectnessTest.php); custom-driver limits remain |
| Trusted login transfer (S09) | Unreleased, PR #50, opt-in | [Authentication](authentication.md) | [Real login/session test](../../tests/Feature/GuestTransferTest.php); web lifecycle required |
| Read-modify-write database concurrency (S10) | Unreleased, PR #50 | [Concurrency boundaries](storage.md) | [Independent server processes](../../tests/Regression/ServerConcurrencyTest.php); excludes direct raw writes |
| Pre-cart-coupon VAT grouping | 4.0.0 | [Legacy reference](reference.md#vat-breakdown-before-cart-coupons) | Existing methods unchanged; not final payable tax |
| Post-discount row/rate allocation (S11) | Unreleased, this follow-up | [payableTaxBreakdown](post-discount-tax.md) | [Mixed-rate/rounding/reload/read-only tests](../../tests/Feature/PayableTaxBreakdownTest.php); no negative adjustments or fiscal certification |
| Livewire/Inertia adoption (S13) | Recipes unreleased, no frontend runtime dependency | [Livewire](recipes/livewire.md), [Inertia/Vue](recipes/inertia-vue.md) | Real shared service tested; UI/controller fragments illustrative |
| Redis-backed sessions (S15) | SessionStorage shipped in 4.0.0; recipe unreleased | [Redis sessions](recipes/redis-sessions.md) | Real Redis session/lock probe; HTTP route blocking illustrative |
| Dedicated Redis driver / independent cart TTL (S15) | **Not implemented** | [Design proposal](../design/redis-cart-storage.md) | Future real Redis/CAS/cluster tests required |
| Exact EUR/JPY/KWD Money model (S16) | **Not implemented** | [Design proposal](../design/exact-money.md) | Public float API is not exact multi-currency support |
| Boost guidelines/skill | Unreleased, PR #50; updated here | [Agent integration](agents.md) | Archived consumer discovery/rendering smoke test |

The supplied discovery exercise used twenty package-neutral research sessions;
its observations prioritize these improvements. It did not test competing packages
or establish universal selection probabilities. No discovery benchmark was rerun.
