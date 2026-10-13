<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('cart:inspect --json reports a deserialization error for corrupted content', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupt-json-user',
        'instance' => 'default',
        'content' => 'corrupt-serialized-data',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'corrupt-json-user', '--json' => true])
        ->assertExitCode(1)
        ->expectsOutputToContain('"identifier":"corrupt-json-user"');
});

it('cart:inspect renders non-percentage coupons with a formatted value', function (): void {
    $item = new CartItem(id: '1', name: 'Spatula', subtitle: '', qty: 1, price: 10.0, totalPrice: 10.0, vat: 0.0);

    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'coupon-user',
        'instance' => 'default',
        'content' => CartContentCodec::encode(collect([$item->rowId => $item])),
        'coupons' => json_encode([
            ['code' => 'FIXED5', 'type' => 'fixed', 'value' => 5, 'isGlobal' => false],
            [],
        ]),
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'coupon-user'])
        ->assertExitCode(0);
});

it('cart:inspect --json ignores coupons stored as invalid JSON', function (): void {
    $item = new CartItem(id: '1', name: 'Spatula', subtitle: '', qty: 1, price: 10.0, totalPrice: 10.0, vat: 0.0);

    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'bad-coupon-user',
        'instance' => 'default',
        'content' => CartContentCodec::encode(collect([$item->rowId => $item])),
        'coupons' => '{not valid json',
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'bad-coupon-user', '--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('"coupons": []');
})->skip(
    fn (): bool => DB::connection('testing')->getDriverName() === 'pgsql',
    'PostgreSQL json columns reject invalid JSON, so such a row cannot exist there.',
);
