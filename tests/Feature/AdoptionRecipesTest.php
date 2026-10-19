<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Validation\ValidationException;
use OfflineAgency\LaravelCart\Examples\CartActions;
use OfflineAgency\LaravelCart\Exceptions\CartLimitExceededException;

beforeEach(function (): void {
    require_once __DIR__.'/../../examples/CartActions.php';
});

function adoptionActions(): CartActions
{
    return new CartActions(resolveCart(), function (int $id, string $size): array {
        if ($id !== 1 || ! in_array($size, ['S', 'L'], true)) {
            throw ValidationException::withMessages(['product' => 'Unknown catalog variant.']);
        }

        return ['name' => 'Catalog shirt', 'price' => 10.0, 'gross' => 12.2, 'vat' => 2.2, 'stock' => 5];
    });
}

it('executes variant wishlist actions with explicit accumulation and isolated instances', function (string $driver): void {
    config(['cart.storage.driver' => $driver]);
    $this->artisan('migrate', ['--database' => 'testing']);
    $actions = adoptionActions();
    $small = $actions->add(1, 'S', 1);
    $large = $actions->add(1, 'L', 1);
    $wish = $actions->add(1, 'S', 3, wishlist: true);
    expect($small->rowId)->not->toBe($large->rowId)->and(resolveCart()->uniqueCount())->toBe(2);
    $actions->moveWishlistToCart($wish->rowId, 2);
    expect(resolveCart()->get($small->rowId)->qty)->toBe(3)->and(resolveCart()->total())->toBe(48.8)
        ->and(resolveCart()->instance('wishlist')->get($wish->rowId)->qty)->toBe(1);
    resolveCart()->instance('default');
    $actions->update($small->rowId, 2);
    expect($actions->summary()['total'])->toBe(36.6);
    $actions->remove($large->rowId);
    expect($actions->summary()['total'])->toBe(24.4);
    $actions->moveWishlistToCart($wish->rowId, 1);
    expect(resolveCart()->instance('wishlist')->isEmpty())->toBeTrue();
})->with(['session', 'database']);

it('rejects untrusted selections and quantities without changing the cart', function (): void {
    $actions = adoptionActions();
    foreach ([[999, 'S', 1], [1, 'fake', 1], [1, 'S', 0], [1, 'S', 100], [1, 'S', 6]] as [$id, $size, $qty]) {
        expect(fn () => $actions->add($id, $size, $qty))->toThrow(ValidationException::class);
    }
    expect(resolveCart()->isEmpty())->toBeTrue();
    $item = $actions->add(1, 'S', 4);
    expect(fn () => $actions->add(1, 'S', 2))->toThrow(ValidationException::class);
    expect(fn () => $actions->update($item->rowId, 6))->toThrow(ValidationException::class);
    expect(fn () => $actions->remove('another-owner-row'))->toThrow(ValidationException::class);
    expect(resolveCart()->total())->toBe(48.8);
});

it('retains the wishlist on target rejection and restores the calling instance', function (): void {
    $actions = adoptionActions();
    $actions->add(1, 'L', 1);
    $wish = $actions->add(1, 'S', 2, wishlist: true);
    config(['cart.limits.max_items' => 1]);
    expect(fn () => $actions->moveWishlistToCart($wish->rowId, 1))->toThrow(CartLimitExceededException::class);
    expect(resolveCart()->currentInstance())->toBe('default')->and(resolveCart()->total())->toBe(12.2)
        ->and(resolveCart()->instance('wishlist')->get($wish->rowId)->qty)->toBe(2);
    resolveCart()->instance('default');
    expect(fn () => $actions->moveWishlistToCart($wish->rowId, 3))->toThrow(ValidationException::class);
});

it('uses the default authenticated owner for mobile service calls and rejects foreign row ids', function (): void {
    config(['cart.storage.driver' => 'database']);
    $this->artisan('migrate', ['--database' => 'testing']);
    $this->actingAs(new GenericUser(['id' => 1]));
    $actions = adoptionActions();
    $row = $actions->add(1, 'S', 1);
    $this->actingAs(new GenericUser(['id' => 2]));
    expect($actions->summary()['total'])->toBe(0.0);
    expect(fn () => $actions->remove($row->rowId))->toThrow(ValidationException::class);
    $actions->add(1, 'L', 1);
    $this->actingAs(new GenericUser(['id' => 1]));
    expect($actions->summary()['total'])->toBe(12.2);
});
