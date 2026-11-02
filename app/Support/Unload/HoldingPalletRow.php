<?php

namespace App\Support\Unload;

/** One held pallet with its readiness, used internally by HoldingLaneBoard. */
final readonly class HoldingPalletRow
{
    public function __construct(
        public string $handlingUnitId,
        public string $locationId,
        public string $barcode,
        public ?string $connoteNumber,
        public ?string $senderName,
        public bool $released,
        public HoldingReadinessStatus $status,
    ) {}
}
