<?php

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

use OfflineAgency\LaravelCart\CanBeBought;

class ProductWithTrait
{
    use CanBeBought;

    public $id;

    public $name;

    public $title;

    public $description;

    public $price;

    public function __construct($id = null, $name = null, $title = null, $description = null, $price = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->title = $title;
        $this->description = $description;
        $this->price = $price;
    }

    public function getKey()
    {
        return $this->id;
    }
}
