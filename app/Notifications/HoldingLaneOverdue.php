<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Tells a scaler, supervisor or admin a pallet has sat past its sender's hold limit. */
class HoldingLaneOverdue extends Notification
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        private readonly string $handlingUnitId,
        private readonly string $barcode,
        private readonly ?string $connoteNumber,
        private readonly string $laneCode,
        private readonly array $reasons,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'handling_unit_id' => $this->handlingUnitId,
            'barcode' => $this->barcode,
            'connote_number' => $this->connoteNumber,
            'lane_code' => $this->laneCode,
            'reasons' => $this->reasons,
        ];
    }
}
