<?php

namespace App\Exceptions\Loading;

use App\Models\OperationalEvent;
use RuntimeException;

class ClientEventConflict extends RuntimeException
{
    public function __construct(
        public readonly OperationalEvent $existingEvent,
        public readonly string $handlingUnitId,
        public readonly string $manifestId,
    ) {
        parent::__construct(
            "Client event {$existingEvent->client_event_id} was already used for "
            .'a different loading operation.'
        );
    }
}
