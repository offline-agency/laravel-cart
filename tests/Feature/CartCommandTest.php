<?php

declare(strict_types=1);
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);

    $this->cart = $this->app->make('cart');
    $this->cart->destroy();
});

it('cart:clear command runs successfully with --force flag', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-clear-test');

    $this->artisan('cart:clear', ['--force' => true])
        ->assertExitCode(0);
});

it('cart:clear command removes all stored records', function (): void {
    $this->cart->add('1', 'Product A', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-1');

    $this->cart->instance('shopping');
    $this->cart->add('2', 'Product B', '', 1, 20.0, 24.4, 4.4);
    $this->cart->store('user-2');

    $this->artisan('cart:clear', ['--force' => true]);

    $count = DB::connection('testing')
        ->table('cart')->count();

    expect($count)->toBe(0);
});

it('cart:clear with --instance option only removes that instance', function (): void {
    $this->cart->instance('shopping')->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-shopping');

    $this->cart->instance('wishlist')->add('2', 'Other', '', 1, 5.0, 5.0, 0.0);
    $this->cart->store('user-wishlist');

    $this->artisan('cart:clear', ['--force' => true, '--instance' => 'shopping']);

    $remaining = DB::connection('testing')
        ->table('cart')->count();

    expect($remaining)->toBe(1);
});

it('cart:clear aborts when the confirmation is declined', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-declined');

    $this->artisan('cart:clear')
        ->expectsConfirmation('This will delete ALL stored cart records from the database. Continue?', 'no')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(1);
});

it('cart:clear deletes the records when the confirmation is accepted', function (): void {
    $this->cart->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-accepted');

    $this->artisan('cart:clear')
        ->expectsConfirmation('This will delete ALL stored cart records from the database. Continue?', 'yes')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(0);
});

it('cart:clear scopes the confirmation prompt to the given instance', function (): void {
    $this->cart->instance('wishlist')->add('1', 'Product', '', 1, 10.0, 12.2, 2.2);
    $this->cart->store('user-wishlist-confirm');

    $this->artisan('cart:clear', ['--instance' => 'wishlist'])
        ->expectsConfirmation('This will delete all stored cart records for instance "wishlist". Continue?', 'yes')
        ->assertExitCode(0);

    expect(DB::connection('testing')->table('cart')->count())->toBe(0);
});
