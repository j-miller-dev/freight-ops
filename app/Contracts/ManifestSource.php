<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ManifestSource
{
    public function sourceName(): string;

    /** @return Collection<int, ManifestData> */
    public function fetch(): Collection;
}
