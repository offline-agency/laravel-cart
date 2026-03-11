# Laravel Cart

[![Latest Stable Version](https://poser.pugx.org/offline-agency/laravel-cart/v/stable)](https://packagist.org/packages/offline-agency/laravel-cart)
[![Total Downloads](https://img.shields.io/packagist/dt/offline-agency/laravel-cart.svg?style=flat-square)](https://packagist.org/packages/offline-agency/laravel-cart)
[![CI](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml/badge.svg)](https://github.com/offline-agency/laravel-cart/actions/workflows/ci.yml)
[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Pint](https://img.shields.io/badge/code%20style-pint-pink?style=flat-square)](https://github.com/laravel/pint)
[![codecov](https://codecov.io/gh/offline-agency/laravel-cart/branch/main/graph/badge.svg?token=0BHADJQYAW)](https://app.codecov.io/gh/offline-agency/laravel-cart)

A Laravel shopping cart with fiscal support. Handles VAT-inclusive pricing, Italian fiscal codes, per-item and cart-wide coupons, database persistence, and multiple cart instances.

---

## Requirements

PHP 8.2 or higher is required.

| Laravel | PHP   | Package |
|---------|-------|---------|
| 10.x    | ^8.2  | ^3.x    |
| 11.x    | ^8.2  | ^3.x    |
| 12.x    | ^8.2  | ^4.x    |

---

## Installation

Install the package via Composer:

```bash
composer require offline-agency/laravel-cart
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cart-config
```

Publish and run the migrations (required only if you use database persistence):

```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

Service provider auto-discovery registers the package automatically. No manual registration is needed.

---

## Quick Start

Add an item in a controller and display it in a Blade view:

```php
// In your controller
use OfflineAgency\LaravelCart\Facades\Cart;

$item = Cart::add(
    id: 42,
    name: 'Blue T-Shirt',
    subtitle: 'Size M',
    qty: 2,
    price: 19.67,      // price without VAT
    totalPrice: 24.00, // price with VAT included
    vat: 4.33,         // VAT amount per unit
);

return view('cart.show');
```

```blade
{{-- resources/views/cart/show.blade.php --}}
@foreach (Cart::content() as $item)
    <tr>
        <td>{{ $item->name }}</td>
        <td>{{ $item->subtitle }}</td>
        <td>{{ $item->qty }}</td>
        <td>{{ $item->totalPrice }}</td>
    </tr>
@endforeach

<p>Total (with VAT): {{ Cart::total() }}</p>
<p>Subtotal (ex-VAT): {{ Cart::subtotal() }}</p>
<p>Total VAT: {{ Cart::vat() }}</p>
```

---

## Configuration

Publish the config file with `php artisan vendor:publish --tag=cart-config` before changing these values.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `database.connection` | `string\|null` | `null` | Database connection name. `null` uses the application default. |
| `database.table` | `string` | `'cart'` | Table name for stored carts. |
| `destroy_on_logout` | `bool` | `false` | When `true`, all cart instances are destroyed when the user logs out. |
| `format.decimals` | `int` | `2` | Number of decimal places for formatted output. |
| `format.decimal_point` | `string` | `'.'` | Decimal point character. |
| `format.thousand_separator` | `string` | `','` | Thousand separator character. |
| `global_coupons_enabled` | `bool` | `true` | Enable the cart-wide coupon system (`addGlobalCoupon` / `globalCouponDiscount`). |
| `coupon_class` | `string` | `CartCoupon::class` | Class used to represent coupon objects. |
| `use_legacy_events` | `bool` | `true` | When `true`, string events (`cart.added`, etc.) are dispatched alongside typed event objects. Set to `false` to dispatch only typed events. |
| `rounding_mode` | `int` | `PHP_ROUND_HALF_UP` | Rounding mode used by `vatBreakdown()`. Any `PHP_ROUND_*` constant is accepted. |

---

## CartItem Reference

Every `Cart::add()` call returns a `CartItem` instance. The following properties are available:

| Property | Type | Description |
|----------|------|-------------|
| `rowId` | `string` | MD5 hash derived from `$id` + serialized `$options`. Two items with the same `id` but different `options` produce different `rowId` values. |
| `id` | `int\|string` | The product identifier passed to `add()`. |
| `name` | `string` | Product name. |
| `subtitle` | `string` | Product subtitle or short description. |
| `qty` | `int` | Quantity. |
| `price` | `float` | Unit price excluding VAT, after any coupons. |
| `totalPrice` | `float` | Unit price including VAT, after any coupons. |
| `vat` | `float` | VAT amount per unit, after any coupons. |
| `vatRate` | `float` | VAT rate as a percentage (calculated: `100 × vat / price`). |
| `vatLabel` | `string` | `'Iva Inclusa'` when VAT > 0, otherwise `'Esente Iva'`. |
| `originalPrice` | `float` | Unit price excluding VAT before any coupons. |
| `originalTotalPrice` | `float` | Unit price including VAT before any coupons. |
| `originalVat` | `float` | VAT per unit before any coupons. |
| `discountValue` | `float` | Total discount amount applied to this item across all coupons. |
| `vatFcCode` | `string` | VAT nature code for fiscal receipts (e.g. Italian `N2`, `N4`). |
| `productFcCode` | `string` | Product fiscal code for receipts. |
| `urlImg` | `string` | Product image URL. |
| `options` | `CartItemOptions` | Arrayable collection of custom options. Access as `$item->options->size`. |
| `associatedModel` | `string\|null` | Fully-qualified class name of the associated Eloquent model. |
| `model` | `Model\|null` | The associated Eloquent model instance (loaded via `find($id)` on access). |
| `appliedCoupons` | `array` | Keyed array of coupons applied to this item. |
| `priceTax` | `float` | `price + tax` (computed via `__get`). |
| `subtotal` | `float` | `qty × price` (computed via `__get`). |
| `total` | `float` | `qty × priceTax` (computed via `__get`). |
| `tax` | `float` | `price × (taxRate / 100)` (computed via `__get`). |
| `taxTotal` | `float` | `tax × qty` (computed via `__get`). |

**How `rowId` works:** The `rowId` is computed as `md5($id . serialize(ksort($options)))`. Two cart items with `id = 5` but `options = ['color' => 'red']` and `options = ['color' => 'blue']` produce different `rowId` values and appear as separate rows. Use `options` intentionally to force separation of otherwise identical products.

---

## Usage

### Cart::add()

```php
Cart::add(
    mixed $id,
    mixed $name = null,
    ?string $subtitle = null,
    ?int $qty = null,
    ?float $price = null,
    ?float $totalPrice = null,
    ?float $vat = null,
    ?string $vatFcCode = '',
    ?string $productFcCode = '',
    ?string $urlImg = '',
    array $options = []
): array|CartItem
```

Adds one or more items to the cart. Returns a `CartItem` when a single item is added, or an `array` of `CartItem` when an array of items is passed.

**Adding a single item by attributes:**

```php
$item = Cart::add(
    id: 1,
    name: 'White Shirt',
    subtitle: 'Size L',
    qty: 1,
    price: 19.67,
    totalPrice: 24.00,
    vat: 4.33,
    vatFcCode: '',
    productFcCode: '',
    urlImg: 'https://example.com/shirt.jpg',
    options: ['color' => 'white', 'size' => 'L']
);
```

**Adding a `Buyable` instance** (when `$id` implements `Buyable`, `$name` acts as the quantity):

```php
$product = Product::find(1); // implements Buyable

$item = Cart::add($product, 2); // 2 = qty
```

**Adding multiple items at once:**

```php
$items = Cart::add([
    ['id' => 1, 'name' => 'Shirt', 'subtitle' => '', 'qty' => 1, 'price' => 19.67, 'totalPrice' => 24.00, 'vat' => 4.33],
    ['id' => 2, 'name' => 'Jeans', 'subtitle' => '', 'qty' => 2, 'price' => 49.18, 'totalPrice' => 60.00, 'vat' => 10.82],
]);
// $items is an array of CartItem
```

If an item with the same `rowId` already exists, the quantities are summed.

---

### Cart::update()

```php
Cart::update(string $rowId, mixed $qty): ?CartItem
```

Updates the cart item identified by `$rowId`. `$qty` accepts an integer, an associative array of attributes, or a `Buyable` instance. Returns `null` when the item is removed (qty ≤ 0).

```php
// Update quantity
Cart::update($item->rowId, 3);

// Update multiple attributes
Cart::update($item->rowId, ['qty' => 3, 'price' => 15.00]);

// Setting qty to 0 or negative removes the item
Cart::update($item->rowId, 0); // returns null, item removed
```

---

### Cart::remove()

```php
Cart::remove(string $rowId): void
```

Removes the item with the given `rowId` from the cart. All per-item coupons are silently detached before removal. Fires `cart.removed`.

```php
Cart::remove($item->rowId);
```

**Throws:** `InvalidRowIDException` if `$rowId` does not exist.

---

### Cart::get()

```php
Cart::get(string $rowId): CartItem
```

Returns the `CartItem` with the given `rowId`.

```php
$item = Cart::get('d8e4a45c...');
echo $item->name;
```

**Throws:** `InvalidRowIDException` if `$rowId` does not exist.

---

### Cart::content()

```php
Cart::content(): Collection<string, CartItem>
```

Returns all items in the current cart instance as a `Collection` keyed by `rowId`.

```php
$items = Cart::content();

foreach ($items as $rowId => $item) {
    echo "{$item->name}: {$item->qty} × {$item->totalPrice}";
}
```

---

### Cart::destroy()

```php
Cart::destroy(): void
```

Removes all items, clears global coupons, and removes cart options from the session.

```php
// After successful checkout
Cart::destroy();

// For a specific instance
Cart::instance('wishlist')->destroy();
```

---

### Cart::total()

```php
Cart::total(
    ?int $decimals = null,
    ?string $decimalPoint = null,
    ?string $thousandSeparator = null
): float
```

Returns the cart total including VAT, after all item-level coupons. Falls back to `config('cart.format.*')` values when parameters are `null`. The result is never negative (returns `0` if coupons exceed the total).

```php
$total = Cart::total();         // e.g. 88.00
$formatted = Cart::total(2, '.', ','); // uses explicit format
```

The magic property `$cart->total` is available when the `Cart` object is resolved via dependency injection (not the Facade).

---

### Cart::subtotal()

```php
Cart::subtotal(
    ?int $decimals = null,
    ?string $decimalPoint = null,
    ?string $thousandSeparator = null
): float
```

Returns the sum of all item `price` values (excluding VAT). Discount cart items are excluded from the subtotal calculation.

```php
$subtotal = Cart::subtotal(); // e.g. 72.13
```

---

### Cart::vat()

```php
Cart::vat(
    ?int $decimals = null,
    ?string $decimalPoint = null,
    ?string $thousandSeparator = null
): float
```

Returns the total VAT amount across all items in the cart.

```php
$totalVat = Cart::vat(); // e.g. 15.87
```

`Cart::totalVatLabel()` returns `'Iva Inclusa'` when any VAT is present, or `'Esente Iva'` when total VAT is zero.

---

### Cart::count()

```php
Cart::count(): int|float
```

Returns the sum of all item quantities (not the number of distinct rows).

```php
// Cart has 2 × Shirt and 3 × Jeans
Cart::count(); // 5
```

---

### Cart::search()

```php
Cart::search(Closure $search): Collection<string, CartItem>
```

Filters cart content using a closure. Returns a `Collection` of matching `CartItem` instances.

```php
// Find items by product id
$found = Cart::search(fn (CartItem $item) => $item->id === 42);

// Find items with a specific option value
$redItems = Cart::search(fn (CartItem $item) => $item->options->color === 'red');

// Find by name (case-insensitive)
$shirts = Cart::search(fn (CartItem $item) => str_contains(strtolower($item->name), 'shirt'));
```

---

### Utility Methods

```php
Cart::isEmpty(): bool
Cart::isNotEmpty(): bool
Cart::uniqueCount(): int                          // number of distinct rows (not sum of qty)
Cart::first(?Closure $callback = null): ?CartItem // first item, or first matching a closure
Cart::where(string $key, mixed $value): Collection<string, CartItem>
```

```php
if (Cart::isEmpty()) {
    return redirect()->route('shop');
}

$itemCount = Cart::uniqueCount(); // 2 rows even if total qty is 5

$first = Cart::first();
$shirt = Cart::first(fn (CartItem $item) => $item->id === 42);

$redItems = Cart::where('options.color', 'red');
```

---

### Cart::addBatch()

```php
Cart::addBatch(array $items): Collection<string, CartItem>
```

Adds multiple items from an array and returns the updated cart content.

```php
Cart::addBatch([
    ['id' => 1, 'name' => 'Shirt',  'subtitle' => '', 'qty' => 1, 'price' => 19.67, 'totalPrice' => 24.00, 'vat' => 4.33],
    ['id' => 2, 'name' => 'Jeans',  'subtitle' => '', 'qty' => 1, 'price' => 49.18, 'totalPrice' => 60.00, 'vat' => 10.82],
]);
```

---

### Cart::sync()

```php
Cart::sync(array $items): static
```

Synchronises the cart with the given items array. Items in the cart that are absent from `$items` are removed. Items in `$items` that are absent from the cart are added. Items present in both are updated to the quantity from `$items`.

```php
Cart::sync([
    ['id' => 1, 'name' => 'Shirt', 'subtitle' => '', 'qty' => 3, 'price' => 19.67, 'totalPrice' => 24.00, 'vat' => 4.33],
    // id 2 is absent → removed from cart if it was there
]);
```

---

### Cart::associate()

```php
Cart::associate(string $rowId, mixed $model): void
```

Associates the cart item with an Eloquent model. After calling this, `$item->model` returns the model instance via `find($item->id)`.

```php
Cart::associate($item->rowId, \App\Models\Product::class);

$product = Cart::get($item->rowId)->model; // triggers Product::find($item->id)
```

**Throws:** `UnknownModelException` when a string class name is passed that does not exist.

---

### Cart::numberFormat()

```php
Cart::numberFormat(
    float|int $value,
    ?int $decimals,
    ?string $decimalPoint,
    ?string $thousandSeparator
): string
```

Formats a number using the cart's configured (or provided) format settings.

```php
echo Cart::numberFormat(1234.5, 2, '.', ','); // "1,234.50"
```

---

## Instances

The cart supports multiple named instances, each stored independently in the session.

```php
Cart::instance(?string $instance = null): Cart
Cart::currentInstance(): string
```

The default instance name is `'default'`. Switch instances with `Cart::instance()`. The call is fluent, so you can chain operations.

```php
// Work with a wishlist alongside the main cart
Cart::instance('wishlist')->add(5, 'Gift Item', '', 1, 30.00, 36.60, 6.60);

Cart::instance('shopping')->add(7, 'Daily Use', '', 2, 10.00, 12.20, 2.20);

// Restore to default
Cart::instance(); // back to 'default'

echo Cart::instance('wishlist')->currentInstance(); // 'wishlist'
```

---

## The Buyable Interface

Any class can be passed directly to `Cart::add()` by implementing `OfflineAgency\LaravelCart\Contracts\Buyable`:

```php
namespace OfflineAgency\LaravelCart\Contracts;

interface Buyable
{
    public function getId(): int|string;
    public function setId(int|string $id): void;

    public function getName(): string;
    public function setName(string $name): void;

    public function getSubtitle(): string;
    public function setSubtitle(string $subtitle): void;

    public function getQty(): int;
    public function setQty(int $qty): void;

    public function getPrice(): float;
    public function setPrice(float $price): void;

    public function getTotalPrice(): float;
    public function setTotalPrice(float $totalPrice): void;

    public function getVat(): float;
    public function setVat(float $vat): void;

    public function getVatFcCode(): string;
    public function setVatFcCode(string $vatFcCode): void;

    public function getProductFcCode(): string;
    public function setProductFcCode(string $productFcCode): void;

    public function getUrlImg(): string;
    public function setUrlImg(mixed $urlImg): void;

    public function getOptions(): array;
    public function setOptions(array $options): void;
}
```

**Complete `Product` model example:**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OfflineAgency\LaravelCart\Contracts\Buyable;

class Product extends Model implements Buyable
{
    protected $fillable = [
        'name', 'subtitle', 'price', 'total_price', 'vat',
        'vat_fc_code', 'product_fc_code', 'url_img',
    ];

    public function getId(): int|string { return $this->id; }
    public function setId(int|string $id): void { $this->id = $id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }

    public function getSubtitle(): string { return $this->subtitle ?? ''; }
    public function setSubtitle(string $subtitle): void { $this->subtitle = $subtitle; }

    public function getQty(): int { return 1; }
    public function setQty(int $qty): void {}

    public function getPrice(): float { return (float) $this->price; }
    public function setPrice(float $price): void { $this->price = $price; }

    public function getTotalPrice(): float { return (float) $this->total_price; }
    public function setTotalPrice(float $totalPrice): void { $this->total_price = $totalPrice; }

    public function getVat(): float { return (float) $this->vat; }
    public function setVat(float $vat): void { $this->vat = $vat; }

    public function getVatFcCode(): string { return $this->vat_fc_code ?? ''; }
    public function setVatFcCode(string $vatFcCode): void { $this->vat_fc_code = $vatFcCode; }

    public function getProductFcCode(): string { return $this->product_fc_code ?? ''; }
    public function setProductFcCode(string $productFcCode): void { $this->product_fc_code = $productFcCode; }

    public function getUrlImg(): string { return $this->url_img ?? ''; }
    public function setUrlImg(mixed $urlImg): void { $this->url_img = $urlImg; }

    public function getOptions(): array { return []; }
    public function setOptions(array $options): void {}
}
```

**Auto-association:** When `Cart::add($product, $qty)` receives a `Buyable`, the cart automatically calls `associate()` on the resulting `CartItem`. Accessing `$item->model` later returns a fresh `Product::find($item->id)` instance.

**Using the `CanBeBought` trait:** For models with standard property names (`id`, `name`, `title`, `description`, `price`), the `CanBeBought` trait provides default implementations of `getId()`, `getName()`/`getSubtitle()`/`getDescription()`, and `getPrice()`:

```php
use OfflineAgency\LaravelCart\CanBeBought;
use OfflineAgency\LaravelCart\Contracts\Buyable;

class SimpleProduct extends Model implements Buyable
{
    use CanBeBought;

    // Only implement the remaining methods not covered by the trait
    public function getSubtitle(): string { return ''; }
    // ... etc.
}
```

---

## Model Association

Associate a cart item with an Eloquent model after it has been added:

```php
$item = Cart::add(42, 'Shirt', '', 1, 19.67, 24.00, 4.33);

Cart::associate($item->rowId, \App\Models\Product::class);
```

After association, accessing `$item->model` triggers `Product::find($item->id)` and returns the model. The association stores only the class name in the session; the model is not serialized.

```php
$item = Cart::get($rowId);
$product = $item->model; // App\Models\Product instance, or null if not found
```

**Throws:** `UnknownModelException` when the supplied string class name does not exist.

---

## Coupons

The package supports two distinct coupon systems: **item-level coupons** that reduce the price of a specific cart item, and **cart-level (global) coupons** that are calculated against the cart total.

### Item-Level Coupons

```php
// Preferred non-deprecated alias (v4.1+)
Cart::addItemCoupon(
    mixed $rowId,
    string $couponCode,
    string $couponType,  // 'fixed' or 'percentage'
    float $couponValue
): void

// Kept for backward compatibility — deprecated since 4.1
Cart::applyCoupon(mixed $rowId, string $couponCode, string $couponType, float $couponValue): void
```

Apply a coupon to a specific item. The `rowId` must be a valid cart item row.

```php
// Add an item first to obtain the rowId
$item = Cart::add(1, 'Shirt', '', 2, 19.67, 24.00, 4.33);

// Apply a fixed €5 discount
Cart::addItemCoupon($item->rowId, 'SAVE5', 'fixed', 5.00);

// Or apply a 10% discount
Cart::addItemCoupon($item->rowId, 'PROMO10', 'percentage', 10.0);
```

**Discount calculation:**
- `'fixed'`: deducts `$couponValue` from `totalPrice`, then back-calculates `price` and `vat`
- `'percentage'`: deducts `($couponValue / 100) × originalTotalPrice` from `totalPrice`, then back-calculates `price` and `vat`

Multiple coupons can be applied to the same item. Each subsequent coupon operates on the already-reduced `totalPrice`.

**Removing item-level coupons:**

```php
// Remove one coupon from a specific item
Cart::detachCoupon($item->rowId, 'SAVE5');

// Remove a coupon by code, searching all items
Cart::removeCoupon('SAVE5');  // throws InvalidCouponHashException if not found

// Remove all per-item coupons from the entire cart
Cart::removeAllCoupons();
```

**Querying item-level coupons:**

```php
// Check whether any per-item coupon exists
Cart::hasCoupons(); // bool

// Check for a specific coupon code
Cart::hasCoupon('SAVE5'); // bool

// Retrieve a specific coupon object
$coupon = Cart::getCoupon('SAVE5');

// Get all per-item coupons as a raw array
$raw = Cart::coupons(); // array<string, object>

// Get all per-item coupons as CartCoupon instances
$coupons = Cart::getCoupons(); // Collection<string, CartCoupon>
```

---

### Cart-Level (Global) Coupons

Global coupons apply a discount to the cart total and are calculated separately from item prices. Two APIs exist: the **new `addCoupon()` API** (v4.1+, recommended) and the **legacy `addGlobalCoupon()` API** (still supported).

#### New API (v4.1+)

```php
// Add a CartCoupon object (full validation: expiry, minCartAmount)
Cart::addCoupon(string|CartCoupon|Couponable $coupon): static

// Remove by hash or coupon code
Cart::removeCartCoupon(string $hashOrCode): static

// Query
Cart::listCoupons(): Collection          // all cart-level coupons
Cart::hasCartCoupon(string $hashOrCode): bool
Cart::discount(): float                  // total discount amount from all coupons
Cart::syncCoupons(): array               // re-validate; returns removed coupon codes
```

```php
use Carbon\Carbon;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;

$coupon = new CartCoupon(
    hash: 'promo-2025',
    code: 'SUMMER25',
    type: 'percentage',
    value: 25.0,
    isGlobal: true,
    expiresAt: Carbon::parse('2025-08-31'),
    minCartAmount: 50.0,
);

try {
    Cart::addCoupon($coupon);   // validates expiry and minCartAmount
} catch (InvalidCouponException $e) {
    // coupon expired or cart total is below minCartAmount
} catch (CouponAlreadyAppliedException $e) {
    // same hash already in the cart
}

Cart::discount();                   // e.g. 25.00 (25% of 100.00)
Cart::total();                      // deducted automatically: 75.00

Cart::removeCartCoupon('SUMMER25'); // remove by code or hash
```

`Cart::total()` automatically deducts cart-level coupon discounts. You do not need to subtract manually.

#### Legacy API

```php
Cart::addGlobalCoupon(
    string $couponHash,
    string $code,
    string $type,       // 'percentage' | 'fixed'
    float|int $value
): static
```

```php
// Add a 10% cart-wide coupon
Cart::addGlobalCoupon('hash-abc', 'CART10', 'percentage', 10.0);

// Add a fixed €20 discount
Cart::addGlobalCoupon('hash-xyz', 'FLAT20', 'fixed', 20.0);
```

Global coupons persist in the session alongside cart items. Use `$couponHash` (any unique string) as the key to remove a specific coupon later.

**Managing legacy global coupons:**

```php
// Remove one global coupon by hash
Cart::removeGlobalCoupon('hash-abc');  // throws InvalidCouponHashException if not found

// Get all global coupons
$globals = Cart::getGlobalCoupons(); // Collection<string, CartCoupon>

// Calculate the total discount from all global coupons
$cartTotal = (string) Cart::total(); // e.g. '100.00'
$discount  = Cart::globalCouponDiscount($cartTotal); // e.g. '30.00'

$finalTotal = (float) $cartTotal - (float) $discount;
```

---

### How Discounts Are Calculated

**Item-level (`'fixed'`):** The fixed value is subtracted from `totalPrice`. `price` and `vat` are back-calculated from the new `totalPrice` using the item's `vatRate`. The item's `discountValue` accumulates each coupon's contribution.

**Item-level (`'percentage'`):** The discount is `($value / 100) × originalTotalPrice`. The result is subtracted from the current `totalPrice` (not `originalTotalPrice`), so stacked percentage coupons compound.

**Global coupons — ordering:** `globalCouponDiscount()` sorts coupons so percentage coupons apply first, then fixed coupons, regardless of insertion order.

**Global coupon cap:** Fixed global coupons are capped at the remaining total. The discount never drives the total below zero.

**Worked numeric example:**

```text
Cart::total() = 100.00

Global coupon 1: 10% percentage
  discount = 100.00 × 10 / 100 = 10.00
  remaining = 90.00

Global coupon 2: fixed 20.00
  discount = min(20.00, 90.00) = 20.00
  remaining = 70.00

Cart::globalCouponDiscount('100.00') → '30.00'
final total = 100.00 - 30.00 = 70.00
```

---

### CartCoupon Reference

Both item-level and global coupons are represented as `CartCoupon` objects:

```php
final readonly class CartCoupon implements Couponable, JsonSerializable
{
    public function __construct(
        public string   $hash,
        public string   $code,
        public string   $type,             // 'fixed' | 'percentage'
        public float    $value,
        public bool     $isGlobal = false,
        public ?Carbon  $expiresAt = null, // null = never expires
        public ?int     $usageLimit = null,
        public ?float   $minCartAmount = null,
    ) {}

    public function isPercentage(): bool;
    public function isFixed(): bool;
    public function couponType(): CouponType;   // bridge to CouponType enum
    public function isExpired(): bool;          // true when expiresAt is in the past
    public function isApplicableTo(float $cartTotal): bool; // checks minCartAmount
    public function toArray(): array;
    public function jsonSerialize(): array;
}
```

Because `CartCoupon` is `final readonly`, all properties are immutable after construction.

**Creating a coupon with constraints:**

```php
use Carbon\Carbon;
use OfflineAgency\LaravelCart\CartCoupon;

$coupon = new CartCoupon(
    hash: 'promo-2025',
    code: 'SUMMER25',
    type: 'percentage',
    value: 25.0,
    isGlobal: true,
    expiresAt: Carbon::parse('2025-08-31'),
    minCartAmount: 50.0,
);

$coupon->isExpired();            // false (before expiry)
$coupon->isApplicableTo(49.99); // false (below minCartAmount)
$coupon->isApplicableTo(50.00); // true
```

---

## Fiscal Support (VAT)

The package is designed for VAT-inclusive pricing as used in Italian fiscal receipts.

**VAT is passed as an amount, not a rate.** When adding an item with `price = 19.67`, `totalPrice = 24.00`, and `vat = 4.33`, the cart stores all three values and derives `vatRate = 22%` automatically.

```php
// Adding a 22% VAT item
Cart::add(
    id: 1,
    name: 'Product A',
    subtitle: '',
    qty: 1,
    price: 19.67,       // ex-VAT unit price
    totalPrice: 24.00,  // VAT-inclusive unit price
    vat: 4.33,          // VAT amount
    vatFcCode: '',       // VAT nature code (e.g. 'N4' for exempt)
    productFcCode: '',   // product fiscal code
);
```

**Per-item fiscal properties:**

| Property | Description |
|----------|-------------|
| `vatRate` | Calculated: `100 × vat / price` |
| `vatLabel` | `'Iva Inclusa'` when `vat > 0`, else `'Esente Iva'` |
| `vatFcCode` | VAT nature code for the fiscal document |
| `productFcCode` | Product fiscal code |

**Cart-level totals:**

```php
Cart::total();         // sum of all item totalPrice × qty, minus cart-level coupon discounts
Cart::subtotal();      // sum of all item price × qty (ex-VAT)
Cart::vat();           // sum of all item vat × qty
Cart::totalVatLabel(); // 'Iva Inclusa' or 'Esente Iva'
```

**VAT breakdown for fiscal receipts:**

```php
Cart::vatBreakdown(): Collection<int, array{rate: float, net: string, vat: string, gross: string}>
```

Groups all cart items by their effective VAT rate and returns formatted totals per rate, suitable for printing on fiscal receipts.

```php
$breakdown = Cart::vatBreakdown();

// Example output for a cart with 22% and 10% VAT items:
// [
//   ['rate' => 22.0, 'net' => '100.00', 'vat' => '22.00', 'gross' => '122.00'],
//   ['rate' => 10.0, 'net' =>  '50.00', 'vat' =>  '5.00', 'gross' =>  '55.00'],
// ]

foreach ($breakdown as $row) {
    echo "VAT {$row['rate']}%: net {$row['net']}, vat {$row['vat']}, gross {$row['gross']}";
}
```

Phantom discount items (added via `applyGlobalCoupon`) are excluded from the breakdown. Rounding is controlled by `config('cart.rounding_mode')`.

**Per-item tax rate override:**

Pass `tax_rate` in the `options` array to override the calculated VAT rate for a specific item. Useful when different products carry different VAT rates within the same cart.

```php
// Item where price is net (ex-VAT) and tax_rate applies
Cart::add(
    id: 2,
    name: 'Reduced-rate Item',
    subtitle: '',
    qty: 1,
    price: 100.0,
    totalPrice: 100.0,  // will be recalculated from tax_rate
    vat: 0.0,
    options: ['tax_rate' => 10.0],  // overrides VAT rate to 10%
);
// resulting item: vatRate=10.0, vat=10.0, totalPrice=110.0
```

---

## Database Persistence

### Storing the Cart

```php
Cart::store(mixed $identifier): void
```

Serializes the current cart instance to the database table defined in `config('cart.database.table')`.

```php
Cart::store(auth()->id());
```

**Throws:** `CartAlreadyStoredException` if a cart with the same identifier is already in the table.

### Restoring the Cart

```php
Cart::restore(mixed $identifier, bool $mergeOnRestore = false): void
```

Loads a stored cart from the database and deletes the database row. Returns silently if the identifier does not exist.

- **`$mergeOnRestore = false` (default):** Replaces the current session cart with the stored cart.
- **`$mergeOnRestore = true`:** Merges the stored cart into the current session cart. Items already present (same `rowId`) are kept as-is; only new rows are added.

Cart-level coupons stored with the cart are automatically restored.

```php
// Replace current cart with stored cart
Cart::restore(auth()->id());

// Merge stored cart into current session cart
Cart::restore(auth()->id(), mergeOnRestore: true);
```

### Migrations

Publish and run the package migrations before using `store()` and `restore()`:

```bash
php artisan vendor:publish --tag=cart-migrations
php artisan migrate
```

The migration creates the table specified in `config('cart.database.table')` (default: `cart`).

---

## Events

The package dispatches both **typed event objects** and **legacy string events**. By default both are dispatched (`use_legacy_events = true`). Set `use_legacy_events = false` in config to dispatch only typed events.

### Typed Events

| Class | Fired when | Properties |
|---|---|---|
| `CartItemAdded` | An item is added | `CartItem $cartItem` |
| `CartItemUpdated` | An item is updated | `CartItem $cartItem` |
| `CartItemRemoved` | An item is removed | `CartItem $cartItem` |
| `CartStored` | The cart is stored to the database | `mixed $identifier`, `string $instance` |
| `CartRestored` | The cart is restored from the database | `mixed $identifier`, `string $instance` |
| `CouponApplied` | A coupon is added via `addCoupon()` | `CartCoupon $coupon`, `string $cartInstance` |
| `CouponRemoved` | A coupon is removed via `removeCartCoupon()` | `CartCoupon $coupon`, `string $cartInstance` |

All typed events are `final readonly` classes in `OfflineAgency\LaravelCart\Events\`.

### Legacy String Events

| Event string | Fired when | Listener receives |
|---|---|---|
| `cart.added` | An item is added | `CartItem $item` |
| `cart.updated` | An item is updated | `CartItem $item` |
| `cart.removed` | An item is removed | `CartItem $item` |
| `cart.stored` | The cart is stored | _(nothing)_ |
| `cart.restored` | The cart is restored | _(nothing)_ |
| `cart.coupon_removed` | A per-item coupon is removed via `removeCoupon()` | `string $couponCode` |
| `cart.coupons_cleared` | All per-item coupons removed via `removeAllCoupons()` | _(nothing)_ |
| `cart.global_coupon_added` | A global coupon is added via `addGlobalCoupon()` | `CartCoupon $coupon` |
| `cart.global_coupon_removed` | A global coupon is removed via `removeGlobalCoupon()` | `CartCoupon $coupon` |

**Listening to typed events:**

```php
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CouponApplied;

// In EventServiceProvider or using #[AsEventListener]
protected $listen = [
    CartItemAdded::class => [
        \App\Listeners\UpdateCartCountCache::class,
    ],
    CouponApplied::class => [
        \App\Listeners\LogCouponUsage::class,
    ],
    // legacy string events still work when use_legacy_events = true
    'cart.added' => [
        \App\Listeners\LegacyListener::class,
    ],
];
```

**Disabling legacy string events:**

```php
// config/cart.php
'use_legacy_events' => false,  // only typed events are dispatched
```

**Logout handling:** When `destroy_on_logout = true` in config, the service provider listens to `Illuminate\Auth\Events\Logout` and calls `Cart::instance()->destroy()` automatically.

---

## Exceptions

### `InvalidRowIDException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException`

Thrown by `Cart::get()`, `Cart::update()`, `Cart::remove()`, and `Cart::associate()` when the given `rowId` does not exist in the current cart instance.

```php
use OfflineAgency\LaravelCart\Exceptions\InvalidRowIDException;

try {
    $item = Cart::get('non-existing-row-id');
} catch (InvalidRowIDException $e) {
    // The rowId is not in the cart
    report($e);
}
```

---

### `CartAlreadyStoredException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException`

Thrown by `Cart::store()` when a cart with the given identifier already exists in the database table.

```php
use OfflineAgency\LaravelCart\Exceptions\CartAlreadyStoredException;

try {
    Cart::store(auth()->id());
} catch (CartAlreadyStoredException $e) {
    // A stored cart already exists for this user
    // Consider deleting the old row first or using restore() + store()
}
```

---

### `UnknownModelException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\UnknownModelException`

Thrown by `Cart::associate()` when a string class name is supplied that does not exist.

```php
use OfflineAgency\LaravelCart\Exceptions\UnknownModelException;

try {
    Cart::associate($item->rowId, 'App\Models\NonExistingProduct');
} catch (UnknownModelException $e) {
    // The model class does not exist
}
```

---

### `InvalidCouponHashException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\InvalidCouponHashException`

Thrown by `Cart::removeCoupon()` when the coupon code is not found on any item, and by `Cart::removeGlobalCoupon()` when the hash is not in the global coupons collection.

```php
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponHashException;

try {
    Cart::removeCoupon('EXPIRED_CODE');
} catch (InvalidCouponHashException $e) {
    // Coupon not found in cart
}

try {
    Cart::removeGlobalCoupon('stale-hash');
} catch (InvalidCouponHashException $e) {
    // Global coupon not found
}
```

---

## Artisan Commands

### `cart:clear`

```bash
php artisan cart:clear [--force] [--instance=<name>]
```

Clears stored carts from the database table. Without `--force` the command prompts for confirmation. Use `--instance` to limit deletion to a specific cart instance.

```bash
# Clear all stored carts (with confirmation prompt)
php artisan cart:clear

# Skip confirmation
php artisan cart:clear --force

# Clear only stored carts for the 'wishlist' instance
php artisan cart:clear --instance=wishlist --force
```

---

## Testing Your Application

### `Cart::fake()` Test Helper

`Cart::fake()` creates an in-memory `Cart` instance and swaps the container binding so the Facade resolves the same fake object. No database or real session is needed.

```php
use OfflineAgency\LaravelCart\Facades\Cart;

it('totals are correct', function () {
    Cart::fake();

    Cart::add('1', 'Alpha', '', 2, 10.0, 12.2, 2.2);
    Cart::add('2', 'Beta',  '', 1,  5.0,  6.1, 1.1);

    expect(Cart::count())->toBe(3)
        ->and(Cart::uniqueCount())->toBe(2)
        ->and(Cart::isEmpty())->toBeFalse();
});
```

`Cart::fake()` returns the `Cart` instance so you can keep a reference:

```php
$fake = Cart::fake();

Cart::add('1', 'Item', '', 1, 9.99, 12.19, 2.20);

expect($fake->count())->toBe(1);
```

### Using FeatureTestCase

Extend the package's own `FeatureTestCase` in your Pest tests:

```php
// tests/Pest.php
use OfflineAgency\LaravelCart\Tests\FeatureTestCase;

uses(FeatureTestCase::class)->in('.');
```

`FeatureTestCase` configures SQLite in-memory, the array session driver, and loads package migrations automatically.

### Testing Cart Operations

```php
use OfflineAgency\LaravelCart\Facades\Cart;

it('adds an item and returns the correct total', function () {
    $item = Cart::add(1, 'Shirt', '', 2, 19.67, 24.00, 4.33);

    expect(Cart::count())->toBe(2)
        ->and(Cart::total())->toBe(48.0)
        ->and(Cart::vat())->toBe(8.66);
});

it('removes an item from the cart', function () {
    $item = Cart::add(1, 'Shirt', '', 1, 19.67, 24.00, 4.33);

    Cart::remove($item->rowId);

    expect(Cart::content())->toBeEmpty();
});
```

### Testing with Cart Instances

```php
it('keeps wishlist and shopping cart separate', function () {
    Cart::instance('shopping')->add(1, 'Shirt', '', 1, 19.67, 24.00, 4.33);
    Cart::instance('wishlist')->add(2, 'Hat',   '', 1, 12.30, 15.00, 2.70);

    expect(Cart::instance('shopping')->count())->toBe(1)
        ->and(Cart::instance('wishlist')->count())->toBe(1);
});
```

### Testing Event Listeners

```php
use Illuminate\Support\Facades\Event;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Events\CouponApplied;
use OfflineAgency\LaravelCart\CartCoupon;

it('dispatches CartItemAdded typed event', function () {
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');

    $cart->add(1, 'Shirt', '', 1, 19.67, 24.00, 4.33);

    Event::assertDispatched(CartItemAdded::class);
    Event::assertDispatched('cart.added'); // legacy string also dispatched when use_legacy_events=true
});

it('dispatches CouponApplied when addCoupon is called', function () {
    Event::fake();
    $this->app->forgetInstance('cart');
    $cart = $this->app->make('cart');

    $coupon = new CartCoupon(hash: 'h1', code: 'SAVE10', type: 'fixed', value: 10.0, isGlobal: true);
    $cart->addCoupon($coupon);

    Event::assertDispatched(CouponApplied::class, fn (CouponApplied $e) => $e->coupon->code === 'SAVE10');
});
```

---

### `CouponAlreadyAppliedException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException`

Thrown by `Cart::addCoupon()` when a coupon with the same hash is already in the cart.

```php
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;

try {
    Cart::addCoupon($coupon);
    Cart::addCoupon($coupon); // duplicate hash
} catch (CouponAlreadyAppliedException $e) {
    echo $e->couponCode; // the duplicate coupon code
}
```

---

### `CouponNotFoundException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\CouponNotFoundException`

Thrown by `Cart::removeCartCoupon()` when the hash or code is not found.

```php
use OfflineAgency\LaravelCart\Exceptions\CouponNotFoundException;

try {
    Cart::removeCartCoupon('NONEXISTENT');
} catch (CouponNotFoundException $e) {
    // $e->couponCode contains the searched value
}
```

---

### `InvalidCouponException`

**Class:** `OfflineAgency\LaravelCart\Exceptions\InvalidCouponException`

Thrown by `Cart::addCoupon()` when a coupon fails validation: the coupon is expired (`isExpired()` returns `true`) or the cart total is below the required `minCartAmount`.

```php
use OfflineAgency\LaravelCart\Exceptions\InvalidCouponException;

try {
    Cart::addCoupon($expiredCoupon);
} catch (InvalidCouponException $e) {
    // $e->couponCode contains the rejected coupon code
}
```

---

## Upgrade Guide

### v3.x → v4.x

**PHP and Laravel:** PHP minimum stays at 8.2. Laravel 12 is now the primary target.

**`CartCoupon` is now `final readonly`:** Any code that mutates `CartCoupon` properties directly after construction will throw an `Error`. Use `addGlobalCoupon()` to create new coupons instead.

**`applyGlobalCoupon()` is deprecated:** Replace calls to `Cart::applyCoupon($rowId = null, ...)` with `Cart::addGlobalCoupon()` or the new `Cart::addCoupon()`:

```php
// Before (deprecated)
Cart::applyCoupon(null, 'PROMO10', 'percentage', 10.0);

// After (legacy API)
Cart::addGlobalCoupon('unique-hash', 'PROMO10', 'percentage', 10.0);

// After (new API — supports expiry, minCartAmount, typed events)
Cart::addCoupon(new CartCoupon(hash: 'unique-hash', code: 'PROMO10', type: 'percentage', value: 10.0, isGlobal: true));
```

**`Cart::applyCoupon($rowId, ...)` is deprecated** for item-level use. Replace with `Cart::addItemCoupon($rowId, ...)`.

**New config keys:** Publish the updated config and add the two new keys, or set defaults in your `config/cart.php`:

```php
'use_legacy_events' => true,
'rounding_mode'     => PHP_ROUND_HALF_UP,
```

**Typed events:** The package now dispatches typed event objects (`CartItemAdded`, `CouponApplied`, etc.) alongside legacy string events. Legacy string events remain active when `use_legacy_events = true` (the default). No immediate action required.

**Migrations:** Run `php artisan vendor:publish --tag=cart-migrations && php artisan migrate` after upgrading. A new `coupons` column (nullable JSON) is added to the cart table to persist cart-level coupons across store/restore cycles.

**Global coupon session key:** Global coupons are now stored under a separate session key (`cart.{instance}_global_coupons`). Existing sessions from v3.x that relied on the legacy `applyCoupon(null, ...)` discountCartItem approach will not carry forward global coupons automatically.

---

## FAQ & Troubleshooting

**My cart is empty after a redirect — why?**

The most common cause is a misconfigured session driver. Verify that `SESSION_DRIVER` in your `.env` is not `array` (which resets on every request). Use `file`, `cookie`, `database`, or `redis` in production. A second cause is calling `Cart::instance('name')` before the redirect but accessing `Cart::instance()` (the default) after it — always use the same instance name across requests.

---

**Two identical products appear as one row — is that a bug?**

No. When two items share the same `id` and the same `options`, they produce the same `rowId` (MD5 of `$id . serialize($options)`). The cart merges them by summing quantities. To force separate rows, pass a differentiating option:

```php
Cart::add(5, 'Shirt', '', 1, 19.67, 24.00, 4.33, '', '', '', ['size' => 'M']);
Cart::add(5, 'Shirt', '', 1, 19.67, 24.00, 4.33, '', '', '', ['size' => 'L']);
// Two separate rows because options differ
```

---

**Prices are not rounding correctly on the receipt.**

All internal calculations use `formatFloat()`, which rounds to 2 decimal places using `number_format($value, 2, '.', '')`. Display formatting is controlled by `config('cart.format.decimals')`, `config('cart.format.decimal_point')`, and `config('cart.format.thousand_separator')`. If your receipt totals do not match, confirm that `price + vat = totalPrice` for each item before adding it to the cart, because the package stores all three values as provided and derives `vatRate` from them.

---

**`Cart::total()` returns `0` even though I added items.**

Two common causes:

1. **Facade not resolving:** Confirm the package is auto-discovered (check `php artisan package:discover`). If you disabled auto-discovery, add `OfflineAgency\LaravelCart\CartServiceProvider` to `config/app.php` providers.

2. **Wrong instance:** If you added items with `Cart::instance('shopping')->add(...)` but call `Cart::total()` without switching back to the same instance, the default instance is empty. Always call `Cart::instance('shopping')->total()`.

---

**Can I use the cart without a database?**

Yes. `Cart::store()` and `Cart::restore()` are optional. The cart runs entirely on the session by default. You only need the migration if you call those two methods.

---

**How do I reset the cart after checkout?**

```php
Cart::destroy();

// Or for a named instance:
Cart::instance('shopping')->destroy();
```

`destroy()` removes all items, global coupons, and cart options for the current instance.

---

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

---

## Security

If you discover any security-related issues, please email <support@offlineagency.com> instead of using the issue tracker.

---

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes, or the [GitHub Releases](https://github.com/offline-agency/laravel-cart/releases) page for full version history.

---

## Credits

- [OFFLINE Agency](https://github.com/offline-agency)

Offline Agency is a web design agency based in Padua, Italy. See [offlineagency.it](https://offlineagency.it/) for an overview of their projects.

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
