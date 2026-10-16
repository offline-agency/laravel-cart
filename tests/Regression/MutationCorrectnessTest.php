<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Session\SessionManager;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\Events\CartItemAdded;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Exceptions\CouponAlreadyAppliedException;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use OfflineAgency\LaravelCart\Storage\StagedStorage;
use OfflineAgency\LaravelCart\Tests\Fixtures\BuyableProduct;
use OfflineAgency\LaravelCart\Tests\Fixtures\LegacyStorage;

function mutationCart(string $driver): Cart
{
    $storage = $driver === 'database'
        ? new DatabaseStorage(app(DatabaseManager::class))
        : new SessionStorage(app('session'));

    return new Cart($storage, app('events'));
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('recalculates net repricing at the retained rate across reload', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 2, price: 10.00, totalPrice: 12.20, vat: 2.20);
    $cart->update($item->rowId, ['price' => 20.00]);
    $reloaded = mutationCart($driver);
    $updated = $reloaded->get($item->rowId);
    expect($updated->originalPrice)->toBe(20.0)
        ->and($updated->originalVat)->toBe(4.4)
        ->and($updated->originalTotalPrice)->toBe(24.4)
        ->and($updated->subtotal)->toBe(40.0)
        ->and($updated->total)->toBe(48.8)
        ->and($reloaded->subtotal())->toBe(40.0)
        ->and($reloaded->vat())->toBe(8.8)
        ->and($reloaded->total())->toBe(48.8);
})->with(['session', 'database']);

it('reapplies a row coupon after repricing and restores the new base on detach', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);
    $cart->addItemCoupon($item->rowId, 'TEN', 'percentage', 10.0);
    $cart->update($item->rowId, ['price' => 20.0]);
    expect($cart->total())->toBe(46.36)
        ->and($cart->get($item->rowId)->total)->toBe(46.36);
    $cart->detachCoupon($item->rowId, 'TEN');
    expect(mutationCart($driver)->total())->toBe(48.8);
})->with(['session', 'database']);

it('rejects an entire two item batch without successful events', function (string $driver): void {
    $cart = mutationCart($driver);
    config(['cart.limits.max_items' => 1]);
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events): void {
        $events[] = 'added';
    });
    expect(fn () => $cart->addBatch([
        ['id' => 1, 'name' => 'One', 'qty' => 1, 'price' => 10.0],
        ['id' => 2, 'name' => 'Two', 'qty' => 1, 'price' => 10.0],
    ]))->toThrow(CartLimitExceededException::class);
    expect($cart->count())->toBe(0)->and(mutationCart($driver)->count())->toBe(0)->and($events)->toBe([]);
})->with(['session', 'database']);

it('preserves removals when sync later fails', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Original', qty: 1, price: 10.0);
    config(['cart.limits.max_qty_per_item' => 1]);
    expect(fn () => $cart->sync([['id' => 2, 'name' => 'Replacement', 'qty' => 2, 'price' => 20.0]]))->toThrow(CartLimitExceededException::class);
    expect($cart->content()->keys()->all())->toBe([$item->rowId]);
})->with(['session', 'database']);

it('removes the old row when options change alongside zero quantity', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0);
    $cart->update($item->rowId, ['qty' => 0, 'options' => ['size' => 'L']]);
    expect($cart->isEmpty())->toBeTrue();
})->with(['session', 'database']);

it('uses configured precision for cart coupon discounts', function (): void {
    config(['cart.format.decimals' => 3]);
    $cart = mutationCart('session');
    $cart->add(id: 1, name: 'Precision', qty: 1, price: 1.235, vat: 0.0);
    $cart->addCoupon(new CartCoupon(hash: 'ten', code: 'TEN', type: 'percentage', value: 10.0));
    expect($cart->globalCouponDiscount('1.235'))->toBe('0.124')->and($cart->total())->toBe(1.111);
});

it('declares its runtime decimal extension', function (): void {
    $manifest = decodeJson(file_get_contents(__DIR__.'/../../composer.json'));
    expect($manifest['require'])->toHaveKey('ext-bcmath', '*');
});

it('preserves option variants in sync and rejects duplicate row identities', function (string $driver): void {
    $cart = mutationCart($driver);
    $rows = [
        ['id' => 1, 'name' => 'Widget', 'qty' => 1, 'price' => 10.0, 'options' => ['size' => 'S']],
        ['id' => 1, 'name' => 'Widget', 'qty' => 2, 'price' => 10.0, 'options' => ['size' => 'L']],
    ];
    $cart->sync($rows);
    expect($cart->uniqueCount())->toBe(2)->and($cart->count())->toBe(3);
    expect(fn () => $cart->sync([$rows[0], $rows[0]]))->toThrow(InvalidArgumentException::class);
    expect($cart->uniqueCount())->toBe(2);
})->with(['session', 'database']);

it('rejects repricing above limits without changing a held reference', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0, totalPrice: 12.2, vat: 2.2);
    config(['cart.limits.max_total' => 15]);
    expect(fn () => $cart->update($item->rowId, ['price' => 20.0]))->toThrow(CartLimitExceededException::class);
    expect($item->price)->toBe(10.0)->and($cart->total())->toBe(12.2)->and(mutationCart($driver)->total())->toBe(12.2);
})->with(['session', 'database']);

it('defers successful events to the outer commit and discards events on rollback', function (): void {
    $cart = mutationCart('database');
    $connection = app(DatabaseManager::class)->connection('testing');
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events): void {
        $events[] = 'added';
    });
    $connection->beginTransaction();
    $cart->add(id: 1, name: 'Rolled back', qty: 1, price: 10.0);
    expect($events)->toBe([]);
    $connection->rollBack();
    expect($cart->count())->toBe(0)->and($events)->toBe([]);
    $connection->beginTransaction();
    $cart->add(id: 2, name: 'Committed', qty: 1, price: 10.0);
    expect($events)->toBe([]);
    $connection->commit();
    expect($events)->toBe(['added']);
});

it('does not reuse coupons across an owner switch or TTL expiry on one Cart', function (): void {
    config(['cart.ttl' => 1]);
    $cart = mutationCart('database');
    $cart->add(id: 1, name: 'Guest', qty: 1, price: 10.0);
    $cart->addCoupon(new CartCoupon(hash: 'guest', code: 'GUEST', type: 'fixed', value: 1.0));
    $this->actingAs(new GenericUser(['id' => 7]));
    expect($cart->isEmpty())->toBeTrue()->and($cart->listCoupons())->toBeEmpty();
    $cart->add(id: 2, name: 'User', qty: 1, price: 20.0);
    $cart->addCoupon(new CartCoupon(hash: 'user', code: 'USER', type: 'fixed', value: 2.0));
    $this->travel(2)->minutes();
    expect($cart->isEmpty())->toBeTrue()->and($cart->listCoupons())->toBeEmpty()->and($cart->total())->toBe(0.0);
});

it('keeps persisted state and events when an insert fails after deletion', function (): void {
    $cart = mutationCart('database');
    $item = $cart->add(id: 1, name: 'Original', qty: 1, price: 10.0);
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events): void {
        $events[] = 'added';
    });
    app(DatabaseManager::class)->connection('testing')->beforeExecuting(function (string $query): void {
        if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'cart_items')) {
            throw new RuntimeException('Simulated insert failure after deletion');
        }
    });
    expect(fn () => $cart->sync([['id' => 2, 'name' => 'Replacement', 'qty' => 1, 'price' => 20.0]]))->toThrow(RuntimeException::class);
    expect($cart->content()->keys()->all())->toBe([$item->rowId])->and($item->price)->toBe(10.0)->and($events)->toBe([]);
});

it('recalculates Buyable base amounts and retains the row discount', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);
    $cart->addItemCoupon($item->rowId, 'FIXED', 'fixed', 2.44);
    $cart->update($item->rowId, new BuyableProduct(price: 20.0, totalPrice: 24.4, vat: 4.4));
    expect($cart->total())->toBe(46.36)->and($cart->subtotal())->toBe(38.0)->and($cart->vat())->toBe(8.36);
    $cart->detachCoupon($item->rowId, 'FIXED');
    expect($cart->total())->toBe(48.8);
})->with(['session', 'database']);

it('keeps a discounted row economically coherent when more units are added', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0, totalPrice: 12.2, vat: 2.2);
    $cart->addItemCoupon($item->rowId, 'TEN', 'percentage', 10.0);
    expect($cart->get($item->rowId)->priceTax)->toBe(10.98);
    $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0, totalPrice: 12.2, vat: 2.2);
    expect($cart->total())->toBe(23.18)->and($cart->get($item->rowId)->hasCoupons())->toBeTrue();
})->with(['session', 'database']);

it('keeps original references unchanged until an outer transaction commits', function (): void {
    $cart = mutationCart('database');
    $item = $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0);
    $connection = app(DatabaseManager::class)->connection('testing');
    $connection->beginTransaction();
    $cart->update($item->rowId, ['price' => 20.0]);
    expect($item->price)->toBe(10.0);
    $connection->rollBack();
    expect($item->price)->toBe(10.0)->and($cart->total())->toBe(10.0);
});

it('retains the tax basis through zero-price repricing', function (string $driver, string $behavior, float $input): void {
    config(['cart.tax.default_rate' => 22, 'cart.tax.tax_behavior' => $behavior]);
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 2, price: $input);
    $cart->update($item->rowId, ['price' => 0.0]);
    expect($cart->total())->toBe(0.0)->and($cart->get($item->rowId)->vatRate)->toEqual(22);
    $cart = mutationCart($driver);
    $cart->update($item->rowId, ['price' => 20.0]);
    expect($cart->total())->toBe(48.8)->and($cart->vat())->toBe(8.8);
})->with(['session', 'database'])->with([['inclusive', 12.2], ['exclusive', 10.0]]);

it('rejects non-finite amounts and contradictory repricing without mutation', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'Widget', qty: 1, price: 10.0);
    expect(fn () => $cart->update($item->rowId, ['price' => 20.0, 'vat' => 4.4, 'totalPrice' => 20.0]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $cart->add(id: 2, name: 'Infinity', qty: 1, price: INF))->toThrow(InvalidArgumentException::class);
    expect(fn () => $cart->update($item->rowId, INF))->toThrow(UnexpectedValueException::class);
    expect($cart->total())->toBe(10.0);
})->with(['session', 'database']);

it('rejects malformed cart coupon domains', function (string $type, float $value): void {
    expect(fn () => new CartCoupon(hash: 'bad', code: 'BAD', type: $type, value: $value))->toThrow(InvalidArgumentException::class);
})->with([['unknown', 1.0], ['fixed', INF], ['fixed', NAN], ['fixed', -1.0], ['percentage', 101.0]]);

it('updates held row references after identity changes and database multi-add', function (string $driver): void {
    $cart = mutationCart($driver);
    $items = $cart->add([
        ['id' => 1, 'name' => 'One', 'qty' => 1, 'price' => 10.0],
        ['id' => 2, 'name' => 'Two', 'qty' => 1, 'price' => 20.0],
    ]);
    if (! is_array($items)) {
        throw new RuntimeException('Multi-add must return its items.');
    }
    $item = $items[0];
    $updated = $cart->update($item->rowId, ['qty' => 2, 'options' => ['size' => 'L']]);
    expect($item->qty)->toBe(2)->and($item->rowId)->toBe($updated?->rowId)
        ->and($item->options->get('size'))->toBe('L');
    $cart->update($item->rowId, 3);
    expect($item->qty)->toBe(3);
})->with(['session', 'database']);

it('reconciles outer-commit references for the captured instance', function (): void {
    $cart = mutationCart('database');
    $item = $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $connection = app(DatabaseManager::class)->connection('testing');
    $connection->beginTransaction();
    $cart->update($item->rowId, 2);
    $cart->instance('wishlist');
    $connection->commit();
    expect($item->qty)->toBe(2);
});

it('keeps the legacy custom-driver interface usable with deferred success events', function (): void {
    $driver = new LegacyStorage(new SessionStorage(app(SessionManager::class)));
    $cart = new Cart($driver, app('events'));
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events, $driver): void {
        $events[] = $driver->has('cart.default');
    });
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    expect($events)->toBe([true]);
    $staged = new StagedStorage($driver);
    expect($staged->has('cart.default'))->toBeTrue();
    $cart->destroy();
    expect($driver->has('cart.default'))->toBeFalse();
});

it('rejects invalid row coupon amounts while retaining the original cart', function (): void {
    $cart = mutationCart('session');
    $item = $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    expect(fn () => $cart->addItemCoupon($item->rowId, 'BAD', 'fixed', INF))->toThrow(InvalidArgumentException::class);
    expect($cart->total())->toBe(10.0)->and($cart->get($item->rowId)->hasCoupons())->toBeFalse();
});

it('retries a concurrency failure from fresh persisted state without duplicate events', function (): void {
    $cart = mutationCart('database');
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $connection = app(DatabaseManager::class)->connection('testing');
    $attempts = 0;
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events): void {
        $events[] = 'added';
    });
    $connection->beforeExecuting(function (string $query) use (&$attempts): void {
        if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'cart_items') && ++$attempts === 1) {
            throw new PDOException('deadlock detected');
        }
    });
    $winnerWritten = false;
    app('events')->listen(TransactionRolledBack::class, function () use ($connection, &$winnerWritten): void {
        if (! $winnerWritten && $connection->transactionLevel() === 0) {
            $winnerWritten = true;
            $connection->table('cart_items')->update(['qty' => 2]);
        }
    });
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    expect($attempts)->toBe(2)->and($winnerWritten)->toBeTrue()->and($cart->count())->toBe(3)->and($events)->toBe(['added']);
});

it('bounds concurrency retries and preserves the original cart on exhaustion', function (): void {
    $cart = mutationCart('database');
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $attempts = 0;
    app(DatabaseManager::class)->connection('testing')->beforeExecuting(function (string $query) use (&$attempts): void {
        if (str_starts_with(strtolower($query), 'insert into') && str_contains($query, 'cart_items')) {
            $attempts++;
            throw new PDOException('deadlock detected');
        }
    });
    expect(fn () => $cart->add(id: 2, name: 'Two', qty: 1, price: 20.0))->toThrow(DeadlockException::class);
    expect($attempts)->toBe(3)->and($cart->count())->toBe(1)->and($cart->total())->toBe(10.0);
});

it('rolls back a session write failure without leaking changes or successful events', function (): void {
    $cart = mutationCart('session');
    $item = $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $failing = new class(app(SessionManager::class)) extends SessionStorage
    {
        public function put(string $key, mixed $value): void
        {
            parent::put($key, $value);
            throw new RuntimeException('Session storage failure after write');
        }
    };
    $cart = new Cart($failing, app('events'));
    $events = [];
    app('events')->listen(CartItemAdded::class, function () use (&$events): void {
        $events[] = 'added';
    });
    expect(fn () => $cart->add(id: 2, name: 'Two', qty: 1, price: 20.0))->toThrow(RuntimeException::class);
    expect($cart->count())->toBe(1)->and($item->price)->toBe(10.0)->and($events)->toBe([]);
});

it('validates sync limits against the final candidate rather than intermediate quantities', function (string $driver): void {
    $cart = mutationCart($driver);
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $cart->add(id: 2, name: 'Two', qty: 2, price: 10.0);
    config(['cart.limits.max_total' => 30]);
    $cart->sync([
        ['id' => 1, 'name' => 'One', 'qty' => 3, 'price' => 10.0],
        ['id' => 2, 'name' => 'Two', 'qty' => 0, 'price' => 10.0],
    ]);
    expect($cart->count())->toBe(3)->and($cart->uniqueCount())->toBe(1)->and($cart->total())->toBe(30.0);
})->with(['session', 'database']);

it('retains legacy global discount rows in coupon summaries', function (): void {
    $cart = mutationCart('session');
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $cart->addItemCoupon(null, 'LEGACY', 'fixed', 2.0);
    expect($cart->total())->toBe(8.0)->and($cart->getCoupons()->first()?->value)->toBe(2.0);
});

it('rejects attaching the same row coupon twice without orphaning a discount', function (string $driver): void {
    $cart = mutationCart($driver);
    $item = $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $cart->addItemCoupon($item->rowId, 'ONE', 'fixed', 1.0);
    expect(fn () => $cart->addItemCoupon($item->rowId, 'ONE', 'fixed', 1.0))->toThrow(CouponAlreadyAppliedException::class);
    $cart->detachCoupon($item->rowId, 'ONE');
    expect($cart->total())->toBe(10.0);
})->with(['session', 'database']);

it('removes a zero-quantity source without overwriting an existing target variant', function (string $driver): void {
    $cart = mutationCart($driver);
    $source = $cart->add(id: 1, name: 'Small', qty: 1, price: 10.0, options: ['size' => 'S']);
    $target = $cart->add(id: 1, name: 'Large', qty: 2, price: 20.0, options: ['size' => 'L']);
    $cart->update($source->rowId, ['qty' => 0, 'options' => ['size' => 'L']]);
    expect($cart->uniqueCount())->toBe(1)->and($cart->get($target->rowId)->qty)->toBe(2)
        ->and($cart->total())->toBe(40.0)->and($cart->get($target->rowId)->name)->toBe('Large');
})->with(['session', 'database']);

it('accepts finite scientific-notation coupon values without passing exponent strings to BCMath', function (string $type, float $value, string $expected): void {
    $cart = mutationCart('session');
    $cart->add(id: 1, name: 'One', qty: 1, price: 10.0);
    $cart->addCoupon(new CartCoupon(hash: 'finite', code: 'FINITE', type: $type, value: $value));
    expect($cart->globalCouponDiscount('10'))->toBe($expected);
})->with([['fixed', 1.0e20, '10.00'], ['fixed', 1.0e-7, '0.00'], ['percentage', 1.0e-7, '0.00']]);

it('retains a tiny percentage coefficient when its discount is economically significant', function (): void {
    $cart = mutationCart('session');
    $cart->add(id: 1, name: 'Large order', qty: 1, price: 1000000000.0);
    $cart->addCoupon(new CartCoupon(hash: 'tiny', code: 'TINY', type: 'percentage', value: 1.0e-7));
    expect($cart->globalCouponDiscount('1000000000'))->toBe('1.00');
});
