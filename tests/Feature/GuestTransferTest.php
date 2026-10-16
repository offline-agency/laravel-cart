<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Contracts\CartStorage;
use OfflineAgency\LaravelCart\Events\GuestCartTransferFailed;
use OfflineAgency\LaravelCart\Exceptions\GuestCartTransferException;
use OfflineAgency\LaravelCart\Http\Middleware\TransferGuestCart;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use OfflineAgency\LaravelCart\Support\Coerce;

beforeEach(function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'cart.storage.driver' => 'database', 'cart.guest_transfer.enabled' => true]);
    $this->artisan('migrate', ['--database' => 'testing']);
    config(['auth.providers.users.driver' => 'database', 'auth.providers.users.table' => 'users']);
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('password')->nullable();
    });
    DB::table('users')->insert(['id' => 42]);
});

it('transfers the pre-login guest cart after actual session regeneration exactly once', function (): void {
    $oldSession = session()->getId();
    $cart = resolveCart();
    $cart->add(id: 1, name: 'Guest', qty: 2, price: 10.0);
    $cart->setMeta('guest', true);
    $cart->addCoupon(new CartCoupon(hash: 'g', code: 'GUEST', type: 'fixed', value: 1.0));
    Route::middleware(['web', TransferGuestCart::class])->get('/cart-login', function () {
        auth()->guard('web')->login(new GenericUser(['id' => 42, 'password' => 'test-hash']));
        request()->session()->regenerate();

        return response('logged in');
    });
    $this->withCookie(Coerce::string(config('session.cookie')), $oldSession)->get('/cart-login')->assertOk();
    expect(session()->getId())->not->toBe($oldSession)
        ->and(auth()->id())->toBe(42)
        ->and(resolveCart()->count())->toBe(2)
        ->and(resolveCart()->total())->toBe(19.0)
        ->and(resolveCart()->getMeta('guest'))->toBeTrue()
        ->and(DB::table('carts')->where('session_id', $oldSession)->exists())->toBeFalse();
    $this->get('/cart-login')->assertOk();
    expect(resolveCart()->count())->toBe(2);
});

it('retains both carts on limits failure and retries only for the bound authenticated owner', function (): void {
    $oldSession = session()->getId();
    $cart = resolveCart();
    $cart->add(id: 1, name: 'Guest', qty: 2, price: 10.0);
    config(['cart.limits.max_qty_per_item' => 1]);
    $failures = [];
    app('events')->listen(GuestCartTransferFailed::class, function ($event) use (&$failures): void {
        $failures[] = $event;
    });
    Route::middleware(['web', TransferGuestCart::class])->get('/cart-login-failure', function () {
        auth()->guard('web')->login(new GenericUser(['id' => 42, 'password' => 'test-hash']));
        request()->session()->regenerate();

        return response('logged in');
    });
    $this->withCookie(Coerce::string(config('session.cookie')), $oldSession)->get('/cart-login-failure')->assertOk();
    expect(auth()->id())->toBe(42)->and($cart->isEmpty())->toBeTrue()
        ->and(DB::table('carts')->whereNotNull('session_id')->count())->toBe(1)
        ->and($failures)->toHaveCount(1);
    config(['cart.limits.max_qty_per_item' => null]);
    $this->get('/cart-login-failure')->assertOk();
    expect($cart->count())->toBe(2)->and(DB::table('carts')->whereNotNull('session_id')->count())->toBe(0);
});

it('retains the trusted retry identity when an outer transaction rolls back transfer', function (): void {
    $cart = resolveCart();
    $guest = session()->getId();
    $cart->add(id: 1, name: 'Guest', qty: 1, price: 10.0);
    $connection = DB::connection('testing');
    $request = Request::create('/login');
    $request->setLaravelSession(app(Session::class));
    $middleware = new TransferGuestCart;
    $connection->beginTransaction();
    $middleware->handle($request, function () {
        auth()->guard('web')->login(new GenericUser(['id' => 42, 'password' => 'test-hash']));
        session()->regenerate();

        return response('logged in');
    });
    $connection->rollBack();
    expect(DB::table('carts')->where('session_id', $guest)->exists())->toBeTrue();
    $middleware->handle($request, fn () => response('retry'));
    expect($cart->count())->toBe(1)->and(DB::table('carts')->where('session_id', $guest)->exists())->toBeFalse();
});

it('leaves guest-only requests alone and honors the disabled default', function (): void {
    $middleware = new TransferGuestCart;
    $request = Request::create('/browse');
    $request->setLaravelSession(app(Session::class));
    $response = response('unchanged');
    expect($middleware->handle($request, fn () => $response))->toBe($response);
    config(['cart.guest_transfer.enabled' => false]);
    expect($middleware->handle($request, fn () => $response))->toBe($response);
    $storage = app(CartStorage::class);
    if (! $storage instanceof DatabaseStorage) {
        throw new RuntimeException('Expected database driver.');
    }
    expect(fn () => $storage->transferGuest('untrusted', ['default']))->toThrow(GuestCartTransferException::class);
});

it('reports invalid transfer configuration explicitly', function (mixed $instances, string $driver): void {
    config(['cart.guest_transfer.instances' => $instances]);
    if ($driver === 'session') {
        app()->instance(CartStorage::class, new SessionStorage(app(SessionManager::class)));
    }
    $request = Request::create('/login');
    $request->setLaravelSession(app(Session::class));
    expect(fn () => (new TransferGuestCart)->handle($request, function () {
        auth()->guard('web')->login(new GenericUser(['id' => 42, 'password' => 'test-hash']));

        return response('logged in');
    }))->toThrow(GuestCartTransferException::class);
})->with([['bad-list', 'database'], [[''], 'database'], [['default'], 'session']]);
