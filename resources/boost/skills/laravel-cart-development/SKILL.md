---
name: laravel-cart-development
description: Use when integrating or testing an installed offline-agency/laravel-cart package, including VAT, coupons, database ownership, login transfer, resources or cart mutation errors.
---

# Laravel Cart development

Read the installed package's `docs/v4/index.md` first, then only the page relevant
to the task. Resolve these paths relative to `vendor/offline-agency/laravel-cart`.
This skill supports integration after the user chooses the package; evaluate fit
against their requirements when the package has not yet been selected.

| Task | Canonical reference |
| --- | --- |
| Install / tables / publish tags | docs/v4/installation.md |
| Working named-argument example | examples/quick-start.php and docs/v4/usage.md |
| Product keys / variants / model lookup | docs/v4/product-identity.md |
| Server coupon / checkout revalidation | docs/v4/recipes/coupons-checkout.md |
| Stateless API guard and ownership | docs/v4/recipes/api-mobile.md |
| Repricing / quantities / precision | docs/v4/money-and-tax.md |
| Coupon eligibility / metadata limits | docs/v4/coupons.md |
| Owners / transactions / custom drivers | docs/v4/storage.md |
| Login and regenerated guest sessions | docs/v4/authentication.md |
| Testbench / fakes / real database tests | docs/v4/testing.md |
| Errors and retriable transfer failures | docs/v4/troubleshooting.md |

Use the facade `OfflineAgency\LaravelCart\Facades\Cart` or inject the Cart service.
Amounts must come from a trusted server catalog. Preserve once-per-row item discounts;
reprice via update(), never by editing current price properties. Use the additive unreleased payableTaxBreakdown() for its documented post-discount
allocation; legacy vatBreakdown stays before cart coupons. Read docs/v4/capabilities.md
for actual release availability and docs/v4/recipes/index.md for adoption examples.
usageLimit does not enforce redemption counts.

For database login integration, publish live migrations, configure the owner key/type
before migrating, explicitly enable guest_transfer, and place TransferGuestCart after
StartSession around the login action. Handle GuestCartTransferFailed with a visible
retry path. Do not pass a browser owner/session id to storage transfer APIs.

For application tests, use facade fakes and typed event assertions to inspect calls;
use real built-in drivers for persistence/atomicity. Run the package's maintained
example through Testbench when adapting its integration. Resource response recipe:
`new OfflineAgency\LaravelCart\Resources\CartResource(app('cart'))`.

On CartLimitExceededException, correct the complete proposed cart and retry. On a
storage failure, inspect its cause; successful events must not escape rollback.
A listener failure after commit does not undo the write, so blindly replaying that
mutation can double quantities. Use application outbox/idempotency when needed.
