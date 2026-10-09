<?php

namespace App\Notifications;

use App\Models\Manifest;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** Tells a scaler a trailer has finished loading on the loaders' side. */
class ManifestLoadingFinished extends Notification
{
    /**
     * @param  array<string, int>  $equipment
     */
    public function __construct(
        private readonly Manifest $manifest,
        private readonly User $finishedBy,
        private readonly int $palletsLoaded,
        private readonly array $equipment,
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
        $trailer = $this->manifest->trailer;

        return [
            'manifest_id' => $this->manifest->getKey(),
            'manifest_number' => $this->manifest->manifest_number,
            'trailer_name' => $trailer?->name,
            'operator_name' => $trailer?->operator_name,
            'finished_by' => $this->finishedBy->name,
            'finished_at' => $this->manifest->loading_finished_at?->toISOString(),
            'pallets_loaded' => $this->palletsLoaded,
            'equipment' => $this->equipment,
        ];
    }
}
