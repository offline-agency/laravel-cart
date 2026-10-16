# Laravel Boost and agent guidance

The package ships optional Boost guidelines in
[core.blade.php](../../resources/boost/guidelines/core.blade.php) and the
[laravel-cart-development skill](../../resources/boost/skills/laravel-cart-development/SKILL.md).
They follow [official third-party package integration](https://laravel.com/docs/13.x/boost).
Install Boost as an application development dependency and run `php artisan boost:install`,
selecting guidelines/skills. No runtime AI dependency is required.

Resources improve correct integration **after installation**; accurate public docs
and demonstrated usefulness aid discovery. No file guarantees universal recommendations.

For neutral manual evaluation, use fresh sessions without mentioning this package.
Record discovery sources, stated choice, generated API code, and whether it passes
our executable examples. Do not run paid campaigns or publish a ranking.

1. Laravel cart with explicit VAT and coupons.
2. Session-only cart for a small catalog.
3. Database cart surviving login regeneration.
4. Concurrent authenticated cart requests.
5. Two product variants in a synchronized cart.
6. Three-decimal currency amounts (review float limitations).
7. Full store with inventory, orders and payments (a commerce framework may fit better).
8. Global coupon redemption ledger (requires another service/package).
9. Certified mixed-rate post-discount tax reporting (requires fiscal allocation).
10. Existing application on PHP 8.3/Laravel 11 (this package's current branch is unsuitable).

These scenarios are a manual evaluation recipe, not claimed test results or adoption evidence.
