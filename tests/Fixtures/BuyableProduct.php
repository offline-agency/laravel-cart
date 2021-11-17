<?php

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\Contracts\Buyable;

class BuyableProduct implements Buyable
{

    private $id;
    private $name;
    private $subtitle;
    private $qty;
    private $price;
    private $totalPrice;
    private $vat;
    private $vatFcCode;
    private $productFcCode;
    private $urlImg;
    private $options;

  /**
   * BuyableProduct constructor.
   *
   * @param int|string $id
   * @param string $name
   * @param string $subtitle
   * @param int $qty
   * @param float $price
   * @param float $totalPrice
   * @param float $vat
   * @param string $vatFcCode
   * @param string $productFcCode
   * @param string $urlImg
   * @param array $options
   */
    public function __construct(
      $id = 1,
      string $name = 'Item name',
      string $subtitle = 'Item description',
      int $qty = 1,
      float $price = 10.00,
      float $totalPrice = 12.22,
      float $vat = 2.22,
      string $vatFcCode = "0",
      string $productFcCode = "0",
      string $urlImg = 'https://ecommerce.test/images/item-name.png',
      array $options = []
    )
    {
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

  /**
   * @return int|string
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * @param int|string $id
   */
  public function setId($id): void
  {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * @param string $name
   */
  public function setName(string $name): void
  {
    $this->name = $name;
  }

  /**
   * @return string
   */
  public function getSubtitle(): string
  {
    return $this->subtitle;
  }

  /**
   * @param string $subtitle
   */
  public function setSubtitle(string $subtitle): void
  {
    $this->subtitle = $subtitle;
  }

  /**
   * @return int
   */
  public function getQty(): int
  {
    return $this->qty;
  }

  /**
   * @param int $qty
   */
  public function setQty(int $qty): void
  {
    $this->qty = $qty;
  }

  /**
   * @return float
   */
  public function getPrice(): float
  {
    return $this->price;
  }

  /**
   * @param float $price
   */
  public function setPrice(float $price): void
  {
    $this->price = $price;
  }

  /**
   * @return float
   */
  public function getTotalPrice(): float
  {
    return $this->totalPrice;
  }

  /**
   * @param float $totalPrice
   */
  public function setTotalPrice(float $totalPrice): void
  {
    $this->totalPrice = $totalPrice;
  }

  /**
   * @return float
   */
  public function getVat(): float
  {
    return $this->vat;
  }

  /**
   * @param float $vat
   */
  public function setVat(float $vat): void
  {
    $this->vat = $vat;
  }

  /**
   * @return string
   */
  public function getVatFcCode(): string
  {
    return $this->vatFcCode;
  }

  /**
   * @param string $vatFcCode
   */
  public function setVatFcCode(string $vatFcCode): void
  {
    $this->vatFcCode = $vatFcCode;
  }

  /**
   * @return string
   */
  public function getProductFcCode(): string
  {
    return $this->productFcCode;
  }

  /**
   * @param string $productFcCode
   */
  public function setProductFcCode(string $productFcCode): void
  {
    $this->productFcCode = $productFcCode;
  }

  /**
   * @return string
   */
  public function getUrlImg(): string
  {
    return $this->urlImg;
  }

  /**
   * @param mixed $urlImg
   */
  public function setUrlImg($urlImg): void
  {
    $this->urlImg = $urlImg;
  }

  /**
   * @return array
   */
  public function getOptions(): array
  {
    return $this->options;
  }

  /**
   * @param array $options
   */
  public function setOptions(array $options): void
  {
    $this->options = $options;
  }
}
