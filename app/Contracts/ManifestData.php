<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

readonly class ManifestData
{
    public function __construct(
        public string $externalId,
        public string $depotCode,
        /** @var list<string> */
        public array $destinationCodes,
        public string $manifestNumber,
        public string $serviceDate,
        public string $status,
        public ?string $trailerLabel,
        public ?string $trailerRegistration,
        public CarbonInterface $sourceUpdatedAt,
    ) {}
}
