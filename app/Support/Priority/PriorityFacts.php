<?php

namespace App\Support\Priority;

/**
 * What is known about one consignment's freight that is ready to load onto a
 * particular trailer. Everything the score is derived from, and nothing else.
 */
final readonly class PriorityFacts
{
    public function __construct(
        public ?string $serviceCode,
        // Minutes until the trailer leaves; negative once it is overdue.
        public ?int $minutesToDeparture,
        public int $itemCount,
        // Pieces of this consignment already loaded on this trailer.
        public int $onThisTrailer,
        // Pieces of this consignment loaded on other trailers.
        public int $onOtherTrailers,
        // Minutes since the first piece was scanned into the depot.
        public ?int $minutesWaiting,
    ) {}
}
