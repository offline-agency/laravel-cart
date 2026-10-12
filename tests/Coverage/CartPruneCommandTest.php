<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OfflineAgency\LaravelCart\CartItem;

function covPruneInsertNormalizedCart(int $userId, string $instance, ?Carbon $expiresAt): int
{
    $cartId = (int) DB::connection('testing')->table('carts')->insertGetId([
        'user_id' => $userId,
        'instance' => $instance,
        'expires_at' => $expiresAt?->toDateTimeString(),
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    DB::connection('testing')->table('cart_items')->insert([
        'cart_id' => $cartId,
        'row_id' => 'row-'.$cartId,
        'item_id' => (string) $cartId,
        'name' => 'Item '.$cartId,
        'price' => 10,
        'total_price' => 10,
        'qty' => 1,
        'created_at' => now()->toDateTimeString(),
        'updated_at' => now()->toDateTimeString(),
    ]);

    return $cartId;
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
});

it('cart:prune asks for confirmation and cancels when declined', function (): void {
    $item = new CartItem(id: '1', name: 'A', subtitle: '', qty: 1, price: 10.0, totalPrice: 10.0, vat: 0.0);
    $old = Carbon::now()->subHours(50)->toDateTimeString();

    DB::connection('testing')->table('cart')->insert([
        'identifier' => 'old-user',
        'instance' => 'default',
        'content' => serialize(collect([$item->rowId => $item])),
        'coupons' => null,
        'created_at' => $old,
        'updated_at' => $old,
    ]);

    $this->artisan('cart:prune', ['--hours' => 48])
        ->expectsConfirmation(__('laravel-cart::cart.command_prune_confirm', ['count' => 1, 'hours' => 48]), 'no')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
});

it('cart:prune --expired-only returns empty when nothing has expired', function (): void {
    covPruneInsertNormalizedCart(1, 'default', null);
    covPruneInsertNormalizedCart(2, 'default', Carbon::now()->addDay());

    $this->artisan('cart:prune', ['--expired-only' => true, '--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->count())->toBe(2);
});

it('cart:prune --expired-only --dry-run does not delete', function (): void {
    covPruneInsertNormalizedCart(1, 'default', Carbon::now()->subDay());

    $this->artisan('cart:prune', ['--expired-only' => true, '--dry-run' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->count())->toBe(1)
        ->and(DB::connection('testing')->table('cart_items')->count())->toBe(1);
});

it('cart:prune --expired-only cancels when confirmation is declined', function (): void {
    covPruneInsertNormalizedCart(1, 'default', Carbon::now()->subDay());

    $this->artisan('cart:prune', ['--expired-only' => true])
        ->expectsConfirmation(__('laravel-cart::cart.command_prune_confirm', ['count' => 1, 'hours' => 0]), 'no')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->count())->toBe(1);
});

it('cart:prune --expired-only deletes expired carts and their items after confirmation', function (): void {
    covPruneInsertNormalizedCart(1, 'default', Carbon::now()->subDay());
    $kept = covPruneInsertNormalizedCart(2, 'default', Carbon::now()->addDay());

    $this->artisan('cart:prune', ['--expired-only' => true])
        ->expectsConfirmation(__('laravel-cart::cart.command_prune_confirm', ['count' => 1, 'hours' => 0]), 'yes')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->pluck('id')->all())->toBe([$kept])
        ->and(DB::connection('testing')->table('cart_items')->pluck('cart_id')->all())->toBe([$kept]);
});

it('cart:prune --expired-only --instance only prunes that instance', function (): void {
    covPruneInsertNormalizedCart(1, 'shopping', Carbon::now()->subDay());
    $kept = covPruneInsertNormalizedCart(2, 'wishlist', Carbon::now()->subDay());

    $this->artisan('cart:prune', ['--expired-only' => true, '--instance' => 'shopping', '--force' => true])
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('carts')->pluck('id')->all())->toBe([$kept])
        ->and(DB::connection('testing')->table('cart_items')->pluck('cart_id')->all())->toBe([$kept]);
});
