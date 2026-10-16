<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Support;

use Stringable;
use UnexpectedValueException;

/**
 * Validating conversions for untyped values crossing into the cart: database
 * rows, decoded JSON and attributes read from models. Values that already
 * convert cleanly with PHP's casts (numbers, numeric strings, stringables)
 * keep converting as before; anything else is rejected instead of being
 * silently turned into a wrong value.
 *
 * @internal
 */
final class Coerce
{
    public static function string(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw self::unexpected('a string', $value);
    }

    public static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : self::string($value);
    }

    public static function float(mixed $value): float
    {
        return (float) self::number($value);
    }

    public static function int(mixed $value): int
    {
        return is_int($value) ? $value : (int) self::number($value);
    }

    public static function number(mixed $value): int|float
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) {
            return $value;
        }

        if (is_string($value) && is_numeric($value) && is_finite((float) $value)) {
            return $value + 0;
        }

        throw self::unexpected('a number', $value);
    }

    public static function key(mixed $value): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        throw self::unexpected('an integer or string identifier', $value);
    }

    private static function unexpected(string $expected, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException(sprintf('Expected %s, %s given.', $expected, get_debug_type($value)));
    }
}
