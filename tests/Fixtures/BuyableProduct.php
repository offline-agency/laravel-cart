<?php

namespace OfflineAgency\Tests\OaLaravelCart\Fixtures;

use OfflineAgency\OaLaravelCart\Contracts\Buyable;

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
    private $option;

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
   * @param array $option
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
      array $option = []
    )
    {
        $this->id = $id;
        $this->name = $name;
        $this->price = $price;
        $this->subtitle = $subtitle;
        $this->qty = $qty;
        $this->totalPrice = $totalPrice;
        $this->vat = $vat;
        $this->vatFcCode = $vatFcCode;
        $this->productFcCode = $productFcCode;
        $this->price = $price;
    }

    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null)
    {
        return $this->id;
    }

  /**
   * Get the description or title of the Buyable item.
   *
   * @param null $options
   * @return string
   */
    public function getBuyableDescription($options = null): string
    {
        return $this->name;
    }

  /**
   * Get the price of the Buyable item.
   *
   * @param null $options
   * @return float
   */
    public function getBuyablePrice($options = null): float
    {
        return $this->price;
    }
}
