# Capability and availability map

Verified 24 November 2026 against tag **4.2.0** (merged PR #51 at `e24c589`);
PHP 8.6 support is published as **4.3.0** when its pull request is merged. **Unreleased means not available from a
stable Composer release yet.** Earlier API presence is not a claim of current
PHP/Laravel support on an old package line.

| Use case | Availability | Maintained example/reference | Executed evidence / limit |
| --- | --- | --- | --- |
| Option variants, named wishlist, associated models (S06) | Core APIs verified in 3.1.5; recipe in 4.2.0 | [Variants/wishlist](recipes/variants-wishlist.md), [CartActions](../../examples/CartActions.php) | [Adoption service](../../tests/Feature/AdoptionRecipesTest.php), [model association](../../tests/CartItemTest.php); move is not cross-instance atomic |
| Session/database carts and CartResource | 4.0.0 (verified tag) | [Storage](storage.md), [API recipe](recipes/api-mobile.md) | Resource/owner tests; default active Laravel auth identity |
| Coherent repricing and atomic logical mutations | 4.1.0 | [Quick start](../../examples/quick-start.php), [money](money-and-tax.md) | [Mutation regressions](../../tests/Regression/MutationCorrectnessTest.php); custom-driver limits remain |
| Trusted login transfer (S09) | 4.1.0, opt-in | [Authentication](authentication.md) | [Real login/session test](../../tests/Feature/GuestTransferTest.php); web lifecycle required |
| Read-modify-write database concurrency (S10) | 4.1.0 | [Concurrency boundaries](storage.md) | [Independent server processes](../../tests/Regression/ServerConcurrencyTest.php); excludes direct raw writes |
| Pre-cart-coupon VAT grouping | 4.0.0 | [Legacy reference](reference.md#vat-breakdown-before-cart-coupons) | Existing methods unchanged; not final payable tax |
| Post-discount row/rate allocation (S11) | 4.2.0 | [payableTaxBreakdown](post-discount-tax.md) | [Mixed-rate/rounding/reload/read-only tests](../../tests/Feature/PayableTaxBreakdownTest.php); no negative adjustments or fiscal certification |
| Livewire/Inertia adoption (S13) | Recipes in 4.2.0, no frontend runtime dependency | [Livewire](recipes/livewire.md), [Inertia/Vue](recipes/inertia-vue.md) | Real shared service tested; UI/controller fragments illustrative |
| Redis-backed sessions (S15) | SessionStorage shipped in 4.0.0; recipe in 4.2.0 | [Redis sessions](recipes/redis-sessions.md) | Real Redis session/lock probe; HTTP route blocking illustrative |
| PHP 8.6 support | 4.3.0 | [Installation](installation.md), [compatibility](reference.md#compatibility) | CI: PHP 8.6 / Laravel 13; Laravel 12 is not tested on PHP 8.6 |
| Dedicated Redis driver / independent cart TTL (S15) | **Not implemented** | No shipped dedicated driver | Future real Redis/CAS/cluster tests required |
| Exact EUR/JPY/KWD Money model (S16) | **Not implemented** | No shipped exact Money API | Public float API is not exact multi-currency support |
| Boost guidelines/skill | 4.1.0; allocation/recipe guidance updated in 4.2.0 | [Agent integration](agents.md) | Archived consumer discovery/rendering smoke test |

The supplied discovery exercise used twenty package-neutral research sessions;
its observations prioritize these improvements. It did not test competing packages
or establish universal selection probabilities. No discovery benchmark was rerun.
