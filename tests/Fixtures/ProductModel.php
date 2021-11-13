<?php

namespace OfflineAgency\Tests\OaLaravelCart\Fixtures;

class ProductModel
{
    public $someValue = 'Some value';

    public function find($id)
    {
        return $this;
    }
}
