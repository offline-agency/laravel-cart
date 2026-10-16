# Redis-backed Laravel sessions

Unreleased recipe; check [release availability](../capabilities.md).

This uses the shipped **SessionStorage** through Laravel sessions. There is no
shipped dedicated Redis cart driver, per-cart Redis key TTL, CAS or Lua mutation API.
See the [dedicated Redis proposal](../../design/redis-cart-storage.md) for that scope.

Illustrative application `.env`, using an installed PHP Redis client and a working
Laravel Redis connection (no Redis client becomes this package's runtime dependency):

```dotenv
CART_STORAGE_DRIVER=session
SESSION_DRIVER=redis
SESSION_CONNECTION=default
SESSION_LIFETIME=120
SESSION_BLOCK_STORE=redis
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Use your application's Redis password/TLS/database settings and session cookie
configuration. Laravel's session config reads SESSION_CONNECTION. Explicitly add the blocking
store to your application's config/session.php if it is absent:

```php
'block_store' => env('SESSION_BLOCK_STORE', 'redis'),
```

The cache stores must include redis. Confirm the consuming app's
configuration after config caching. Session expiry controls the entire session;
`cart.ttl` applies to database live carts, not an independent session-cart expiry.

Place mutations in the web middleware group with StartSession/CSRF protection and
block **every** competing session route that writes the cart:

```php
Route::post('/cart/items', [CartController::class, 'store'])->block(10, 10);
Route::patch('/cart/items/{rowId}', [CartController::class, 'update'])->block(10, 10);
Route::delete('/cart/items/{rowId}', [CartController::class, 'destroy'])->block(10, 10);
```

Handle Laravel LockTimeoutException as a retryable request failure. Blocking requires
an atomic-lock-capable store; it serializes the session request lifecycle, rather
than just the cart method. Also block login/other routes writing the same session.
The staged session driver alone cannot serialize concurrent HTTP session writes.

This configuration is based on [Laravel's session documentation](https://laravel.com/docs/13.x/session#session-blocking).
[RedisSessionExample.php](../../../examples/RedisSessionExample.php) was executed
in the archived-package consumer with Laravel 13.36.0, PHP 8.5.10/phpredis and
disposable Redis 8.10.2: a new session manager reloaded the cart and a second lock
was excluded until release. Full HTTP route blocking remains illustrative.

Repeat with your disposable local Redis port:

```sh
CART_REDIS_RECIPE_PORT=18473 composer consumer:check
```

The optional probe creates/removes only its own random session and lock keys; no
application Redis database should be used. Normal CI consumer checks do not require Redis.
