# Adoption recipes (unreleased source)

These recipes target the current development checkout based on released 4.1.0.
The new recipe sources and allocation API are unreleased additions. See the
[capability map](../capabilities.md) before selecting a released package version.

- [Variants, wishlist and models](variants-wishlist.md)
- [Livewire component](livewire.md)
- [Inertia with Vue](inertia-vue.md)
- [Server coupon / checkout validation](coupons-checkout.md)
- [Authenticated API/mobile](api-mobile.md)
- [Redis-backed Laravel sessions](redis-sessions.md)

## Shared application service

Copy [CartActions.php](../../../examples/CartActions.php) to your application's
support namespace and adapt imports. It is an executable example, not an autoloaded
package runtime service. Inject Cart and a trusted catalog closure returning
`array{name: string, price: float, gross: float, vat: float, stock: int}` for a
validated integer product ID and size. Throw Laravel ValidationException for an
unknown, unauthorized, disabled or unavailable catalog selection. Load these values
from your catalog/database, never request price, tax, stock or owner fields.

The service validates quantity 1–99, checks aggregate variant availability, and
resolves row IDs only within the active owner's cart. `add()` accumulates quantities
and retains existing row prices/coupons; `update()` explicitly refreshes trusted
catalog amounts. Named instances are restored in finally, even after errors.

<!-- example:cart-add:start -->

```php
// Catalog values are trusted server-side; clients supply only product/size/quantity.
return $this->cart->add(id: $productId, name: $product['name'], qty: $quantity,
    price: $product['price'], totalPrice: $product['gross'], vat: $product['vat'], options: ['size' => $size]);
```

<!-- example:cart-add:end -->

[AdoptionRecipesTest.php](../../../tests/Feature/AdoptionRecipesTest.php) executes
the real service with a deterministic server catalog on session/database storage,
including owner isolation, invalid variants, stock, updates and removals. Vue,
Livewire and HTTP routing fragments below are **illustrative application
integration**, not installed/tested frontend stacks. The Redis page separately
identifies its executed session/lock probe and unexecuted HTTP layer.
Revalidate catalog prices/availability at checkout under your application's
inventory/order transaction; cart availability checks do not reserve stock.
