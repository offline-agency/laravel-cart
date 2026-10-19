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
and foreign-row rejection. Token issuance/guard middleware is an application concern
and is not integration-tested here. Revalidate trusted catalog prices/availability
before checkout and use the application's authorization/inventory transaction.
