<?php

declare(strict_types=1);

use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\Exceptions\CartException;
use OfflineAgency\LaravelCart\Exceptions\InvalidConfigurationException;

/**
 * cart.format.rounding_mode feeds round() for every amount the cart computes.
 */
function roundingCovItem(): CartItem
{
    return new CartItem(1, 'Item', '', 1, 10.0, 10.0, '', '', 0.0);
}

it('rounds half up by default', function (): void {
    expect(roundingCovItem()->formatFloat(0.125))->toBe(0.13)
        ->and(roundingCovItem()->formatFloat(-0.125))->toBe(-0.13);
});

it('treats a null rounding mode as the default', function (): void {
    config()->set('cart.format.rounding_mode', null);

    expect(roundingCovItem()->formatFloat(0.125))->toBe(0.13);
});

it('accepts the PHP_ROUND_HALF_* constants', function (int $mode, float $expected): void {
    config()->set('cart.format.rounding_mode', $mode);

    expect(roundingCovItem()->formatFloat(0.125))->toBe($expected);
})->with([
    'PHP_ROUND_HALF_UP' => [PHP_ROUND_HALF_UP, 0.13],
    'PHP_ROUND_HALF_DOWN' => [PHP_ROUND_HALF_DOWN, 0.12],
    'PHP_ROUND_HALF_EVEN' => [PHP_ROUND_HALF_EVEN, 0.12],
    'PHP_ROUND_HALF_ODD' => [PHP_ROUND_HALF_ODD, 0.13],
]);

it('accepts RoundingMode enum cases', function (RoundingMode $mode, float $value, float $expected): void {
    config()->set('cart.format.rounding_mode', $mode);

    expect(roundingCovItem()->formatFloat($value))->toBe($expected);
})->with([
    'HalfTowardsZero' => [RoundingMode::HalfTowardsZero, 0.125, 0.12],
    'AwayFromZero' => [RoundingMode::AwayFromZero, 0.121, 0.13],
    'NegativeInfinity' => [RoundingMode::NegativeInfinity, 0.129, 0.12],
]);

it('applies the rounding mode to the cart totals', function (): void {
    config()->set('cart.format.rounding_mode', RoundingMode::TowardsZero);

    $cart = resolveCart();
    $cart->add('1', 'Item', '', 1, 10.0, 10.129, 0.129);

    expect($cart->total())->toBe(10.12)
        ->and($cart->vat())->toBe(0.12);
});

it('rejects an invalid rounding mode', function (mixed $mode, string $given): void {
    config()->set('cart.format.rounding_mode', $mode);

    expect(fn () => roundingCovItem()->formatFloat(0.125))->toThrow(
        InvalidConfigurationException::class,
        "The [cart.format.rounding_mode] configuration value must be one of the PHP_ROUND_HALF_* constants or a RoundingMode case, {$given} given.",
    );
})->with([
    'zero' => [0, 'int 0'],
    'out of range' => [5, 'int 5'],
    'numeric string' => ['1', "string '1'"],
    'name' => ['half_up', "string 'half_up'"],
    'float' => [1.0, 'float 1.0'],
    'array' => [[PHP_ROUND_HALF_UP], 'array'],
]);

it('reports an invalid rounding mode as a cart exception', function (): void {
    config()->set('cart.format.rounding_mode', 'nope');

    $cart = resolveCart();

    expect(fn () => $cart->add('1', 'Item', '', 1, 10.0, 12.2, 2.2))->toThrow(CartException::class);
});
