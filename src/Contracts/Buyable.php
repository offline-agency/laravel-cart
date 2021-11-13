<?php

namespace OfflineAgency\OaLaravelCart\Contracts;

interface Buyable
{
    /**
     * Get the identifier of the Buyable item.
     *
     * @return int|string
     */
    public function getBuyableIdentifier($options = null);

  /**
   * Get the description or title of the Buyable item.
   *
   * @param null $options
   * @return string
   */
    public function getBuyableDescription($options = null): string;

  /**
   * Get the price of the Buyable item.
   *
   * @param null $options
   * @return float
   */
    public function getBuyablePrice($options = null): float;
}
