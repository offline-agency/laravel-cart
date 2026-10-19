# Variants, wishlist and associated models

Unreleased recipe; check [release availability](../capabilities.md).

Use the [shared tested service](index.md). Two calls with product 1, sizes S and L
produce different row IDs through `options: ['size' => ...]`. Rows match by id and
options, not product id alone. Call `$actions->add(1, 'S', 2, wishlist: true)` to
use the fixed `wishlist` instance; browser input never chooses arbitrary instances.

`moveWishlistToCart($rowId, $quantity)` validates the source quantity, adds exactly
that quantity to `default`, then reduces/removes the wishlist source. Matching
cart rows accumulate quantities and retain their current prices/coupons; distinct
variants remain separate. New rows use trusted catalog amounts. All rows need
checkout revalidation. A rejected target add leaves the wishlist unchanged.

<!-- example:wishlist-move:start -->

```php
// Two operations: target succeeds before source is reduced. Not cross-instance atomic.
$this->add($productId, $size, $quantity);
$this->cart->update($source->rowId, ['qty' => $source->qty - $quantity]);
```

<!-- example:wishlist-move:end -->

This recipe is two operations, **not cross-instance atomic or inherently idempotent**.
If reducing the source fails after target persistence, retain/report that failure
and reconcile before retrying; blind retry can double quantities. A production
all-or-nothing move needs its own owner-aware transaction/idempotency design.

Model association is optional and does not define variant identity. Illustrative:

```php
$cart->associate($rowId, App\Models\Product::class);
$product = $cart->get($rowId)->model;
```

The associated class must be an application model with a compatible find() method.
Authorization, catalog visibility and availability still belong to the application;
association is not permission to expose arbitrary models. Existing package model
association tests are separate from the adoption service's catalog fixture.
