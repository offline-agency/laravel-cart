<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

use OfflineAgency\LaravelCart\Support\Coerce;

/**
 * Default Buyable implementation for Eloquent models (attributes) or plain
 * classes (public properties). The getters read the listed attributes, falling
 * back to sensible defaults; numeric and string values are converted to the
 * Buyable return types, any other value is rejected.
 */
trait CanBeBought
{
    // --- Getters (with sensible defaults reading from common Eloquent columns) ---

    public function getId(): int|string
    {
        return Coerce::key(method_exists($this, 'getKey') ? $this->getKey() : $this->buyableAttribute('id'));
    }

    public function getName(): string
    {
        return Coerce::string($this->buyableAttribute('name', 'title') ?? '');
    }

    public function getSubtitle(): string
    {
        return Coerce::string($this->buyableAttribute('subtitle', 'description') ?? '');
    }

    public function getQty(): int
    {
        return Coerce::int($this->buyableAttribute('qty') ?? 1);
    }

    public function getPrice(): float
    {
        return Coerce::float($this->buyableAttribute('price') ?? 0.0);
    }

    public function getTotalPrice(): float
    {
        return $this->getPrice() + $this->getVat();
    }

    public function getVat(): float
    {
        return Coerce::float($this->buyableAttribute('vat') ?? 0.0);
    }

    public function getUrlImg(): string
    {
        return Coerce::string($this->buyableAttribute('url_img', 'urlImg', 'image') ?? '');
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        $options = $this->buyableAttribute('cartOptions');

        return is_array($options) ? $options : [];
    }

    public function getWeight(): float
    {
        return Coerce::float($this->buyableAttribute('weight') ?? 0.0);
    }

    // --- Setters (store in transient properties for cart manipulation) ---

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setSubtitle(string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function setQty(int $qty): void
    {
        $this->qty = $qty;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }

    public function setUrlImg(string $urlImg): void
    {
        $this->urlImg = $urlImg;
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $this->cartOptions = $options;
    }

    // --- Legacy methods (deprecated, kept for backward compatibility) ---

    /**
     * @deprecated Use getId() instead.
     */
    public function getBuyableIdentifier(mixed $options = null): int|string
    {
        return $this->getId();
    }

    /**
     * @deprecated Use getName() or getSubtitle() instead.
     */
    public function getBuyableDescription(mixed $options = null): string
    {
        return Coerce::string($this->buyableAttribute('name', 'title', 'description') ?? '');
    }

    /**
     * @deprecated Use getPrice() instead.
     */
    public function getBuyablePrice(mixed $options = null): float
    {
        return $this->getPrice();
    }

    /**
     * The first non-null of the given optional attributes: model attributes or
     * public properties, which plain classes do not have to declare.
     */
    private function buyableAttribute(string ...$names): mixed
    {
        foreach ($names as $name) {
            $value = $this->{$name} ?? null;

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
