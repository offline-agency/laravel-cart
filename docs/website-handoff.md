# Website source handoff — offline-agency/oa-doc-osp

Prepared 16 October 2026. This package task does not edit or deploy the VuePress site.
PR #50 merged at b89d321 and shipped as **4.1.0** on 16 October 2026. This follow-up
extends that release. Label its new allocation/recipes **unreleased**, never a guessed next version.
At publication, map them to the actual accepted package tag and CI-verified merge SHA.

Consume these package sources instead of implementing PHP behavior in VuePress:

| Navigation / destination under /laravel-cart/v4/ | Package source | Executable evidence |
| --- | --- | --- |
| Capabilities / availability | docs/v4/capabilities.md | Tag checks and linked tests; retain unreleased labels |
| Product identity | docs/v4/product-identity.md | tests/Regression/ProductIdentityTest.php; lookup fix unreleased, no row migration |
| Server coupon / checkout revalidation | docs/v4/recipes/coupons-checkout.md | examples/CheckoutValidation.php, tests/Feature/CheckoutValidationRecipeTest.php |
| Measurements | docs/v4/performance.md | benchmarks/cart-read.php and raw benchmarks/results/2026-10-16-php85-array-session.json; local read-path data only |
| Post-discount tax | docs/v4/post-discount-tax.md | examples/payable-tax.php, tests/Feature/PayableTaxBreakdownTest.php |
| Recipes / shared server actions | docs/v4/recipes/index.md | examples/CartActions.php, tests/Feature/AdoptionRecipesTest.php |
| Variants and wishlist | docs/v4/recipes/variants-wishlist.md | Same service; explicit quantity/price conflict policy |
| Livewire | docs/v4/recipes/livewire.md | Service tested, UI illustrative |
| Inertia + Vue | docs/v4/recipes/inertia-vue.md | Service/resource tested, UI/controller illustrative |
| API/mobile | docs/v4/recipes/api-mobile.md | tests/Feature/StatelessApiOwnershipTest.php exercises real RequestGuard routes without session; Sanctum token issuance not tested |
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
remain unimplemented; do not promote display precision to multi-currency support.

Run composer docs:check and examples:check in the package, consume its generated
snippets, then validate VuePress build, all internal links/anchors, version navigation
and historical routes in oa-doc-osp. Preserve old URLs/redirects while separating
released from unreleased docs. Record deployed commit/URLs only after an actual
website deployment. No website changes or discovery-benchmark rerun occurred here.

## Additional navigation and integration boundaries

Link the dated primary-source comparison at docs/v4/alternatives.md. Keep Redis
and Money design notes under docs/proposals/ separate from implemented API pages.
No docs/design or docs/implementation directory is required. Suggested destinations:
`/laravel-cart/v4/product-identity/`, `/laravel-cart/v4/recipes/coupons-checkout/`,
`/laravel-cart/v4/performance/` and clearly labelled `/laravel-cart/proposals/`.

Product lookup uses exact string conversion, including CartItem::is(); row hashing
is unchanged. Active Laravel authentication selects database ownership. Do not add
an X-Cart-Identifier example as if it were an authenticated package feature. Coupon
usageLimit remains metadata; checkout revalidation is an application policy example,
not inventory reservation, payment authorization or a redemption ledger.

For a later discovery rerun, preserve the original frozen twenty prompts and raw
records. Record search presence, page opened, comparison, package selected,
functional evidence and environment separately. No rerun or improved selection
rate is claimed by this handoff.
