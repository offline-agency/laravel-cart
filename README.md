# Laravel Cart

[![Latest Version on Packagist](https://img.shields.io/packagist/v/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![PHP Version](https://img.shields.io/packagist/php-v/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Laravel Version](https://img.shields.io/badge/laravel-10%20%7C%2011%20%7C%2012-blue?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Tests](https://img.shields.io/github/actions/workflow/status/offline-agency/laravel-cart/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/offline-agency/laravel-cart/actions)
[![License](https://img.shields.io/packagist/l/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)

---

## Overview

`offline-agency/laravel-cart` is a feature-rich Laravel shopping cart package with first-class fiscal and VAT support. It was forked from `Crinsane/LaravelShoppingcart` and re-namespaced under `OfflineAgency\LaravelCart`. The package provides session and database storage drivers, cart-level and item-level coupons, tax breakdowns, Artisan commands, API resources, and a testing fake.

---

## Compatibility Matrix

| PHP / Laravel | 10.x | 11.x | 12.x |
|:---:|:---:|:---:|:---:|
| 8.2 | Yes | Yes | Yes |
| 8.3 | Yes | Yes | Yes |
| 8.4 | Yes | Yes | Yes |

---

## Installation

Install via Composer:

```bash
composer require offline-agency/laravel-cart
```

The service provider and `Cart` facade are auto-discovered. Then publish the assets you need:

```bash
# Configuration
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=config

# Translations
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang

# Database migrations (required for the database storage driver)
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations
php artisan migrate
```

---

## Quick Start

```php
use OfflineAgency\LaravelCart\Facades\Cart;

Cart::add(1, 'Product A', null, 2, 9.99, 12.19, 2.20);
Cart::add(2, 'Product B', null, 1, 19.99, 24.39, 4.40);
$total = Cart::total();   // 48.77
$vat   = Cart::vat();     // 8.80
Cart::destroy();
```

---

## Configuration Reference

After publishing, the configuration file is located at `config/cart.php`. Below is a summary of all available keys:

| Key | Type | Default | Description |
|---|---|---|---|
| `storage.driver` | `string` | `"session"` | Storage driver. Supported: `"session"`, `"database"`. |
| `database.connection` | `string\|null` | `null` | Database connection name (`null` uses the default connection). |
| `database.table` | `string` | `"cart"` | Table name for the legacy `store()`/`restore()` persistence. |
| `database.carts_table` | `string` | `"carts"` | Table name for the normalized carts table. |
| `database.cart_items_table` | `string` | `"cart_items"` | Table name for the normalized cart items table. |
| `users.foreign_key` | `string` | `"user_id"` | Foreign key column referencing the users table. |
| `users.foreign_key_type` | `string` | `"unsignedBigInteger"` | Column type for the user foreign key. |
| `users.table` | `string` | `"users"` | Users table name. |
| `destroy_on_logout` | `bool` | `false` | Destroy all cart instances when the user logs out. |
| `ttl` | `int\|null` | `null` | Minutes before a stored cart expires (`null` = never). Database driver only. |
| `middleware.merge_on_login` | `bool` | `false` | Merge guest cart with stored user cart on login. |
| `middleware.auto_restore` | `bool` | `false` | Auto-restore a stored cart when an authenticated user has an empty cart. |
| `format.decimals` | `int` | `2` | Number of decimal places for formatted numbers. |
| `format.decimal_point` | `string` | `"."` | Decimal separator character. |
| `format.thousand_separator` | `string` | `","` | Thousands separator character. |
| `format.rounding_mode` | `int` | `PHP_ROUND_HALF_UP` | PHP rounding mode constant. |
| `limits.max_items` | `int\|null` | `null` | Maximum number of unique items in the cart. |
| `limits.max_qty_per_item` | `int\|null` | `null` | Maximum quantity per item. |
| `limits.max_total` | `float\|null` | `null` | Maximum cart total. |
| `limits.min_total` | `float\|null` | `null` | Minimum cart total (checked via `meetsMinimum()`). |
| `tax.default_rate` | `float` | `0.0` | Default tax rate applied when no explicit VAT is provided. |
| `tax.tax_behavior` | `string` | `"inclusive"` | `"inclusive"` (EU: price includes tax) or `"exclusive"` (US: tax added on top). |
| `tax.fiscal_codes.enabled` | `bool` | `false` | Enable fiscal code tracking on items. |

---

## Tax Configuration

### EU example -- inclusive VAT at 22%

```php
// config/cart.php
'tax' => [
    'default_rate'  => 22.0,
    'tax_behavior'  => 'inclusive',
],
```

### US example -- exclusive sales tax at 8.875%

```php
'tax' => [
    'default_rate'  => 8.875,
    'tax_behavior'  => 'exclusive',
],
```

### Tax-free

```php
'tax' => [
    'default_rate'  => 0.0,
    'tax_behavior'  => 'inclusive',
],
```

### Custom tax name (e.g. GST)

Override the label in your published translation file (`lang/vendor/laravel-cart/en/cart.php`):

```php
'tax_name' => 'GST',
```

### FiscalBuyable

For items that carry fiscal codes (VAT FC code and product FC code), implement `FiscalBuyable` instead of `Buyable`:

```php
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;

class Product extends Model implements FiscalBuyable
{
    use \OfflineAgency\LaravelCart\CanBeBought;

    public function getVatFcCode(): string { return $this->vat_fc_code; }
    public function getProductFcCode(): string { return $this->product_fc_code; }
}
```

---

## Storage Drivers

### Session (default)

No additional setup required. Cart data is stored in the Laravel session.

### Database

1. Publish and run the migrations:

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=cart-database-migrations
php artisan migrate
```

2. Set the driver in your `.env`:

```dotenv
CART_STORAGE_DRIVER=database
```

### Switching drivers at runtime

The driver is resolved from `config('cart.storage.driver')`. You can change it per-environment via the `CART_STORAGE_DRIVER` env variable.

### Custom drivers

Implement the `OfflineAgency\LaravelCart\Contracts\CartStorage` interface:

```php
use OfflineAgency\LaravelCart\Contracts\CartStorage;

class RedisCartStorage implements CartStorage
{
    public function get(string $key, mixed $default = null): mixed { /* ... */ }
    public function put(string $key, mixed $value): void { /* ... */ }
    public function has(string $key): bool { /* ... */ }
    public function remove(string $key): void { /* ... */ }
}
```

Then bind it in a service provider:

```php
$this->app->singleton(CartStorage::class, RedisCartStorage::class);
```

---

## Usage

### Adding items

**Single item (scalar arguments):**

```php
use OfflineAgency\LaravelCart\Facades\Cart;

Cart::add($id, 'Product Name', 'Subtitle', 2, 10.00, 12.20, 2.20);
```

**From a Buyable model:**

```php
$product = Product::find(1);
Cart::add($product);
```

**From an array:**

```php
Cart::add(['id' => 1, 'name' => 'Widget', 'qty' => 3, 'price' => 5.00]);
```

**Batch add:**

```php
Cart::addBatch([
    ['id' => 1, 'name' => 'A', 'qty' => 1, 'price' => 10.00],
    ['id' => 2, 'name' => 'B', 'qty' => 2, 'price' => 20.00],
]);
```

### Updating items

```php
// Update quantity
Cart::update($rowId, 5);

// Update multiple attributes
Cart::update($rowId, ['name' => 'New Name', 'qty' => 3]);
```

### Removing items

```php
Cart::remove($rowId);
Cart::destroy(); // removes all items
```

### Retrieving content

```php
Cart::content();      // Collection of CartItem objects
Cart::get($rowId);    // single CartItem
Cart::count();        // total quantity of all items
Cart::uniqueCount();  // number of unique product rows
Cart::isEmpty();      // true when cart has zero items
Cart::isNotEmpty();   // true when cart has one or more items
```

### Multiple instances

```php
Cart::instance('wishlist')->add($product);
Cart::instance('wishlist')->content();
Cart::currentInstance(); // "wishlist"
```

### Model association

```php
Cart::add(1, 'Shirt', null, 1, 29.99, 36.59, 6.60);
Cart::associate($rowId, \App\Models\Product::class);
```

### Search

```php
$results = Cart::search(fn ($item) => $item->id === 1);
Cart::isAlreadyAdded(1, \App\Models\Product::class);
$item = Cart::searchById(1, \App\Models\Product::class);
```

### Serialization

```php
Cart::toArray();   // array representation of the entire cart
Cart::toJson();    // JSON string
Cart::snapshot();  // toArray() with an ISO 8601 timestamp
```

### Metadata

```php
Cart::setMeta('notes', 'Leave at door');
Cart::getMeta('notes');       // "Leave at door"
Cart::meta();                 // ['notes' => 'Leave at door']
Cart::forgetMeta('notes');
```

### Limits

When a limit is exceeded, a `CartLimitExceededException` is thrown:

```php
// config/cart.php
'limits' => [
    'max_items'        => 50,
    'max_qty_per_item' => 10,
    'max_total'        => 9999.99,
    'min_total'        => 5.00,
],
```

Check minimum total programmatically:

```php
Cart::meetsMinimum(); // bool
```

### Weight

```php
Cart::add(1, 'Box', null, 2, 5.00, 6.10, 1.10, '', '', '', null, null, ['weight' => 1.5]);
Cart::weight(); // 3.0
```

---

## Fiscal / VAT Support

The package automatically computes VAT based on the configured `tax.tax_behavior` and `tax.default_rate`. Each `CartItem` exposes `vat`, `vatRate`, and `vatLabel` properties.

### Tax totals

```php
Cart::subtotal();       // net total (price without VAT)
Cart::vat();            // total VAT amount
Cart::total();          // gross total (subtotal + VAT, after discounts)
Cart::totalVatLabel();  // "VAT Included" or "Tax Exempt"
Cart::taxName();        // Localized tax name (e.g. "VAT")
```

### VAT breakdown (for fiscal receipts)

```php
$breakdown = Cart::vatBreakdown();
// Returns a Collection of arrays, each with:
// 'rate', 'net', 'vat', 'tax', 'gross', 'tax_name'
```

### Per-item rates

Pass the VAT amount directly when adding items:

```php
Cart::add(1, 'Standard', null, 1, 81.97, 100.00, 18.03);  // 22% VAT
Cart::add(2, 'Reduced',  null, 1, 90.91, 100.00, 9.09);   // 10% VAT
```

### FiscalBuyable

Models implementing `FiscalBuyable` carry `vatFcCode` and `productFcCode` for fiscal/e-invoicing:

```php
use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;

class Product extends Model implements FiscalBuyable
{
    use \OfflineAgency\LaravelCart\CanBeBought;

    public function getVatFcCode(): string { return $this->vat_fc_code; }
    public function getProductFcCode(): string { return $this->product_fc_code; }
}
```

---

## Coupons and Discounts

The package supports three levels of coupons: per-item, cart-level (global), and model-based via `Couponable` / `HasDiscount`.

### CartCoupon

`CartCoupon` is an immutable value object representing a coupon. Types are `fixed` or `percentage` (see `CouponType` enum).

```php
use OfflineAgency\LaravelCart\CartCoupon;

$coupon = new CartCoupon(
    hash: 'abc123', code: 'SAVE10', type: 'percentage', value: 10.0, isGlobal: true,
);
```

### Per-item coupons

```php
Cart::addItemCoupon($rowId, 'ITEM20', 'percentage', 20.0);
Cart::detachCoupon($rowId, 'ITEM20');
```

### Cart-level coupons

```php
Cart::addCoupon($coupon);                     // CartCoupon or Couponable
Cart::addGlobalCoupon('hash', 'CODE', 'fixed', 5.00);
Cart::removeCartCoupon('SAVE10');             // by hash or code
Cart::removeGlobalCoupon('hash');
Cart::listCoupons();                          // Collection<CartCoupon>
Cart::hasCartCoupon('SAVE10');                // bool
Cart::discount();                             // total discount amount
```

### syncCoupons()

Re-validates all cart-level coupons and silently removes expired or inapplicable ones:

```php
$removedCodes = Cart::syncCoupons();
```

### Couponable and HasDiscount

For Eloquent-backed coupons, implement `Couponable` and use the `HasDiscount` trait:

```php
use OfflineAgency\LaravelCart\Contracts\Couponable;
use OfflineAgency\LaravelCart\Traits\HasDiscount;

class Promo extends Model implements Couponable
{
    use HasDiscount;
}

Cart::addCoupon(Promo::find(1));
```

The `HasDiscount` trait reads from standard columns: `coupon_code`, `coupon_type`, `coupon_value`, `coupon_expires_at`, `coupon_usage_limit`, `coupon_min_cart_amount`.

---

## Middleware

### CartMergeMiddleware

Merges a guest session cart with the stored user cart upon login. Enable via config:

```php
// config/cart.php
'middleware' => [
    'merge_on_login' => true,
],
```

### CartRestoreMiddleware

Automatically restores a stored cart when an authenticated user has an empty session cart:

```php
'middleware' => [
    'auto_restore' => true,
],
```

### Registration

Register the middleware in your application (e.g. in `bootstrap/app.php` or a middleware group):

```php
use OfflineAgency\LaravelCart\Http\Middleware\CartMergeMiddleware;
use OfflineAgency\LaravelCart\Http\Middleware\CartRestoreMiddleware;

->withMiddleware(function ($middleware) {
    $middleware->web(append: [
        CartMergeMiddleware::class,
        CartRestoreMiddleware::class,
    ]);
})
```

---

## Events

| Event Class | When Fired |
|---|---|
| `CartItemAdded` | An item is added to the cart |
| `CartItemUpdated` | An item in the cart is updated |
| `CartItemRemoved` | An item is removed from the cart |
| `CartStored` | The cart is persisted to the database via `store()` |
| `CartRestored` | The cart is restored from the database via `restore()` |
| `CouponApplied` | A cart-level coupon is applied |
| `CouponRemoved` | A cart-level coupon is removed |

All event classes are in the `OfflineAgency\LaravelCart\Events` namespace.

---

## Artisan Commands

| Command | Description |
|---|---|
| `cart:clear` | Clear all stored cart records from the database. Supports `--force` and `--instance` options. |
| `cart:stats` | Display statistics about stored carts. Supports `--instance` and `--json`. |
| `cart:list` | List all stored carts. Supports `--instance`, `--limit`, `--sort`, and `--json`. |
| `cart:inspect {identifier}` | Inspect a specific stored cart in detail. Supports `--instance`, `--json`, and `--raw`. |
| `cart:prune` | Delete stored carts older than a threshold. Supports `--hours`, `--instance`, `--expired-only`, `--dry-run`, and `--force`. |
| `cart:export` | Export stored carts to a JSON file. Supports `--path`, `--instance`, `--since`, and `--pretty`. |
| `cart:migrate-driver` | Migrate stored carts between storage drivers. Supports `--from`, `--to`, `--dry-run`, `--force`, and `--chunk`. |

---

## API Resources

The package ships with Laravel API Resources for serializing cart data in API responses.

### CartResource and CartItemResource

```php
use OfflineAgency\LaravelCart\Facades\Cart;
use OfflineAgency\LaravelCart\Resources\CartResource;

class CartController extends Controller
{
    public function show()
    {
        return new CartResource(app('cart'));
    }
}
```

`CartResource` calls `Cart::toArray()` internally. `CartItemResource` wraps a single `CartItem`:

```php
use OfflineAgency\LaravelCart\Resources\CartItemResource;

return CartItemResource::collection(Cart::content());
```

---

## Localization

The package ships with translations for **English** (`en`) and **Italian** (`it`).

### Adding a new locale

Publish the translations and create a new language directory:

```bash
php artisan vendor:publish --provider="OfflineAgency\LaravelCart\CartServiceProvider" --tag=laravel-cart-lang
```

Then copy `lang/vendor/laravel-cart/en/cart.php` to your desired locale folder (e.g. `lang/vendor/laravel-cart/de/cart.php`) and translate the strings.

### Overriding existing translations

After publishing, edit the files in `lang/vendor/laravel-cart/{locale}/cart.php` directly. Your changes take precedence over the package defaults.

---

## Testing

The package provides `Cart::fake()` for testing, following the same pattern as `Mail::fake()` and `Queue::fake()`.

```php
use OfflineAgency\LaravelCart\Facades\Cart;
use OfflineAgency\LaravelCart\Events\CartItemAdded;

it('fires an event when adding an item', function () {
    $cart = Cart::fake();

    Cart::add(1, 'Test Product', null, 1, 9.99, 12.19, 2.20);

    $cart->assertDispatched(CartItemAdded::class);
    $cart->assertNotDispatched(\OfflineAgency\LaravelCart\Events\CartItemRemoved::class);
    $cart->assertDispatchedTimes(CartItemAdded::class, 1);
});
```

### Available assertions

| Method | Description |
|---|---|
| `assertDispatched($event, $callback = null)` | Assert the event was dispatched, optionally matching a callback. |
| `assertNotDispatched($event)` | Assert the event was never dispatched. |
| `assertDispatchedTimes($event, $times)` | Assert the event was dispatched exactly N times. |

---

## Upgrading

For detailed upgrade instructions between major versions, see [UPGRADE.md](UPGRADE.md).

---

## Contributing

Contributions are welcome. Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

When developing locally:

```bash
composer install
composer test        # Run the test suite (Pest)
composer lint        # Format code (Pint)
composer analyse     # Static analysis (Larastan)
```

---

## License

This package is open-sourced software licensed under the [MIT License](LICENSE).
