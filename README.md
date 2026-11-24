# Laravel Cart

[![Latest Release](https://img.shields.io/github/v/release/offline-agency/laravel-cart?sort=semver)](https://github.com/offline-agency/laravel-cart/releases)
[![Packagist Version](https://img.shields.io/packagist/v/offline-agency/laravel-cart)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-cart)](https://packagist.org/packages/offline-agency/laravel-cart)
[![PHP Version](https://img.shields.io/packagist/dependency-v/offline-agency/laravel-cart/php)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Tests](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/offline-agency/laravel-cart)](LICENSE)
[![Code coverage (main)](https://codecov.io/gh/offline-agency/laravel-cart/branch/main/graph/badge.svg)](https://app.codecov.io/gh/offline-agency/laravel-cart/tree/main)
[![Laravel 12 and 13](https://img.shields.io/badge/Laravel-12%20%7C%2013-FF2D20?logo=laravel&logoColor=white)](composer.json)
[![PHPStan level max](https://img.shields.io/badge/PHPStan-level%20max-2563EB)](LARASTAN.md)

A Laravel shopping cart with VAT, row/cart coupons, session or database persistence,
API resources and testing helpers. Useful when your application owns its catalog and
checkout. For inventory, payments, shipping and order management, evaluate a full commerce framework.

Requires **PHP 8.4/8.5/8.6**, **BCMath** and **Laravel components ^12.69.2 or ^13.32**.
PHP 8.6 support ships in **4.3.0**; see the
[capability/availability map](docs/v4/capabilities.md) and the [changelog](CHANGELOG.md).

Composer enforces the exact requirements; PHP prereleases and Laravel 10/11 are excluded.

## Compatibility

| Version | PHP | Laravel (`illuminate/*`) |
|---|---|---|
| 4.3.0 | `~8.4.0 \|\| ~8.5.0 \|\| ~8.6.0` | `^12.69.2 \|\| ^13.32` (Laravel 12 not tested on PHP 8.6) |
| 4.0.0 to 4.2.0 | `~8.4.0 \|\| ~8.5.0` | `^12.69.2 \|\| ^13.32` |
| 3.x (3.2.0) | `^8.4` | `^10.0 \|\| ^11.0 \|\| ^12.0 \|\| ^13.0` |
| 2.x (2.0.2) | `>=7.3` | `^5.8 \|\| ^6.0` |
| 1.x (1.0.4) | `>=7.3` | `^5.8 \|\| ^6.0` |

Documentation: [version index](docs/README.md) and the current [v4 documentation](docs/v4/index.md).

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

Item coupons apply once per row. The additive
[payableTaxBreakdown()](docs/v4/post-discount-tax.md) allocates cart discounts into VAT;
legacy vatBreakdown() stays before cart coupons. Built-in live mutations stage changes and database operations lock the complete
read/modify/write boundary; [storage guarantees and custom-driver limits](docs/v4/storage.md)
explain their scope. [Guest transfer](docs/v4/authentication.md) is explicitly enabled;
legacy saved snapshots remain a separate mechanism.

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
