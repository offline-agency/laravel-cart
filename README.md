# Laravel Cart

[![Latest stable release](https://img.shields.io/packagist/v/offline-agency/laravel-cart?label=stable)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Total downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-cart)](https://packagist.org/packages/offline-agency/laravel-cart/stats)
[![CI (main)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml/badge.svg?branch=main&event=push)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml?query=branch%3Amain)
[![Code coverage (main)](https://codecov.io/gh/offline-agency/laravel-cart/branch/main/graph/badge.svg)](https://app.codecov.io/gh/offline-agency/laravel-cart/tree/main)

[![PHP 8.4 and 8.5](https://img.shields.io/badge/PHP-8.4%20%7C%208.5-777BB4?logo=php&logoColor=white)](composer.json)
[![Laravel 12 and 13](https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-2563EB)](LARASTAN.md)
[![MIT license](https://img.shields.io/packagist/l/offline-agency/laravel-cart)](LICENSE)

A Laravel shopping cart with VAT, row/cart coupons, session or database persistence,
API resources and testing helpers. Useful when your application owns its catalog and
checkout. For inventory, payments, shipping and order management, evaluate a full commerce framework.

Requires **PHP 8.4/8.5**, **BCMath** and **Laravel components ^12.69.2 or ^13.32**.
Latest published tag verified 16 October 2026: **4.1.0**. This development branch
adds **unreleased** tax allocation and recipe examples; see the
[capability/availability map](docs/v4/capabilities.md). No next version is assigned.

Composer enforces the exact requirements; PHP prereleases and Laravel 10/11 are excluded.

```sh
composer require offline-agency/laravel-cart
```

```php
use OfflineAgency\LaravelCart\Facades\Cart;

// Prices come from your trusted catalog, never from browser payable amounts.
$item = Cart::add(id: 1, name: 'Widget', qty: 2, price: 10.00, totalPrice: 12.20, vat: 2.20);
Cart::update($item->rowId, ['price' => 20.00]);
Cart::total(); // 48.80
```

See the [maintained v4 docs](docs/v4/index.md), [executable quick start](examples/quick-start.php),
[full reference](docs/v4/reference.md), [upgrade guide](UPGRADE.md) and [contributing](CONTRIBUTING.md).
The external documentation site is pending alignment with these sources.

Find practical [variants/wishlist, Livewire, Inertia, API and Redis-session recipes](docs/v4/recipes/index.md).

Item coupons apply once per row. The additive, unreleased
[payableTaxBreakdown()](docs/v4/post-discount-tax.md) allocates cart discounts into VAT;
legacy vatBreakdown() stays before cart coupons. Built-in live mutations stage changes and database operations lock the complete
read/modify/write boundary; [storage guarantees and custom-driver limits](docs/v4/storage.md)
explain their scope. [Guest transfer](docs/v4/authentication.md) is explicitly enabled;
legacy saved snapshots remain a separate mechanism.

## Capabilities and proof

| Requirement | Availability | Executed source / boundary |
| --- | --- | --- |
| VAT, coupons and session/database persistence | 4.1.0 | [Quick start](examples/quick-start.php), [mutation regressions](tests/Regression/MutationCorrectnessTest.php) |
| Trusted login transfer and database concurrency | 4.1.0, transfer opt-in | [Login](tests/Feature/GuestTransferTest.php), [independent processes](tests/Regression/ServerConcurrencyTest.php) |
| String product keys, variants and wishlist | Lookup fix/recipe unreleased | [Identity tests](tests/Regression/ProductIdentityTest.php), [recipe](docs/v4/recipes/variants-wishlist.md) |
| Post-cart-discount VAT allocation | Unreleased, additive | [Executed allocation](examples/payable-tax.php), [mixed-rate tests](tests/Feature/PayableTaxBreakdownTest.php) |
| Livewire, Inertia and authenticated API integration | Recipes unreleased | [Shared server service](examples/CartActions.php), [stateless ownership test](tests/Feature/StatelessApiOwnershipTest.php); UI fragments illustrative |
| Customer coupon and checkout revalidation | Recipe unreleased | [Executed server policy](examples/CheckoutValidation.php), [recipe](docs/v4/recipes/coupons-checkout.md); no redemption ledger |

[Redis sessions](docs/v4/recipes/redis-sessions.md) use Laravel session storage;
a dedicated Redis cart driver and an exact currency-aware Money API remain
[proposals](docs/proposals/redis-cart-storage.md). See the
[measured read-path protocol](docs/v4/performance.md) for a reproducible local harness,
not a comparison with other packages.

## Reference links

<a id="installation"></a>
[Installation and publish tags](docs/v4/installation.md)

<a id="configuration"></a>
[Configuration](docs/v4/configuration.md)

<a id="usage"></a>
[Usage](docs/v4/usage.md) · [Coupons](docs/v4/coupons.md) · [Storage](docs/v4/storage.md)

## Credits and license

Maintained by Offline Agency. Original package credits and contributors are preserved
in the [expanded reference](docs/v4/reference.md). Released under the [MIT license](LICENSE).
