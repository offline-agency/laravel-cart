<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartCoupon;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;

function covDbStorage(): DatabaseStorage
{
    return new DatabaseStorage(app(DatabaseManager::class));
}

/**
 * @param  array<array-key, mixed>  $options
 */
function covDbCartItem(string $id = '1', array $options = []): CartItem
{
    return new CartItem(
        id: $id,
        name: 'Product '.$id,
        subtitle: 'Sub',
        qty: 2,
        price: 10.0,
        totalPrice: 12.2,
        vatFcCode: 'V22',
        productFcCode: 'P01',
        vat: 2.2,
        urlImg: 'https://example.com/img.png',
        options: $options,
    );
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('returns the default when no cart row exists', function (): void {
    $storage = covDbStorage();

    expect($storage->get('default', 'fallback'))->toBe('fallback')
        ->and($storage->has('default'))->toBeFalse();
});

it('persists and hydrates cart content items', function (): void {
    $plain = covDbCartItem('1', ['size' => 'M']);
    $associated = covDbCartItem('2');
    $associated->associate('App\\Models\\Product');
    $associated->appliedCoupons = ['CODE10' => (object) ['couponCode' => 'CODE10', 'couponType' => 'fixed', 'couponValue' => 10.0, 'discountValue' => 10.0]];
    $associated->discountValue = 1.5;

    $content = new Collection([$plain->rowId => $plain, $associated->rowId => $associated]);

    $storage = covDbStorage();
    $storage->put('default', $content);

    // Cached on the same instance.
    expect($storage->get('default'))->toBe($content)
        ->and($storage->has('default'))->toBeTrue();

    $fresh = covDbStorage();
    expect($fresh->has('default'))->toBeTrue();

    $loaded = cartItemsOf($fresh->get('default'));

    expect($loaded)->toBeInstanceOf(Collection::class)
        ->and($loaded)->toHaveCount(2);

    $loadedPlain = $loaded->get($plain->rowId);
    expect($loadedPlain)->toBeInstanceOf(CartItem::class)
        ->and($loadedPlain?->name)->toBe('Product 1')
        ->and($loadedPlain?->subtitle)->toBe('Sub')
        ->and($loadedPlain?->options->toArray())->toBe(['size' => 'M'])
        ->and($loadedPlain?->associatedModel)->toBeNull()
        ->and($loadedPlain?->discountValue)->toBe(0.0);

    $loadedAssociated = $loaded->get($associated->rowId);
    expect($loadedAssociated?->associatedModel)->toBe('App\\Models\\Product')
        ->and($loadedAssociated?->appliedCoupons)->toEqual(['CODE10' => (object) ['couponCode' => 'CODE10', 'couponType' => 'fixed', 'couponValue' => 10.0, 'discountValue' => 10.0]])
        ->and($loadedAssociated?->discountValue)->toBe(1.5);

    expect(DB::table('cart_items')->count())->toBe(2);
});

it('replaces existing cart items when content is put again', function (): void {
    $storage = covDbStorage();
    $first = covDbCartItem('1');
    $storage->put('default', new Collection([$first->rowId => $first]));

    $second = covDbCartItem('2');
    $storage->put('default', new Collection([$second->rowId => $second]));

    expect(DB::table('carts')->count())->toBe(1)
        ->and(DB::table('cart_items')->count())->toBe(1)
        ->and(DB::table('cart_items')->value('item_id'))->toBe('2');
});

it('ignores non collection content values', function (): void {
    $storage = covDbStorage();
    $storage->put('default', 'not-a-collection');

    expect(DB::table('carts')->count())->toBe(1)
        ->and(DB::table('cart_items')->count())->toBe(0)
        ->and(covDbStorage()->get('default'))->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('stores and restores global coupons', function (): void {
    $coupon = new CartCoupon(
        hash: 'hash-1',
        code: 'SAVE10',
        type: 'percentage',
        value: 10.0,
        isGlobal: true,
        expiresAt: Carbon::parse('2099-01-01 00:00:00'),
        usageLimit: 5,
        minCartAmount: 50.0,
    );
    $minimal = new CartCoupon(hash: 'hash-2', code: 'FIX5', type: 'fixed', value: 5.0);

    covDbStorage()->put('default_global_coupons', collect([$coupon, $minimal]));

    $loaded = cartCouponsOf(covDbStorage()->get('default_global_coupons'));

    expect($loaded)->toHaveCount(2)
        ->and($loaded->get('hash-1'))->toBeInstanceOf(CartCoupon::class)
        ->and($loaded->get('hash-1')?->code)->toBe('SAVE10')
        ->and($loaded->get('hash-1')?->isGlobal)->toBeTrue()
        ->and($loaded->get('hash-1')?->expiresAt?->year)->toBe(2099)
        ->and($loaded->get('hash-1')?->usageLimit)->toBe(5)
        ->and($loaded->get('hash-1')?->minCartAmount)->toBe(50.0)
        ->and($loaded->get('hash-2')?->expiresAt)->toBeNull()
        ->and($loaded->get('hash-2')?->minCartAmount)->toBeNull();
});

it('stores null coupons for an empty collection and returns an empty collection', function (): void {
    covDbStorage()->put('default_global_coupons', collect());

    expect(DB::table('carts')->value('coupons'))->toBeNull()
        ->and(covDbStorage()->get('default_global_coupons'))->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('returns an empty collection when stored coupons json is not an array', function (): void {
    covDbStorage()->put('default_cart_info', ['a' => 1]);
    DB::table('carts')->update(['coupons' => '"just-a-string"']);

    expect(covDbStorage()->get('default_global_coupons'))->toBeInstanceOf(Collection::class)->toBeEmpty();
});

it('stores and restores cart info options', function (): void {
    covDbStorage()->put('default_cart_info', ['note' => 'gift']);

    expect(covDbStorage()->get('default_cart_info'))->toBe(['note' => 'gift']);

    covDbStorage()->put('default_cart_info', []);

    expect(DB::table('carts')->value('options'))->toBeNull()
        ->and(covDbStorage()->get('default_cart_info'))->toBe([]);
});

it('stores and restores cart meta', function (): void {
    covDbStorage()->put('default_meta', ['source' => 'web']);

    expect(covDbStorage()->get('default_meta'))->toBe(['source' => 'web']);

    covDbStorage()->put('default_meta', []);

    expect(DB::table('carts')->value('meta'))->toBeNull()
        ->and(covDbStorage()->get('default_meta'))->toBe([]);
});

it('sets expires_at from the ttl config and purges expired carts on read', function (): void {
    config(['cart.ttl' => 10]);
    Carbon::setTestNow('2026-01-01 12:00:00');

    $item = covDbCartItem('1');
    covDbStorage()->put('default', new Collection([$item->rowId => $item]));

    expect(DB::table('carts')->value('expires_at'))->not->toBeNull();

    Carbon::setTestNow('2026-01-01 12:30:00');

    expect(covDbStorage()->get('default', 'expired'))->toBe('expired')
        ->and(DB::table('carts')->count())->toBe(0)
        ->and(DB::table('cart_items')->count())->toBe(0);
});

it('removes the whole cart for content keys', function (): void {
    $storage = covDbStorage();
    $item = covDbCartItem('1');
    $storage->put('default', new Collection([$item->rowId => $item]));

    $storage->remove('default');

    expect(DB::table('carts')->count())->toBe(0)
        ->and(DB::table('cart_items')->count())->toBe(0)
        ->and($storage->has('default'))->toBeFalse();
});

it('only clears coupons when removing the global coupons key', function (): void {
    $storage = covDbStorage();
    $first = covDbCartItem('1');
    $second = covDbCartItem('2');
    $storage->put('default', new Collection([$first->rowId => $first, $second->rowId => $second]));
    $storage->put('default_meta', ['source' => 'web']);
    $storage->put('default_cart_info', ['options' => ['gift' => true]]);
    $storage->put('default_global_coupons', collect([
        new CartCoupon(hash: 'h', code: 'C', type: 'fixed', value: 1.0),
    ]));

    $storage->remove('default_global_coupons');

    expect(DB::table('carts')->count())->toBe(1)
        ->and(DB::table('carts')->value('coupons'))->toBeNull()
        ->and(DB::table('cart_items')->count())->toBe(2);

    // A fresh storage object reads the database, so the instance cache cannot mask a wipe.
    $fresh = covDbStorage();

    expect(cartItemsOf($fresh->get('default'))->keys()->all())->toEqualCanonicalizing([$first->rowId, $second->rowId])
        ->and($fresh->get('default_meta'))->toBe(['source' => 'web'])
        ->and($fresh->get('default_cart_info'))->toBe(['options' => ['gift' => true]])
        ->and($fresh->get('default_global_coupons'))->toBeEmpty();
});

it('does nothing when removing a key without a stored cart', function (): void {
    covDbStorage()->remove('missing');

    expect(DB::table('carts')->count())->toBe(0);
});

it('scopes carts to the authenticated user id', function (): void {
    $this->actingAs(new GenericUser(['id' => 42]));

    covDbStorage()->put('wishlist_meta', ['k' => 'v']);

    $row = DB::table('carts')->first();

    expect($row?->user_id)->toEqual('42')
        ->and($row?->instance)->toBe('wishlist')
        ->and(covDbStorage()->get('wishlist_meta'))->toBe(['k' => 'v']);
});
