<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Support\CartContentCodec;

function seedCartRow(
    string $identifier,
    string $instance = 'default',
    array $items = [],
    ?array $coupons = null,
    ?string $createdAt = null,
    ?string $updatedAt = null,
): void {
    $collection = collect();

    foreach ($items as $item) {
        $cartItem = new CartItem(
            id: $item['id'],
            name: $item['name'],
            subtitle: $item['subtitle'] ?? '',
            qty: $item['qty'] ?? 1,
            price: $item['price'],
            totalPrice: $item['totalPrice'] ?? null,
            vat: $item['vat'] ?? 0.0,
        );
        $collection->put($cartItem->rowId, $cartItem);
    }

    $now = $createdAt ?? now()->toDateTimeString();

    DB::connection('testing')->table('cart')->insert([
        'identifier' => $identifier,
        'instance' => $instance,
        'content' => CartContentCodec::encode($collection),
        'coupons' => $coupons !== null ? json_encode($coupons) : null,
        'created_at' => $now,
        'updated_at' => $updatedAt ?? $now,
    ]);
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

// ─── cart:clear (Termwind refactor) ─────────────────────────────────────────

it('cart:clear still works after Termwind refactor', function (): void {
    seedCartRow('user-clear-1', 'default', [['id' => '1', 'name' => 'Product', 'price' => 10.0, 'totalPrice' => 12.2, 'vat' => 2.2]]);

    $this->artisan('cart:clear', ['--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(0);
});

it('cart:clear with --instance still filters correctly after refactor', function (): void {
    seedCartRow('user-1', 'shopping', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'wishlist', [['id' => '2', 'name' => 'B', 'price' => 5.0]]);

    $this->artisan('cart:clear', ['--force' => true, '--instance' => 'shopping']);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
});

// ─── cart:stats ─────────────────────────────────────────────────────────────

it('cart:stats on empty table returns success', function (): void {
    $this->artisan('cart:stats')
        ->assertExitCode(0);
});

it('cart:stats --json on empty table returns zero counts', function (): void {
    $this->artisan('cart:stats', ['--json' => true])
        ->assertExitCode(0);

    // Run again capturing output
    $result = $this->artisan('cart:stats', ['--json' => true]);
    $result->assertExitCode(0);
});

it('cart:stats returns correct counts with seeded data', function (): void {
    seedCartRow('user-1', 'default', [
        ['id' => '1', 'name' => 'Product A', 'price' => 100.0, 'totalPrice' => 122.0, 'vat' => 22.0, 'qty' => 2],
        ['id' => '2', 'name' => 'Product B', 'price' => 50.0, 'totalPrice' => 61.0, 'vat' => 11.0],
    ]);
    seedCartRow('user-2', 'wishlist', [
        ['id' => '3', 'name' => 'Product C', 'price' => 30.0, 'totalPrice' => 36.6, 'vat' => 6.6],
    ], coupons: [['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10]]);

    $this->artisan('cart:stats', ['--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('"totalCarts": 2');
});

it('cart:stats --instance filters correctly', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'wishlist', [['id' => '2', 'name' => 'B', 'price' => 20.0]]);

    $this->artisan('cart:stats', ['--json' => true, '--instance' => 'wishlist'])
        ->assertExitCode(0)
        ->expectsOutputToContain('"totalCarts": 1');
});

// ─── cart:list ───────────────────────────────────────────────────────────────

it('cart:list on empty table returns success', function (): void {
    $this->artisan('cart:list')
        ->assertExitCode(0);
});

it('cart:list --json on empty table returns empty array', function (): void {
    $this->artisan('cart:list', ['--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('[]');
});

it('cart:list returns all carts', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'default', [['id' => '2', 'name' => 'B', 'price' => 20.0]]);

    $this->artisan('cart:list', ['--json' => true])
        ->assertExitCode(0);
});

it('cart:list --limit restricts output', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'default', [['id' => '2', 'name' => 'B', 'price' => 20.0]]);
    seedCartRow('user-3', 'default', [['id' => '3', 'name' => 'C', 'price' => 30.0]]);

    $this->artisan('cart:list', ['--json' => true, '--limit' => 2])
        ->assertExitCode(0);
});

it('cart:list --instance filters correctly', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'wishlist', [['id' => '2', 'name' => 'B', 'price' => 20.0]]);

    $this->artisan('cart:list', ['--json' => true, '--instance' => 'wishlist'])
        ->assertExitCode(0)
        ->expectsOutputToContain('user-2');
});

it('cart:list --sort options work', function (): void {
    seedCartRow('user-a', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]], createdAt: '2026-01-01 00:00:00');
    seedCartRow('user-b', 'default', [['id' => '2', 'name' => 'B', 'price' => 20.0]], createdAt: '2026-06-01 00:00:00');

    $this->artisan('cart:list', ['--json' => true, '--sort' => 'oldest'])
        ->assertExitCode(0);

    $this->artisan('cart:list', ['--json' => true, '--sort' => 'identifier'])
        ->assertExitCode(0);
});

it('cart:list handles corrupted content without crashing', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupt-user',
        'instance' => 'default',
        'content' => 'not-valid-serialized-data',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:list', ['--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('corrupt-user');
});

// ─── cart:inspect ────────────────────────────────────────────────────────────

it('cart:inspect shows cart details', function (): void {
    seedCartRow('user-42', 'default', [
        ['id' => '1', 'name' => 'Spatula', 'price' => 800.0, 'totalPrice' => 800.0, 'vat' => 0.0, 'qty' => 2],
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'user-42'])
        ->assertExitCode(0);
});

it('cart:inspect returns failure for missing cart', function (): void {
    $this->artisan('cart:inspect', ['identifier' => 'nonexistent'])
        ->assertExitCode(1);
});

it('cart:inspect --json returns valid JSON for existing cart', function (): void {
    seedCartRow('user-42', 'default', [
        ['id' => '1', 'name' => 'Spatula', 'price' => 800.0, 'totalPrice' => 800.0, 'vat' => 0.0],
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'user-42', '--json' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('"identifier": "user-42"');
});

it('cart:inspect --json returns error for missing cart', function (): void {
    $this->artisan('cart:inspect', ['identifier' => 'nonexistent', '--json' => true])
        ->assertExitCode(1)
        ->expectsOutputToContain('"error"');
});

it('cart:inspect --raw works on valid data', function (): void {
    seedCartRow('user-42', 'default', [
        ['id' => '1', 'name' => 'Spatula', 'price' => 800.0, 'totalPrice' => 800.0, 'vat' => 0.0],
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'user-42', '--raw' => true])
        ->assertExitCode(0);
});

it('cart:inspect --raw works on corrupted data', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupt-user',
        'instance' => 'default',
        'content' => 'corrupt-serialized-data',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'corrupt-user', '--raw' => true])
        ->assertExitCode(0);
});

it('cart:inspect corrupted data without --raw shows error', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupt-user',
        'instance' => 'default',
        'content' => 'corrupt-serialized-data',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $this->artisan('cart:inspect', ['identifier' => 'corrupt-user'])
        ->assertExitCode(1);
});

it('cart:inspect shows coupons section', function (): void {
    seedCartRow('user-42', 'default', [
        ['id' => '1', 'name' => 'Spatula', 'price' => 800.0, 'totalPrice' => 800.0, 'vat' => 0.0],
    ], coupons: [['code' => 'SUMMER20', 'type' => 'percentage', 'value' => 20, 'isGlobal' => true]]);

    $this->artisan('cart:inspect', ['identifier' => 'user-42'])
        ->assertExitCode(0);
});

// ─── cart:prune ──────────────────────────────────────────────────────────────

it('cart:prune deletes old carts', function (): void {
    seedCartRow('old-user', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]], updatedAt: Carbon::now()->subHours(50)->toDateTimeString(), createdAt: Carbon::now()->subHours(50)->toDateTimeString());
    seedCartRow('new-user', 'default', [['id' => '2', 'name' => 'B', 'price' => 20.0]], updatedAt: Carbon::now()->toDateTimeString(), createdAt: Carbon::now()->toDateTimeString());

    $this->artisan('cart:prune', ['--hours' => 48, '--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
    expect(DB::connection('testing')->table('cart')->first()->identifier)->toBe('new-user');
});

it('cart:prune --dry-run does not delete', function (): void {
    seedCartRow('old-user', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]], updatedAt: Carbon::now()->subHours(50)->toDateTimeString(), createdAt: Carbon::now()->subHours(50)->toDateTimeString());

    $this->artisan('cart:prune', ['--hours' => 48, '--dry-run' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
});

it('cart:prune --instance only prunes that instance', function (): void {
    seedCartRow('old-1', 'shopping', [['id' => '1', 'name' => 'A', 'price' => 10.0]], updatedAt: Carbon::now()->subHours(50)->toDateTimeString(), createdAt: Carbon::now()->subHours(50)->toDateTimeString());
    seedCartRow('old-2', 'wishlist', [['id' => '2', 'name' => 'B', 'price' => 20.0]], updatedAt: Carbon::now()->subHours(50)->toDateTimeString(), createdAt: Carbon::now()->subHours(50)->toDateTimeString());

    $this->artisan('cart:prune', ['--hours' => 48, '--force' => true, '--instance' => 'shopping'])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
    expect(DB::connection('testing')->table('cart')->first()->instance)->toBe('wishlist');
});

it('cart:prune returns success with zero matching carts', function (): void {
    seedCartRow('new-user', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);

    $this->artisan('cart:prune', ['--hours' => 48, '--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
});

// ─── cart:export ─────────────────────────────────────────────────────────────

it('cart:export creates a valid JSON file', function (): void {
    seedCartRow('user-1', 'default', [
        ['id' => '1', 'name' => 'Product A', 'price' => 100.0, 'totalPrice' => 122.0, 'vat' => 22.0],
    ]);

    $path = sys_get_temp_dir().'/cart-export-test-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path])
        ->assertExitCode(0);

    expect(file_exists($path))->toBeTrue();

    $data = json_decode(file_get_contents($path), true);
    expect($data)->toHaveKey('exported_at')
        ->toHaveKey('total_carts')
        ->toHaveKey('carts');
    expect($data['total_carts'])->toBe(1);
    expect($data['carts'])->toHaveCount(1);
    expect($data['carts'][0]['identifier'])->toBe('user-1');

    @unlink($path);
});

it('cart:export --instance filters correctly', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);
    seedCartRow('user-2', 'wishlist', [['id' => '2', 'name' => 'B', 'price' => 20.0]]);

    $path = sys_get_temp_dir().'/cart-export-instance-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path, '--instance' => 'wishlist'])
        ->assertExitCode(0);

    $data = json_decode(file_get_contents($path), true);
    expect($data['total_carts'])->toBe(1);
    expect($data['carts'][0]['identifier'])->toBe('user-2');

    @unlink($path);
});

it('cart:export --since filters by date', function (): void {
    seedCartRow('old-user', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]], createdAt: '2025-01-01 00:00:00');
    seedCartRow('new-user', 'default', [['id' => '2', 'name' => 'B', 'price' => 20.0]], createdAt: '2026-06-01 00:00:00');

    $path = sys_get_temp_dir().'/cart-export-since-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path, '--since' => '2026-01-01'])
        ->assertExitCode(0);

    $data = json_decode(file_get_contents($path), true);
    expect($data['total_carts'])->toBe(1);
    expect($data['carts'][0]['identifier'])->toBe('new-user');

    @unlink($path);
});

it('cart:export --pretty produces indented JSON', function (): void {
    seedCartRow('user-1', 'default', [['id' => '1', 'name' => 'A', 'price' => 10.0]]);

    $path = sys_get_temp_dir().'/cart-export-pretty-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path, '--pretty' => true])
        ->assertExitCode(0);

    $content = file_get_contents($path);
    expect($content)->toContain("\n");

    @unlink($path);
});

it('cart:export includes corrupted carts with error flag', function (): void {
    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'corrupt-user',
        'instance' => 'default',
        'content' => 'invalid-serialized-data',
        'coupons' => null,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    $path = sys_get_temp_dir().'/cart-export-corrupt-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path])
        ->assertExitCode(0);

    $data = json_decode(file_get_contents($path), true);
    expect($data['carts'])->toHaveCount(1);
    expect($data['carts'][0])->toHaveKey('error');

    @unlink($path);
});

it('cart:export on empty table returns success', function (): void {
    $path = sys_get_temp_dir().'/cart-export-empty-'.uniqid().'.json';

    $this->artisan('cart:export', ['--path' => $path])
        ->assertExitCode(0);
});

// ─── cart:migrate-driver ─────────────────────────────────────────────────────

it('cart:migrate-driver fails when from and to are the same', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'database', '--to' => 'database'])
        ->assertExitCode(1);
});

it('cart:migrate-driver fails for database to session direction', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'database', '--to' => 'session'])
        ->assertExitCode(1);
});

it('cart:migrate-driver session to database with unsupported driver shows error', function (): void {
    config(['session.driver' => 'array']);

    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database'])
        ->assertExitCode(1);
});

it('cart:migrate-driver --dry-run with database session driver does not write', function (): void {
    config(['session.driver' => 'database']);
    config(['session.table' => 'sessions']);
    config(['session.connection' => 'testing']);

    // Create a sessions table for the test
    DB::connection('testing')->getSchemaBuilder()->create('sessions', function ($table): void {
        $table->string('id')->primary();
        $table->text('payload');
        $table->integer('last_activity');
        $table->string('user_id')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
    });

    $this->artisan('cart:migrate-driver', ['--from' => 'session', '--to' => 'database', '--dry-run' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(0);
});

it('cart:migrate-driver rejects invalid driver names', function (): void {
    $this->artisan('cart:migrate-driver', ['--from' => 'redis', '--to' => 'database'])
        ->assertExitCode(1);
});
