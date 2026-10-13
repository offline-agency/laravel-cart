<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('cart:stats renders the formatted statistics for stored carts', function (): void {
    $item = new CartItem(id: '1', name: 'A', subtitle: '', qty: 2, price: 10.0, totalPrice: 12.2, vat: 2.2);

    DB::connection('testing')->table('cart')->insert([
        [
            'identifier' => 'stats-user-1',
            'instance' => 'default',
            'content' => CartContentCodec::encode(collect([$item->rowId => $item])),
            'coupons' => json_encode([['code' => 'X', 'type' => 'fixed', 'value' => 1]]),
            'created_at' => now()->subDays(3)->toDateTimeString(),
            'updated_at' => now()->subDays(3)->toDateTimeString(),
        ],
        [
            'identifier' => 'stats-user-2',
            'instance' => 'wishlist',
            'content' => CartContentCodec::encode(collect()),
            'coupons' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ],
    ]);

    $this->artisan('cart:stats')
        ->assertExitCode(0);
});

it('cart:stats renders n/a dates when created_at is missing', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'stats-no-date',
        'instance' => 'default',
        'content' => CartContentCodec::encode(collect()),
        'coupons' => null,
        'created_at' => null,
        'updated_at' => null,
    ]);

    $this->artisan('cart:stats')
        ->assertExitCode(0);
});
