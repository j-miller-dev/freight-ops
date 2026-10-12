<?php

namespace App\Exceptions\Unload;

use App\Support\Unload\BayDirection;
use RuntimeException;

class DirectionChanged extends RuntimeException
{
    public function __construct(public readonly BayDirection $direction)
    {
        parent::__construct(
            "This pallet should now go to {$direction->location->code}. Check the screen and confirm again."
        );
    }
}
