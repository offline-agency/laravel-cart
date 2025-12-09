<?php

namespace OfflineAgency\LaravelCart\Contracts;

interface Buyable
{
    /**
     * @return int|string
     */
    public function getId();

    /**
     * @param  int|string  $id
     */
    public function setId($id): void;

    public function getName(): string;

    public function setName(string $name): void;

    public function getSubtitle(): string;

    public function setSubtitle(string $subtitle): void;

    public function getQty(): int;

    public function setQty(int $qty): void;

    public function getPrice(): float;

    public function setPrice(float $price): void;

    public function getTotalPrice(): float;

    public function setTotalPrice(float $totalPrice): void;

    public function getVat(): float;

    public function setVat(float $vat): void;

    public function getVatFcCode(): string;

    public function setVatFcCode(string $vatFcCode): void;

    public function getProductFcCode(): string;

    public function setProductFcCode(string $productFcCode): void;

    public function getUrlImg(): string;

    /**
     * @param  mixed  $urlImg
     */
    public function setUrlImg($urlImg): void;

    public function getOptions(): array;

    public function setOptions(array $options): void;
}
