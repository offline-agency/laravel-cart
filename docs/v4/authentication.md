# Authentication and guest transfer

Development documentation: see the [capability map](capabilities.md) for released
features and unreleased additions.

Legacy CartMergeMiddleware restores the saved `cart` snapshot; its defaults remain
unchanged. Live database guest transfer is a separate, disabled-by-default feature.
Enable `cart.guest_transfer.enabled` and explicitly allow instance names in
`cart.guest_transfer.instances` (default `['default']`).

In `bootstrap/app.php`, append the following middleware to the **web** group, after
Laravel's StartSession (and before the login route/controller):

```php
->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware) {
    $middleware->web(append: [\OfflineAgency\LaravelCart\Http\Middleware\TransferGuestCart::class]);
})
```

It captures the trusted session id when the request starts as a guest, invokes the
login action, then transfers to the actual authenticated owner after success and
session regeneration. No Login listener is required. Include this middleware on
subsequent web requests to retry a rejected transfer. It never accepts source ids
from browser fields. The pending retry identity is stored server-side and bound to
the authenticated owner; another user's request cannot consume it.

The transfer locks both owners in consistent order, merges all allowlisted instances
in one database transaction and deletes the source only after successful persistence.
Replay is a no-op. Matching row quantities add; option variants remain separate.
Authenticated base prices and row coupons win matching rows; guest-only rows retain
their state. **Revalidate all catalog prices/availability before checkout**, especially
guest-only rows; this package does not trust a stale guest catalog price as checkout authority.
Authenticated options/meta win top-level key conflicts; guest-only keys survive.
Coupons deduplicate by hash or code, authenticated definitions win, then expiry and
minimum rules are revalidated against merged gross. Invalid coupons are removed.

Limits/storage rejection retains both carts. Login continues; GuestCartTransferFailed
contains the user id and an actionable GuestCartTransferException and the exception
is reported via Laravel. Display a retry/revalidation message to the user. The pending
identity is retained until retry succeeds; no unconditional success marker is written.
GuestCartTransferred fires after commit, once when a source actually transferred.
Configuration/programming errors propagate. Do not combine automatic snapshot restore
and live transfer without defining their application order.
