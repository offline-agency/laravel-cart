<?php

declare(strict_types=1);

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use OfflineAgency\LaravelCart\Examples\CartActions;

it('uses the authenticated active guard on real stateless requests and ignores browser owner selectors', function (): void {
    require_once __DIR__.'/../../examples/CartActions.php';
    config(['cart.storage.driver' => 'database', 'auth.guards.mobile' => ['driver' => 'recipe-token']]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $auth = app(AuthManager::class);
    $auth->viaRequest('recipe-token', function (Request $request): ?GenericUser {
        return match ($request->bearerToken()) {
            'server-issued-a' => new GenericUser(['id' => 71]),
            'server-issued-b' => new GenericUser(['id' => 72]),
            default => null,
        };
    });
    $cart = resolveCart();
    $actions = new CartActions($cart, function (int $id, string $size): array {
        abort_unless($id === 1 && $size === 'S', 422);

        return ['name' => 'Trusted', 'price' => 10.0, 'gross' => 12.2, 'vat' => 2.2, 'stock' => 5];
    });
    Route::middleware('auth:mobile')->post('/api/recipe/cart', function (Request $request) use ($actions): array {
        $request->validate(['productId' => 'required|integer', 'size' => 'required|string', 'quantity' => 'required|integer|between:1,99']);
        $actions->add($request->integer('productId'), $request->string('size')->toString(), $request->integer('quantity'));

        return $actions->summary();
    });
    Route::middleware('auth:mobile')->get('/api/recipe/cart', fn (): array => $actions->summary());
    $this->getJson('/api/recipe/cart', ['X-Cart-Identifier' => '71'])->assertUnauthorized();
    $auth->forgetGuards(); // New FPM requests / standard worker cleanup do not share a cached RequestGuard user.
    $this->postJson('/api/recipe/cart', ['productId' => 1, 'size' => 'S', 'quantity' => 1, 'price' => 0.01, 'owner' => 72],
        ['Authorization' => 'Bearer server-issued-a', 'X-Cart-Identifier' => '72'])->assertOk()->assertJsonPath('total', 12.2);
    $auth->forgetGuards();
    $this->getJson('/api/recipe/cart', ['Authorization' => 'Bearer server-issued-b', 'X-Cart-Identifier' => '71'])
        ->assertOk()->assertJsonPath('total', 0);
    $auth->forgetGuards();
    $this->getJson('/api/recipe/cart', ['Authorization' => 'Bearer server-issued-a'])
        ->assertOk()->assertJsonPath('total', 12.2);
    expect(app('db')->connection('testing')->table('carts')->where('user_id', 71)->count())->toBe(1)
        ->and(app('db')->connection('testing')->table('carts')->whereNotNull('session_id')->count())->toBe(0);
});
