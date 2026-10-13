<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\Cart;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Storage\DatabaseStorage;
use OfflineAgency\LaravelCart\Tests\Fixtures\ProductModel;

/**
 * Defect 7: the normalized tables lost item timestamps, original prices,
 * VAT metadata and the shape of applied coupons.
 */
function regRoundTripCart(): Cart
{
    return new Cart(new DatabaseStorage(app(DatabaseManager::class)), app('events'));
}

/**
 * @return array<string, array<string, mixed>>
 */
function regRoundTripSnapshot(Collection $content): array
{
    return $content->map(function (CartItem $item): array {
        $data = $item->toArray();
        unset($data['model']); // live objects are not persisted; the association is (associatedModel)
        $data['tax'] = $item->tax;
        $data['taxRate'] = $item->taxRate;

        return $data;
    })->all();
}

beforeEach(function (): void {
    $this->artisan('migrate', ['--database' => 'testing']);
    Carbon::setTestNow('2026-03-01 09:15:30');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('preserves item timestamps', function (): void {
    $created = Carbon::parse('2025-12-24 08:00:00');
    $updated = Carbon::parse('2026-01-02 10:30:00');

    $cart = regRoundTripCart();
    $item = $cart->add('1', 'Dated', '', 1, 10.0, 10.0, 0.0, createdAt: $created, updatedAt: $updated);

    Carbon::setTestNow('2026-06-01 00:00:00');

    $loaded = regRoundTripCart()->get($item->rowId);

    expect($loaded->createdAt->toDateTimeString())->toBe('2025-12-24 08:00:00')
        ->and($loaded->updatedAt->toDateTimeString())->toBe('2026-01-02 10:30:00');
});

it('round-trips prices, VAT, coupons, options and the model association', function (): void {
    $cart = regRoundTripCart();

    $plain = $cart->add('1', 'Plain', 'Sub', 3, 100.0, 122.0, 22.0, 'V22', 'P01', 'https://img', options: ['size' => 'M']);
    $fixed = $cart->add('2', 'Fixed coupon', '', 1, 50.0, 61.0, 11.0);
    $percent = $cart->add('3', 'Percent coupon', '', 2, 10.0, 10.0, 0.0);

    $cart->addItemCoupon($fixed->rowId, 'FIVE', 'fixed', 5.0);
    $cart->addItemCoupon($percent->rowId, 'TEN', 'percentage', 10.0);
    $cart->associate($plain->rowId, ProductModel::class);
    $cart->applyGlobalCoupon('GLOBAL', 'fixed', 7.0);

    $expectedItems = regRoundTripSnapshot($cart->content());
    $expectedTotals = [$cart->total(), $cart->subtotal(), $cart->vat(), $cart->count(), $cart->originalTotalPrice()];

    $restored = regRoundTripCart();

    expect(regRoundTripSnapshot($restored->content()))->toEqual($expectedItems)
        ->and([$restored->total(), $restored->subtotal(), $restored->vat(), $restored->count(), $restored->originalTotalPrice()])
        ->toBe($expectedTotals)
        ->and($restored->get($plain->rowId)->qty)->toBe(3)
        ->and($restored->get($plain->rowId)->originalPrice)->toBe(100.0)
        ->and($restored->get($fixed->rowId)->originalTotalPrice)->toBe(61.0)
        ->and($restored->get($fixed->rowId)->originalVat)->toBe(11.0)
        ->and($restored->get($fixed->rowId)->appliedCoupons['FIVE']->discountValue)->toBe(5.0)
        ->and($restored->getCoupon('TEN')->couponType)->toBe('percentage')
        ->and($restored->hasGlobalCoupon())->toBeTrue();

    // Coupons hydrated from the database can still be detached.
    $restored->detachCoupon($fixed->rowId, 'FIVE');

    expect(regRoundTripCart()->get($fixed->rowId)->totalPrice)->toBe(61.0);
});

it('keeps the stored row id even when it no longer matches the item id', function (): void {
    $cart = regRoundTripCart();
    $item = $cart->add('1', 'Renamed', '', 1, 10.0, 10.0, 0.0);
    $item->id = '1-changed';
    $cart->associate($item->rowId, ProductModel::class);

    expect(regRoundTripCart()->get($item->rowId)->id)->toBe('1-changed');
});
