<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OfflineAgency\LaravelCart\CartItem;
use OfflineAgency\LaravelCart\CartItemOptions;
use OfflineAgency\LaravelCart\Contracts\Buyable;
use stdClass;
use Throwable;

/**
 * Encodes the cart content stored in the legacy `cart` table.
 *
 * PHP's serialize() output contains NUL bytes (protected/private properties),
 * which text columns on PostgreSQL cannot hold. New rows are written as
 * base64 behind a prefix marker; rows written by older versions (raw
 * serialize() output) are still read.
 *
 * Decoding restricts unserialize() to the classes a cart legitimately holds:
 * the cart value objects, Carbon/DateTime instances, collections and the
 * associated models (Buyable implementations or Eloquent models); enums are
 * not affected by allowed_classes. Any other class is restored as
 * __PHP_Incomplete_Class without running its magic methods.
 */
final class CartContentCodec
{
    public const string PREFIX = 'lcart:b64:';

    /** @var list<class-string> */
    private const array CORE_CLASSES = [
        Collection::class,
        CartItem::class,
        CartItemOptions::class,
        stdClass::class,
    ];

    public static function encode(mixed $content): string
    {
        return self::PREFIX.base64_encode(serialize($content));
    }

    /**
     * Decode stored content. Returns null when the payload is malformed or is
     * not a Collection of cart items; an empty payload is an empty cart.
     *
     * @return Collection<string, CartItem>|null
     */
    public static function decode(?string $payload): ?Collection
    {
        if ($payload === null || $payload === '') {
            return new Collection;
        }

        if (str_starts_with($payload, self::PREFIX)) {
            $payload = base64_decode(substr($payload, strlen(self::PREFIX)), true);

            if ($payload === false) {
                return null;
            }
        }

        try {
            $result = @unserialize($payload, ['allowed_classes' => self::allowedClasses($payload)]);
        } catch (Throwable) {
            return null;
        }

        return $result instanceof Collection ? self::cartItems($result) : null;
    }

    /**
     * Key the items by row id; null when the collection holds anything but cart items.
     *
     * @param  Collection<array-key, mixed>  $content
     * @return Collection<string, CartItem>|null
     */
    public static function cartItems(Collection $content): ?Collection
    {
        $items = [];

        foreach ($content as $item) {
            if (! $item instanceof CartItem) {
                return null;
            }

            $items[$item->rowId] = $item;
        }

        return new Collection($items);
    }

    /**
     * @return list<string>
     */
    private static function allowedClasses(string $serialized): array
    {
        preg_match_all('/[OC]:\d+:"([^"]+)"/', $serialized, $matches);

        $allowed = self::CORE_CLASSES;

        foreach (array_unique($matches[1]) as $class) {
            if (self::isAllowed($class)) {
                $allowed[] = $class;
            }
        }

        return $allowed;
    }

    private static function isAllowed(string $class): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return is_subclass_of($class, DateTimeInterface::class)
            || is_subclass_of($class, Collection::class)
            || is_subclass_of($class, Buyable::class)
            || is_subclass_of($class, Model::class);
    }
}
