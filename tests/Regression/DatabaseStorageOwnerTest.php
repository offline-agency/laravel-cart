<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Support\Coerce;
use OfflineAgency\LaravelCart\Tests\FeatureTestCase;

/**
 * Defects 3 & 4: the configured owner column was ignored and guest session
 * ids were written into the (bigint) user foreign key column.
 */
function regOwnerStorage(): DatabaseStorage
{
    return new DatabaseStorage(app(DatabaseManager::class));
}

/**
 * @return Collection<string, CartItem>
 */
function regOwnerContent(string $id = '1'): Collection
{
    $item = new CartItem(id: $id, name: 'Owned '.$id, subtitle: '', qty: 1, price: 3.0, totalPrice: 3.0, vat: 0.0);

    return new Collection([$item->rowId => $item]);
}

function regOwnerMigrate(FeatureTestCase $test, ?string $column = null, ?string $type = null): void
{
    if ($column !== null) {
        config(['cart.users.foreign_key' => $column]);
    }
    if ($type !== null) {
        config(['cart.users.foreign_key_type' => $type]);
    }

    $test->artisan('migrate', ['--database' => 'testing']);
}

it('stores a guest cart under the session id, never in the user column', function (): void {
    regOwnerMigrate($this);

    regOwnerStorage()->put('cart.default', regOwnerContent());

    $row = DB::table('carts')->first();

    expect($row?->user_id)->toBeNull()
        ->and($row?->session_id)->toBe(session()->getId())
        ->and(regOwnerStorage()->get('cart.default'))->toHaveCount(1);
});

it('stores an authenticated cart under the user column without a session id', function (): void {
    regOwnerMigrate($this);
    $this->actingAs(new GenericUser(['id' => 42]));

    regOwnerStorage()->put('cart.default', regOwnerContent());

    $row = DB::table('carts')->first();

    expect($row?->user_id)->toEqual(42)
        ->and($row?->session_id)->toBeNull()
        ->and(regOwnerStorage()->get('cart.default'))->toHaveCount(1);
});

it('keeps guest and user carts apart', function (): void {
    regOwnerMigrate($this);

    regOwnerStorage()->put('cart.default', regOwnerContent('guest'));

    $this->actingAs(new GenericUser(['id' => 7]));

    expect(regOwnerStorage()->get('cart.default'))->toBeNull()
        ->and(regOwnerStorage()->has('cart.default'))->toBeFalse();

    regOwnerStorage()->put('cart.default', regOwnerContent('user'));

    expect(DB::table('carts')->count())->toBe(2)
        ->and(cartItemsOf(regOwnerStorage()->get('cart.default'))->first()?->id)->toBe('user');
});

it('uses the configured owner column', function (): void {
    regOwnerMigrate($this, column: 'customer_id');
    $this->actingAs(new GenericUser(['id' => 99]));

    $storage = regOwnerStorage();
    $storage->put('cart.default', regOwnerContent());
    $storage->put('cart.default_meta', ['k' => 'v']);

    $row = DB::table('carts')->first();

    expect($row?->customer_id)->toEqual(99)
        ->and($row !== null && property_exists($row, 'user_id'))->toBeFalse()
        ->and(regOwnerStorage()->get('cart.default'))->toHaveCount(1)
        ->and(regOwnerStorage()->get('cart.default_meta'))->toBe(['k' => 'v']);

    regOwnerStorage()->remove('cart.default');

    expect(DB::table('carts')->count())->toBe(0);
});

it('supports string owner keys', function (string $type, string $id): void {
    regOwnerMigrate($this, type: $type);
    $this->actingAs(new GenericUser(['id' => $id]));

    regOwnerStorage()->put('cart.default', regOwnerContent());

    expect(strtolower(Coerce::string(DB::table('carts')->value('user_id'))))->toBe(strtolower($id))
        ->and(regOwnerStorage()->get('cart.default'))->toHaveCount(1);
})->with([
    'uuid' => ['uuid', (string) Str::uuid()],
    'ulid' => ['ulid', (string) Str::ulid()],
]);

it('enforces one guest cart per session and instance', function (): void {
    regOwnerMigrate($this);

    $row = ['session_id' => 'abc', 'instance' => 'cart.default', 'created_at' => now(), 'updated_at' => now()];
    DB::table('carts')->insert($row);

    expect(fn () => DB::table('carts')->insert($row))->toThrow(QueryException::class);
});
