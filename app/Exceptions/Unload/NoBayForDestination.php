<?php

namespace App\Exceptions\Unload;

use App\Models\Depot;
use RuntimeException;

class NoBayForDestination extends RuntimeException
{
    public function __construct(public readonly Depot $destination)
    {
        parent::__construct(
            "No active bay is set up for {$destination->code}. Ask a supervisor."
        );
    }
}
