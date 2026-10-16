# Website source handoff — offline-agency/oa-doc-osp

Prepared 16 October 2026. This package task does not edit or deploy the VuePress site.
PR #50 remains open at 131f2e6; this local follow-up depends on it. Latest actual tag
is 4.0.3. Label all #50/follow-up features **unreleased**, never a guessed next version.
At publication, map them to the actual accepted package tag and CI-verified merge SHA.

Consume these package sources instead of implementing PHP behavior in VuePress:

| Navigation / destination under /laravel-cart/v4/ | Package source | Executable evidence |
| --- | --- | --- |
| Capabilities / availability | docs/v4/capabilities.md | Tag checks and linked tests; retain unreleased labels |
| Post-discount tax | docs/v4/post-discount-tax.md | examples/payable-tax.php, tests/Feature/PayableTaxBreakdownTest.php |
| Recipes / shared server actions | docs/v4/recipes/index.md | examples/CartActions.php, tests/Feature/AdoptionRecipesTest.php |
| Variants and wishlist | docs/v4/recipes/variants-wishlist.md | Same service; explicit quantity/price conflict policy |
| Livewire | docs/v4/recipes/livewire.md | Service tested, UI illustrative |
| Inertia + Vue | docs/v4/recipes/inertia-vue.md | Service/resource tested, UI/controller illustrative |
| API/mobile | docs/v4/recipes/api-mobile.md | Authenticated database service isolation; token middleware illustrative |
| Redis sessions | docs/v4/recipes/redis-sessions.md | examples/RedisSessionExample.php; real session/lock probe, HTTP layer illustrative |
| Storage / login transfer | docs/v4/storage.md, docs/v4/authentication.md | PR #50 rollback/concurrency/login suites |
| Upgrade / release history | UPGRADE.md, CHANGELOG.md | Real tag/release dates; no fabricated patch entries |

Exact additive PHP API: Cart::payableTaxBreakdown(): TaxBreakdown, with precision,
rows, rates, totals, toArray() and JsonSerializable support. Amounts are decimal
strings. CartResource stays compatible; allocation is an explicit extra response field.
Legacy vatBreakdown/subtotal/vat are before cart coupons. Publish the gross-basis,
once-per-row coupon, rowId residual and TaxAllocationException rules together.

Link README and v4 navigation prominently to recipes and capabilities. Replace old
fiscal-receipt headings with before-cart-coupon wording. Label RedisCartStorage sample
as an illustrative custom class, not a built-in driver. Exact Money and dedicated Redis
remain design proposals; do not promote display precision to multi-currency support.

Run composer docs:check and examples:check in the package, consume its generated
snippets, then validate VuePress build, all internal links/anchors, version navigation
and historical routes in oa-doc-osp. Preserve old URLs/redirects while separating
released from unreleased docs. Record deployed commit/URLs only after an actual
website deployment. No website changes or discovery-benchmark rerun occurred here.
