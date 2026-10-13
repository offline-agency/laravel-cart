<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\DatabaseManager;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartItemOptions;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;
use OfflineAgency\LaravelCart\Exceptions\CartStorageException;
use OfflineAgency\LaravelCart\Exceptions\InvalidConfigurationException;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Storage\SessionStorage;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

/**
 * Untyped values entering the cart (configuration, stored data, loose
 * arguments) are validated and narrowed instead of being silently cast.
 */
function typedCovCart(): Cart
{
    $cart = resolveCart();
    $cart->destroy();

    return $cart;
}

function typedCovSession(): SessionManager
{
    return app(SessionManager::class);
}

// ── Configuration ───────────────────────────────────────────────────────────

it('accepts numeric strings for numeric settings', function (): void {
    config(['cart.format.decimals' => '3.0', 'cart.limits.max_items' => '1']);

    $cart = typedCovCart();
    $cart->add('1', 'Item', '', 1, 1.0, 1.2345, 0.2345);

    expect($cart->numberFormat(1.0, null, null, null))->toBe('1.000')
        ->and($cart->total())->toBe(1.235)
        ->and(fn () => $cart->add('2', 'Other', '', 1, 1.0, 1.0, 0.0))->toThrow(CartLimitExceededException::class);
});

it('treats null format settings as the defaults', function (): void {
    config(['cart.format.decimals' => null, 'cart.format.decimal_point' => null, 'cart.format.thousand_separator' => null]);

    expect(typedCovCart()->numberFormat(1234.5, null, null, null))->toBe('1,234.50');
});

it('rejects configuration values of the wrong type', function (string $key, mixed $value, string $message): void {
    config([$key => $value]);

    expect(fn () => typedCovCart()->numberFormat(1.0, null, null, null))
        ->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'non numeric decimals' => ['cart.format.decimals', 'two', "The [cart.format.decimals] configuration value must be a number or null, string 'two' given."],
    'fractional decimals' => ['cart.format.decimals', 2.5, 'The [cart.format.decimals] configuration value must be an integer, float 2.5 given.'],
    'non string separator' => ['cart.format.decimal_point', 5, 'The [cart.format.decimal_point] configuration value must be a string or null, int 5 given.'],
]);

// ── Loose arguments ─────────────────────────────────────────────────────────

it('accepts a null subtitle and null fiscal codes when adding by attributes', function (): void {
    $item = typedCovCart()->add('1', 'Item', null, 1, 10.0, 12.2, 2.2, null, null, null);

    expect($item->subtitle)->toBe('')
        ->and($item->vatFcCode)->toBe('')
        ->and($item->productFcCode)->toBe('')
        ->and($item->urlImg)->toBe('');
});

it('rejects an empty identifier when adding by attributes', function (): void {
    expect(fn () => typedCovCart()->add('', 'Item', '', 1, 10.0))
        ->toThrow(InvalidArgumentException::class, 'Please supply a valid identifier.');
});

it('updates the quantity from a numeric string and rejects other values', function (): void {
    $cart = typedCovCart();
    $item = $cart->add('1', 'Item', '', 1, 10.0, 10.0, 0.0);

    expect($cart->update($item->rowId, '3')?->qty)->toBe(3)
        ->and(fn () => $cart->update($item->rowId, ['qty' => 'three']))->toThrow(UnexpectedValueException::class, 'Expected a number, string given.');
});

it('rejects an attributes array with an invalid identifier', function (): void {
    expect(fn () => CartItem::fromArray(['id' => 1.5, 'name' => 'Item', 'qty' => 1, 'price' => 1.0]))
        ->toThrow(UnexpectedValueException::class, 'Expected an integer or string identifier, float given.');
});

it('updates the options from a CartItemOptions collection and rejects non arrays', function (): void {
    $item = new CartItem(1, 'Item', '', 1, 10.0, 10.0, '', '', 0.0, '', null, null, ['size' => 'M']);

    $item->updateFromArray(['options' => new CartItemOptions(['size' => 'L'])]);

    expect($item->options->all())->toBe(['size' => 'L'])
        ->and($item->options->__get('size'))->toBe('L')
        ->and(fn () => $item->updateFromArray(['options' => 'large']))->toThrow(InvalidArgumentException::class, 'The cart item options must be an array.');
});

it('keys the item coupons by their full code', function (): void {
    $cart = typedCovCart();
    $item = $cart->add('1', 'Item', '', 1, 100.0, 100.0, 0.0);
    $cart->addItemCoupon($item->rowId, 'SUMMER.2026', 'fixed', 5.0);

    expect(array_keys($cart->coupons()))->toBe(['SUMMER.2026'])
        ->and($cart->hasCoupon('SUMMER.2026'))->toBeTrue()
        ->and($cart->getCoupon('SUMMER.2026')?->rowId)->toBe($item->rowId);
});

// ── Stored data ─────────────────────────────────────────────────────────────

it('rejects session content that is not a collection of cart items', function (mixed $content): void {
    typedCovCart();
    typedCovSession()->put('cart.default', $content);

    expect(fn () => resolveCart()->content())
        ->toThrow(CartStorageException::class, 'The cart storage key [cart.default] does not hold a collection of cart items.');
})->with([
    'not a collection' => ['garbage'],
    'not cart items' => [new Collection(['garbage'])],
]);

it('rejects stored cart-level coupons that are not cart coupons', function (mixed $coupons): void {
    typedCovSession()->put('cart.default_global_coupons', $coupons);

    expect(fn () => new Cart(new SessionStorage(typedCovSession()), app('events')))
        ->toThrow(CartStorageException::class, 'The cart storage key [cart.default_global_coupons] does not hold a collection of cart coupons.');
})->with([
    'not a collection' => ['garbage'],
    'not cart coupons' => [new Collection(['garbage'])],
]);

it('reports whether the session storage holds a key', function (): void {
    $storage = new SessionStorage(typedCovSession());
    $storage->put('typed.key', 'value');

    expect($storage->has('typed.key'))->toBeTrue()
        ->and($storage->has('typed.missing'))->toBeFalse();
});

it('decodes stored content holding anything but cart items as undecodable', function (): void {
    expect(CartContentCodec::decode(CartContentCodec::encode(new Collection(['not an item']))))->toBeNull();
});

it('refuses to store database cart content holding anything but cart items', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $storage = new DatabaseStorage(app(DatabaseManager::class));

    expect(fn () => $storage->put('cart.default', new Collection(['not an item'])))
        ->toThrow(CartStorageException::class, 'The cart content to store must only contain cart items.')
        ->and(DB::table('cart_items')->count())->toBe(0);
});

it('rejects stored cart-level coupons that are not coupon arrays', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    DB::table('cart')->insert([
        'identifier' => 'typed',
        'instance' => 'default',
        'content' => CartContentCodec::encode(new Collection),
        'coupons' => '["not a coupon"]',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => typedCovCart()->restore('typed'))
        ->toThrow(UnexpectedValueException::class, 'Expected a stored coupon array, string given.');
});

it('restores the cart options stored by the database driver', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    config(['cart.storage.driver' => 'database']);
    $this->actingAs(new GenericUser(['id' => 5]));

    resolveCart()->setOptions(['gift' => true]);

    // A later request: the storage reads the options back as a decoded array.
    app()->forgetScopedInstances();

    expect(resolveCart()->getOptions())->toBe(['gift' => true])
        ->and(resolveCart()->getOptionsByKey('gift'))->toBeTrue();
});
