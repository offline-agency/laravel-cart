<?php

namespace OfflineAgency\LaravelCart\Contracts;

interface Buyable
{
  /**
   * @return int|string
   */
  public function getId();

  /**
   * @param int|string $id
   */
  public function setId($id): void;

  /**
   * @return string
   */
  public function getName(): string;

  /**
   * @param string $name
   */
  public function setName(string $name): void;

  /**
   * @return string
   */
  public function getSubtitle(): string;

  /**
   * @param string $subtitle
   */
  public function setSubtitle(string $subtitle): void;

  /**
   * @return int
   */
  public function getQty(): int;


  /**
   * @param int $qty
   */
  public function setQty(int $qty): void;

  /**
   * @return float
   */
  public function getPrice(): float;

  /**
   * @param float $price
   */
  public function setPrice(float $price): void;

  /**
   * @return float
   */
  public function getTotalPrice(): float;

  /**
   * @param float $totalPrice
   */
  public function setTotalPrice(float $totalPrice): void;

  /**
   * @return float
   */
  public function getVat(): float;

  /**
   * @param float $vat
   */
  public function setVat(float $vat): void;

  /**
   * @return string
   */
  public function getVatFcCode(): string;

  /**
   * @param string $vatFcCode
   */
  public function setVatFcCode(string $vatFcCode): void;

  /**
   * @return string
   */
  public function getProductFcCode(): string;

  /**
   * @param string $productFcCode
   */
  public function setProductFcCode(string $productFcCode): void;


  /**
   * @return string
   */
  public function getUrlImg(): string;


  /**
   * @param mixed $urlImg
   */
  public function setUrlImg($urlImg): void;

  /**
   * @return array
   */
  public function getOptions(): array;

  /**
   * @param array $options
   */
  public function setOptions(array $options): void;

}
