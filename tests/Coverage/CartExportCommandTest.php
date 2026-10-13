<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

function covExportSerializedContent(): string
{
    $item = new CartItem(id: '1', name: 'Exported product', subtitle: '', qty: 1, price: 10.0, totalPrice: 12.2, vat: 2.2);

    return CartContentCodec::encode(collect([$item->rowId => $item]));
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('cart:export resolves a relative path against base_path', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'rel-user',
        'instance' => 'default',
        'content' => covExportSerializedContent(),
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $relative = 'cov-cart-export-'.uniqid().'.json';
    $absolute = base_path($relative);

    try {
        $this->artisan('cart:export', ['--path' => $relative])
            ->assertExitCode(0);

        expect(file_exists($absolute))->toBeTrue();

        $data = decodeJson(file_get_contents($absolute));
        expect($data['total_carts'])->toBe(1)
            ->and(data_get($data, 'carts.0.identifier'))->toBe('rel-user');
    } finally {
        @unlink($absolute);
    }
});

it('cart:export fails when the target directory does not exist', function (): void {
    $this->artisan('cart:export', ['--path' => '/cov-nonexistent-dir-'.uniqid().'/export.json'])
        ->assertExitCode(1);
});

it('cart:export fails when --since is not a valid date', function (): void {
    $path = sys_get_temp_dir().'/cov-cart-export-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path, '--since' => 'definitely-not-a-date'])
        ->assertExitCode(1);

    expect(file_exists($path))->toBeFalse();
});

it('cart:export warns for more than 5000 carts and reports size in MB', function (): void {
    $content = covExportSerializedContent();
    $now = now()->toDateTimeString();

    $rows = [];
    for ($i = 1; $i <= 5001; $i++) {
        $rows[] = [
            'identifier' => sprintf('bulk-user-%05d', $i),
            'instance' => 'default',
            'content' => $content,
            'coupons' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (count($rows) === 500) {
            DB::connection('testing')->table('cart')->insert($rows);
            $rows = [];
        }
    }
    DB::connection('testing')->table('cart')->insert($rows);

    $path = sys_get_temp_dir().'/cov-cart-export-'.uniqid().'.json';

    try {
        $this->artisan('cart:export', ['--path' => $path, '--pretty' => true])
            ->assertExitCode(0);

        expect(filesize($path))->toBeGreaterThanOrEqual(1048576);

        $data = decodeJson(file_get_contents($path));
        expect($data['total_carts'])->toBe(5001)
            ->and($data['carts'])->toHaveCount(5001);
    } finally {
        @unlink($path);
    }
});
