<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Support;

use OfflineAgency\LaravelCart\Exceptions\InvalidConfigurationException;
use RoundingMode;

/**
 * Typed access to the `cart.*` configuration.
 *
 * Configuration values are untyped user input: each accessor validates the
 * value and throws InvalidConfigurationException when it cannot be used,
 * instead of silently casting it. Numeric settings also accept numeric
 * strings (e.g. values read from env()); a null value means "use the default".
 *
 * @internal
 */
final class CartConfig
{
    public static function decimals(): int
    {
        return self::integer('cart.format.decimals', 2);
    }

    public static function decimalPoint(): string
    {
        return self::string('cart.format.decimal_point', '.');
    }

    public static function thousandSeparator(): string
    {
        return self::string('cart.format.thousand_separator', ',');
    }

    /**
     * The rounding mode used for every amount the cart computes.
     *
     * Accepts one of the PHP_ROUND_HALF_* constants or a RoundingMode case;
     * defaults to PHP_ROUND_HALF_UP.
     *
     * @return int<1, 4>|RoundingMode
     */
    public static function roundingMode(): int|RoundingMode
    {
        $key = 'cart.format.rounding_mode';
        $mode = config($key);

        if ($mode === null) {
            return PHP_ROUND_HALF_UP;
        }

        if ($mode instanceof RoundingMode) {
            return $mode;
        }

        return match ($mode) {
            PHP_ROUND_HALF_UP, PHP_ROUND_HALF_DOWN, PHP_ROUND_HALF_EVEN, PHP_ROUND_HALF_ODD => $mode,
            default => throw new InvalidConfigurationException($key, 'one of the PHP_ROUND_HALF_* constants or a RoundingMode case', $mode),
        };
    }

    /**
     * Round an amount with the configured decimals and rounding mode.
     */
    public static function round(float $value): float
    {
        return round($value, self::decimals(), self::roundingMode());
    }

    /**
     * A numeric setting where null disables it (e.g. the cart limits).
     */
    public static function nullableNumber(string $key): int|float|null
    {
        $value = config($key);

        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        throw new InvalidConfigurationException($key, 'a number or null', $value);
    }

    public static function float(string $key, float $default): float
    {
        return (float) (self::nullableNumber($key) ?? $default);
    }

    public static function integer(string $key, int $default): int
    {
        $value = self::nullableNumber($key) ?? $default;

        if (is_int($value)) {
            return $value;
        }

        if ($value !== floor($value)) {
            throw new InvalidConfigurationException($key, 'an integer', $value);
        }

        return (int) $value;
    }

    public static function string(string $key, string $default): string
    {
        return self::nullableString($key) ?? $default;
    }

    public static function nullableString(string $key): ?string
    {
        $value = config($key);

        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new InvalidConfigurationException($key, 'a string or null', $value);
    }

    /**
     * The database connection the cart tables live on (null = the default connection).
     */
    public static function connection(): ?string
    {
        return self::nullableString('cart.database.connection');
    }
}
