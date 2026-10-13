<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

class ProductModel
{
    public string $someValue = 'Some value';

    public function find(int|string $id): static
    {
        return $this;
    }
}
