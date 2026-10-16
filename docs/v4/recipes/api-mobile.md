# Authenticated API / mobile

Unreleased recipe; check [release availability](../capabilities.md).

Configure the `database` driver and publish/migrate `cart-database-migrations` on
the configured connection. Owners come from `auth()->id()`, using Laravel's active
default guard for the request. The package has no separate configurable guard.
Your verified token middleware must authenticate the real user and make that identity
visible through auth()->id(); for example use an application-installed Sanctum setup
with auth:sanctum. Never accept owner IDs or guest session IDs from a request body.
Guest login transfer needs the stateful web session lifecycle; a stateless token
request does not reproduce session regeneration.

Illustrative application endpoint, with imports for Request, CartActions and
CartException/ValidationException as in the [Inertia recipe](inertia-vue.md):

```php
Route::middleware('auth:sanctum')->post('/cart/items', function (Request $request, CartActions $actions) {
    $data = $request->validate(['productId' => 'required|integer|min:1',
        'size' => 'required|string|max:30', 'quantity' => 'required|integer|between:1,99']);
    try { $actions->add((int) $data['productId'], $data['size'], (int) $data['quantity']); }
    catch (CartException $error) { throw ValidationException::withMessages(['cart' => $error->getMessage()]); }
    return response()->json(['data' => $actions->summary()]);
});
```

Use the same validated update/remove actions for PATCH/DELETE and JSON 422 errors;
CartActions resolves only current-owner rows. Its summary uses CartResource. Expose
post-discount tax only through an explicit additional field, handling
TaxAllocationException for unsupported legacy adjustment carts. Protect all cart,
snapshot and checkout endpoints; a row ID is not an ownership credential.

[AdoptionRecipesTest.php](../../../tests/Feature/AdoptionRecipesTest.php) exercises
the service with two real auth identities and database storage, asserting isolation
and foreign-row rejection. Sanctum token issuance and its own middleware setup are application concerns
and were not integration-tested here; the active-guard boundary is tested below. Revalidate trusted catalog prices/availability
before checkout and use the application's authorization/inventory transaction.

## Verified ownership boundary

DatabaseStorage resolves auth()->id() before falling back to a session guest key.
Laravel's Authenticate middleware calls shouldUse() for the successful guard; this
is why auth:sanctum must be an actual authenticated middleware boundary, not just
a token-shaped header. Sanctum must be installed/configured by the consuming app;
this package adds no guard option or token issuer. Authenticated stateless calls
need no browser session to select the user-owned database cart.

[StatelessApiOwnershipTest.php](../../../tests/Feature/StatelessApiOwnershipTest.php)
executes real JSON requests through auth:mobile with a Laravel RequestGuard fixture,
including 401, two token identities, no cookies and ignored request owner/price and
X-Cart-Identifier values. The guard cache is reset between simulated requests, as
with separate FPM requests or standard worker cleanup. It verifies active-guard
ownership, not Sanctum token issuance.

Unauthenticated stateless guests have no supported stable headless cart identifier:
X-Cart-Identifier is not consumed or authorized by this package. Keep API routes
authenticated; do not map arbitrary header values into session/user owner columns.
A possible [verified guest-token extension](../../proposals/redis-cart-storage.md#guest-owner-extension-proposal)
is a separate proposal, not a shipped authentication subsystem.

Use the [executed server coupon/checkout recipe](coupons-checkout.md) to validate
authoritative promotions and catalog state; actual reservations and payment remain
application-owned.
