<?php

namespace App\ManifestSources;

use App\Contracts\ManifestData;
use App\Contracts\ManifestSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FixtureManifestSource implements ManifestSource
{
    public function sourceName(): string
    {
        return 'fixture';
    }

    /** @return Collection<int, ManifestData> */
    public function fetch(): Collection
    {
        $now = CarbonImmutable::now();
        $today = today()->toDateString();
        $yesterday = today()->subDay()->toDateString();

        return collect([
            new ManifestData(
                externalId: 'fixture-mel-syd-today',
                depotCode: 'MEL',
                destinationCodes: ['SYD'],
                manifestNumber: 'MEL-SYD-260913',
                serviceDate: $today,
                status: 'open',
                trailerLabel: 'MEL-SYD-260913',
                trailerRegistration: null,
                sourceUpdatedAt: $now,
            ),
            new ManifestData(
                externalId: 'fixture-mel-bne-today',
                depotCode: 'MEL',
                destinationCodes: ['BNE'],
                manifestNumber: 'MEL-BNE-260913',
                serviceDate: $today,
                status: 'closed',
                trailerLabel: 'MEL-BNE-260913',
                trailerRegistration: null,
                sourceUpdatedAt: $now,
            ),
            new ManifestData(
                externalId: 'fixture-mel-syd-yesterday',
                depotCode: 'MEL',
                destinationCodes: ['SYD'],
                manifestNumber: 'MEL-SYD-260912',
                serviceDate: $yesterday,
                status: 'closed',
                trailerLabel: 'MEL-SYD-260912',
                trailerRegistration: null,
                sourceUpdatedAt: $now,
            ),
        ]);
    }
}
