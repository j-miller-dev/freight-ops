<?php

namespace App\Exceptions\Unload;

use App\Models\OperationalEvent;
use RuntimeException;

class UnloadEventConflict extends RuntimeException
{
    public function __construct(public readonly OperationalEvent $existingEvent)
    {
        parent::__construct(
            "Client event {$existingEvent->client_event_id} was already used for a different operation."
        );
    }
}
