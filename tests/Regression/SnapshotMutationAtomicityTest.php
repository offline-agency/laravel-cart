<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Events\CartRestored;
use OfflineAgency\LaravelCart\Events\CartStored;
use OfflineAgency\LaravelCart\Storage\SessionStorage;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('rolls back restored session items coupons and metadata when persistence fails', function (): void {
    $cart = resolveCart();
    $cart->add(id: 2, name: 'Saved', qty: 1, price: 20.0);
    $cart->addCoupon(new CartCoupon(hash: 'saved', code: 'SAVED', type: 'fixed', value: 2.0));
    $cart->setMeta('state', 'saved')->store('snapshot');
    $cart->destroy();
    $old = $cart->add(id: 1, name: 'Original', qty: 1, price: 10.0);
    $cart->setMeta('state', 'original');
    $driver = new class(app(SessionManager::class)) extends SessionStorage
    {
        public function put(string $key, mixed $value): void
        {
            parent::put($key, $value);
            if ($key === 'cart.default') {
                throw new RuntimeException('Restore content write failed');
            }
        }
    };
    $failing = new Cart($driver, app('events'));
    $events = [];
    app('events')->listen(CartRestored::class, function () use (&$events): void {
        $events[] = 'restored';
    });
    expect(fn () => $failing->restore('snapshot'))->toThrow(RuntimeException::class);
    expect($cart->total())->toBe(10.0)->and($cart->content()->keys()->all())->toBe([$old->rowId])
        ->and($cart->listCoupons())->toBeEmpty()->and($cart->getMeta('state'))->toBe('original')
        ->and(app(DatabaseManager::class)->connection('testing')->table('cart')->where('identifier', 'snapshot')->exists())->toBeTrue()
        ->and($events)->toBe([]);
});

it('defers snapshot events until outer commit for both live drivers', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $events = [];
    app('events')->listen(CartStored::class, function () use (&$events): void {
        $events[] = 'stored';
    });
    app('events')->listen(CartRestored::class, function () use (&$events): void {
        $events[] = 'restored';
    });
    $connection = app(DatabaseManager::class)->connection('testing');
    $connection->beginTransaction();
    $cart->store('rolled-back');
    expect($events)->toBe([]);
    $connection->rollBack();
    expect($events)->toBe([])->and($connection->table('cart')->where('identifier', 'rolled-back')->exists())->toBeFalse();
    $cart->store('committed');
    expect($events)->toBe(['stored']);
    $connection->beginTransaction();
    $cart->restore('committed');
    expect($events)->toBe(['stored']);
    $connection->commit();
    expect($events)->toBe(['stored', 'restored']);
})->with(['session', 'database']);

it('treats a snapshot removed before the locked read as a no-op', function (): void {
    $cart = resolveCart();
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $cart->store('disappearing');
    $connection = app(DatabaseManager::class)->connection('testing');
    $reads = 0;
    $connection->beforeExecuting(function (string $query) use ($connection, &$reads): void {
        if (str_starts_with(strtolower($query), 'select') && str_contains($query, 'identifier') && ++$reads === 2) {
            $connection->table('cart')->where('identifier', 'disappearing')->delete();
        }
    });
    $events = [];
    app('events')->listen(CartRestored::class, function () use (&$events): void {
        $events[] = 'restored';
    });
    $cart->restore('disappearing');
    expect($cart->total())->toBe(10.0)->and($cart->currentInstance())->toBe('default')->and($events)->toBe([]);
});
