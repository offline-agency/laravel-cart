# Laravel Cart

[![Packagist](https://img.shields.io/packagist/v/offline-agency/laravel-cart)](https://packagist.org/packages/offline-agency/laravel-cart)
[![CI](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml/badge.svg)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml)
[![MIT](https://img.shields.io/packagist/l/offline-agency/laravel-cart)](LICENSE)

A Laravel shopping cart with VAT, row/cart coupons, session or database persistence,
API resources and testing helpers. Useful when your application owns its catalog and
checkout. For inventory, payments, shipping and order management, evaluate a full commerce framework.

Requires **PHP 8.4/8.5**, **BCMath** and **Laravel components ^12.69.2 or ^13.32**.
Latest published tag verified 16 October 2026: **4.0.3**. The examples and docs on this
development branch include **unreleased** PR #50/follow-up features; see the
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
