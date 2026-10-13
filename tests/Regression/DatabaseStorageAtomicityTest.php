<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

/**
 * Defect 6: item sync deleted and re-inserted rows outside a transaction and
 * cart creation was a racy select-then-insert.
 */
function regAtomicStorage(): DatabaseStorage
{
    return new DatabaseStorage(app(DatabaseManager::class));
}

function regAtomicItem(string $id): CartItem
{
    return new CartItem(id: $id, name: 'Item '.$id, subtitle: '', qty: 1, price: 2.0, totalPrice: 2.0, vat: 0.0);
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('keeps the previous cart when a write fails midway', function (): void {
    $storage = regAtomicStorage();
    $one = regAtomicItem('1');
    $two = regAtomicItem('2');
    $storage->put('cart.default', new Collection([$one->rowId => $one, $two->rowId => $two]));

    $valid = regAtomicItem('3');
    $broken = regAtomicItem('4');
    $broken->name = null; // violates the NOT NULL constraint on insert

    expect(fn () => $storage->put('cart.default', new Collection([$valid->rowId => $valid, $broken->rowId => $broken])))
        ->toThrow(QueryException::class);

    expect(DB::table('cart_items')->pluck('item_id')->sort()->values()->all())->toBe(['1', '2'])
        ->and(regAtomicStorage()->get('cart.default')->keys()->all())->toEqualCanonicalizing([$one->rowId, $two->rowId])
        ->and($storage->get('cart.default')->keys()->all())->toEqualCanonicalizing([$one->rowId, $two->rowId]);
});

it('tolerates a concurrent request creating the same cart', function (): void {
    $sessionId = session()->getId();
    $raced = false;

    // Simulate another request inserting the cart row right after our existence check.
    DB::listen(function (QueryExecuted $query) use (&$raced, $sessionId): void {
        if ($raced || ! str_starts_with(strtolower($query->sql), 'select') || ! str_contains($query->sql, 'carts')) {
            return;
        }

        $raced = true;
        DB::table('carts')->insert([
            'session_id' => $sessionId,
            'instance' => 'cart.default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $item = regAtomicItem('1');
    regAtomicStorage()->put('cart.default', new Collection([$item->rowId => $item]));

    expect($raced)->toBeTrue()
        ->and(DB::table('carts')->count())->toBe(1)
        ->and(regAtomicStorage()->get('cart.default'))->toHaveCount(1);
});

it('rejects duplicate rows for the same cart line', function (): void {
    $item = regAtomicItem('1');
    regAtomicStorage()->put('cart.default', new Collection([$item->rowId => $item]));

    $row = (array) DB::table('cart_items')->first();
    unset($row['id']);

    expect(fn () => DB::table('cart_items')->insert($row))->toThrow(QueryException::class);
});
