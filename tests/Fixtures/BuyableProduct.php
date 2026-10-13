<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\Contracts\FiscalBuyable;

class BuyableProduct implements FiscalBuyable
{
    private int|string $id;

    private string $name;

    private string $subtitle;

    private int $qty;

    private float $price;

    private float $totalPrice;

    private float $vat;

    private string $vatFcCode;

    private string $productFcCode;

    private string $urlImg;

    /** @var array<array-key, mixed> */
    private array $options;

    /**
     * BuyableProduct constructor.
     *
     * @param  array<array-key, mixed>  $options
     */
    public function __construct(
        int|string $id = 1,
        string $name = 'Item name',
        string $subtitle = 'Item description',
        int $qty = 1,
        float $price = 10.00,
        float $totalPrice = 12.22,
        float $vat = 2.22,
        string $vatFcCode = '0',
        string $productFcCode = '0',
        string $urlImg = 'https://ecommerce.test/images/item-name.png',
        array $options = []
    ) {
        $this->setId($id);
        $this->setName($name);
        $this->setSubtitle($subtitle);
        $this->setQty($qty);
        $this->setPrice($price);
        $this->setTotalPrice($totalPrice);
        $this->setVat($vat);
        $this->setVatFcCode($vatFcCode);
        $this->setProductFcCode($productFcCode);
        $this->setUrlImg($urlImg);
        $this->setOptions($options);
    }

    public function getId(): int|string
    {
        return $this->id;
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getQty(): int
    {
        return $this->qty;
    }

    public function setQty(int $qty): void
    {
        $this->qty = $qty;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getVat(): float
    {
        return $this->vat;
    }

    public function setVat(float $vat): void
    {
        $this->vat = $vat;
    }

    public function getVatFcCode(): string
    {
        return $this->vatFcCode;
    }

    public function setVatFcCode(string $vatFcCode): void
    {
        $this->vatFcCode = $vatFcCode;
    }

    public function getProductFcCode(): string
    {
        return $this->productFcCode;
    }

    public function setProductFcCode(string $productFcCode): void
    {
        $this->productFcCode = $productFcCode;
    }

    public function getUrlImg(): string
    {
        return $this->urlImg;
    }

    public function setUrlImg(string $urlImg): void
    {
        $this->urlImg = $urlImg;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param  array<array-key, mixed>  $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getWeight(): float
    {
        return 0.0;
    }
}
