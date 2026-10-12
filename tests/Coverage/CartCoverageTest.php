<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;

beforeEach(function (): void {
    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('meetsMinimum returns true when no minimum total is configured', function (): void {
    config(['cart.limits.min_total' => null]);

    expect($this->cart->meetsMinimum())->toBeTrue();
});

it('meetsMinimum compares the raw total with the configured minimum', function (): void {
    config(['cart.limits.min_total' => 50]);

    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    expect($this->cart->meetsMinimum())->toBeFalse();

    $this->cart->add('2', 'Expensive', '', 1, 100.0, 122.0, 22.0);
    expect($this->cart->meetsMinimum())->toBeTrue();
});

it('overwrites an already stored cart when storing again with overwrite enabled', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('cov-overwrite');

    $this->cart->add('2', 'Other', '', 3, 5.0, 6.1, 1.1);
    $this->cart->store('cov-overwrite');

    expect(DB::table('cart')->where('identifier', 'cov-overwrite')->count())->toBe(1);

    $this->cart->destroy();
    $this->cart->restore('cov-overwrite');

    expect($this->cart->count())->toBe(4);
});

it('restores stored metadata together with the cart content', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->setMeta('note', 'leave at door');
    $this->cart->store('cov-meta');

    $this->cart->destroy();
    $this->cart->forgetMeta('note');
    expect($this->cart->getMeta('note'))->toBeNull();

    $this->cart->restore('cov-meta');

    expect($this->cart->getMeta('note'))->toBe('leave at door')
        ->and($this->cart->count())->toBe(1);
});

it('ignores stored metadata that does not decode to an array', function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('cov-bad-meta');
    DB::table('cart')->where('identifier', 'cov-bad-meta')->update(['meta' => '"just a string"']);

    $this->cart->destroy();
    $this->cart->restore('cov-bad-meta');

    expect($this->cart->meta())->toBe([])
        ->and($this->cart->count())->toBe(1);
});

it('returns the translated tax name', function (): void {
    expect($this->cart->taxName())->toBe(__('laravel-cart::cart.tax_name'));
});

it('serializes the cart to an array and to json', function (): void {
    $this->cart->add('1', 'Product', '', 2, 10.0, 12.2, 2.2);
    $this->cart->setMeta('channel', 'web');

    $array = $this->cart->toArray();

    expect($array)->toHaveKeys([
        'instance', 'items', 'count', 'uniqueCount', 'subtotal', 'vat',
        'total', 'discount', 'coupons', 'meta', 'isEmpty',
    ])
        ->and($array['count'])->toBe(2)
        ->and($array['uniqueCount'])->toBe(1)
        ->and($array['items'])->toHaveCount(1)
        ->and($array['meta'])->toBe(['channel' => 'web'])
        ->and($array['isEmpty'])->toBeFalse();

    expect(json_decode($this->cart->toJson(), true))->toBe(json_decode((string) json_encode($array), true));
});

it('sums an attribute across items', function (): void {
    $this->cart->add('1', 'A', '', 2, 10.0, 12.2, 2.2);
    $this->cart->add('2', 'B', '', 3, 5.0, 6.1, 1.1);

    expect($this->cart->sum('qty'))->toBe(5.0);
});

it('calculates total weight from item options', function (): void {
    $this->cart->add('1', 'A', '', 2, 10.0, 12.2, 2.2, '', '', '', null, null, ['weight' => 1.5]);
    $this->cart->add('2', 'B', '', 1, 5.0, 6.1, 1.1);

    expect($this->cart->weight())->toBe(3.0);
});

it('returns a timestamped snapshot of the cart', function (): void {
    $this->cart->add('1', 'A', '', 1, 10.0, 12.2, 2.2);

    $snapshot = $this->cart->snapshot();

    expect($snapshot)->toHaveKey('timestamp')
        ->and($snapshot['timestamp'])->toBeString()
        ->and($snapshot['count'])->toBe(1);
});

it('sets, gets and forgets metadata', function (): void {
    $result = $this->cart->setMeta('gift', ['wrap' => true]);

    expect($result)->toBe($this->cart)
        ->and($this->cart->getMeta('gift.wrap'))->toBeTrue()
        ->and($this->cart->getMeta('missing', 'fallback'))->toBe('fallback');

    expect($this->cart->forgetMeta('gift'))->toBe($this->cart)
        ->and($this->cart->getMeta('gift'))->toBeNull()
        ->and($this->cart->meta())->toBe([]);
});

it('reports no meta column when the schema cannot be inspected', function (): void {
    config(['cart.database.connection' => 'cov-missing-connection']);

    $method = new ReflectionMethod(Cart::class, 'hasMetaColumn');

    expect($method->invoke($this->cart))->toBeFalse();
});

it('throws when an item exceeds the maximum quantity per item', function (): void {
    config(['cart.limits.max_qty_per_item' => 2]);

    $this->cart->add('1', 'A', '', 3, 10.0, 12.2, 2.2);
})->throws(CartLimitExceededException::class);

it('throws when the cart exceeds the maximum number of items', function (): void {
    config(['cart.limits.max_items' => 1]);

    $this->cart->add('1', 'A', '', 1, 10.0, 12.2, 2.2);

    try {
        $this->cart->add('2', 'B', '', 1, 10.0, 12.2, 2.2);
        $this->fail('Expected CartLimitExceededException');
    } catch (CartLimitExceededException $e) {
        expect($e->limit)->toBe(1)
            ->and($e->attempted)->toBe(2);
    }
});

it('throws when the cart exceeds the maximum total', function (): void {
    config(['cart.limits.max_total' => 20]);

    $this->cart->add('1', 'A', '', 1, 10.0, 12.2, 2.2);

    try {
        $this->cart->add('2', 'B', '', 1, 10.0, 12.2, 2.2);
        $this->fail('Expected CartLimitExceededException');
    } catch (CartLimitExceededException $e) {
        expect($e->limit)->toBe(20.0)
            ->and($e->attempted)->toBeGreaterThan(20.0);
    }
});

it('allows adding items within the configured maximum total', function (): void {
    config(['cart.limits.max_total' => 1000]);

    $this->cart->add('1', 'A', '', 2, 10.0, 12.2, 2.2);

    expect($this->cart->count())->toBe(2);
});
