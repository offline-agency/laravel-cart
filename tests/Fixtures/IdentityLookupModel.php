<?php

declare(strict_types=1);

namespace OfflineAgency\LaravelCart\Tests\Fixtures;

final class IdentityLookupModel
{
    public int|string $selectedId;

    public function find(int|string $id): self
    {
        $this->selectedId = $id;

        return $this;
    }
}
