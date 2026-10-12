<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart;

trait CanBeBought
{
    // --- Getters (with sensible defaults reading from common Eloquent columns) ---

    public function getId(): int|string
    {
        return method_exists($this, 'getKey') ? $this->getKey() : $this->id;
    }

    public function getName(): string
    {
        return (string) ($this->name ?? $this->title ?? '');
    }

    public function getSubtitle(): string
    {
        return (string) ($this->subtitle ?? $this->description ?? '');
    }

    public function getQty(): int
    {
        return (int) ($this->qty ?? 1);
    }

    public function getPrice(): float
    {
        return (float) ($this->price ?? 0.0);
    }

    public function getTotalPrice(): float
    {
        return $this->getPrice() + $this->getVat();
    }

    public function getVat(): float
    {
        return (float) ($this->vat ?? 0.0);
    }

    public function getUrlImg(): string
    {
        return (string) ($this->url_img ?? $this->urlImg ?? $this->image ?? '');
    }

    public function getOptions(): array
    {
        if (property_exists($this, 'cartOptions') && is_array($this->cartOptions)) {
            return $this->cartOptions;
        }

        return [];
    }

    public function getWeight(): float
    {
        return (float) ($this->weight ?? 0.0);
    }

    // --- Setters (store in transient properties for cart manipulation) ---

    public function setId($id): void
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

    public function setUrlImg($urlImg): void
    {
        $this->urlImg = $urlImg;
    }

    public function setOptions(array $options): void
    {
        $this->cartOptions = $options;
    }

    // --- Legacy methods (deprecated, kept for backward compatibility) ---

    /**
     * @deprecated Use getId() instead.
     */
    public function getBuyableIdentifier($options = null)
    {
        return $this->getId();
    }

    /**
     * @deprecated Use getName() or getSubtitle() instead.
     */
    public function getBuyableDescription($options = null)
    {
        return (string) ($this->name ?? $this->title ?? $this->description ?? '');
    }

    /**
     * @deprecated Use getPrice() instead.
     */
    public function getBuyablePrice($options = null)
    {
        return $this->getPrice();
    }
}
