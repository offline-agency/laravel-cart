<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('cart:list renders the formatted table with items, coupons and dates', function (): void {
    $one = new CartItem(id: '1', name: 'A', subtitle: '', qty: 1, price: 10.0, totalPrice: 10.0, vat: 0.0);
    $two = new CartItem(id: '2', name: 'B', subtitle: '', qty: 1, price: 5.0, totalPrice: 5.0, vat: 0.0);

    DB::connection('testing')->table('cart')->insert([
        [
            'identifier' => 'single-item-user',
            'instance' => 'default',
            'content' => serialize(collect([$one->rowId => $one])),
            'coupons' => json_encode([['code' => 'ONE', 'type' => 'fixed', 'value' => 1]]),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ],
        [
            'identifier' => 'multi-item-user-with-long-identifier',
            'instance' => 'wishlist',
            'content' => serialize(collect([$one->rowId => $one, $two->rowId => $two])),
            'coupons' => json_encode([
                ['code' => 'A', 'type' => 'fixed', 'value' => 1],
                ['code' => 'B', 'type' => 'percentage', 'value' => 10],
            ]),
            'created_at' => null,
            'updated_at' => null,
        ],
        [
            'identifier' => 'empty-user',
            'instance' => 'default',
            'content' => serialize(collect()),
            'coupons' => null,
            'created_at' => now()->subDay()->toDateTimeString(),
            'updated_at' => now()->subDay()->toDateTimeString(),
        ],
    ]);

    $this->artisan('cart:list')
        ->assertExitCode(0);
});

it('cart:list flags carts whose content cannot be deserialized', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupted-user',
        'instance' => 'default',
        'content' => 'not-a-serialized-string',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:list')->assertExitCode(0);

    $this->artisan('cart:list', ['--json' => true])
        ->expectsOutputToContain('"items": null')
        ->assertExitCode(0);
});
