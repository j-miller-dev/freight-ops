<?php

namespace App\Actions\Loading;

use App\Contracts\ManifestData;
use App\Contracts\ManifestSource;
use App\Models\Depot;
use App\Models\Manifest;

class SyncManifests
{
    public function handle(ManifestSource $source): void
    {
        foreach ($source->fetch() as $data) {
            $this->syncOne($source->sourceName(), $data);
        }
    }

    private function syncOne(string $sourceName, ManifestData $data): void
    {
        $depot = Depot::query()->where('code', $data->depotCode)->firstOrFail();

        $existing = Manifest::query()
            ->where('source', $sourceName)
            ->where('external_id', $data->externalId)
            ->first();

        // A manifest closed locally in Freight Ops (see CloseManifest) stays
        // closed even if the upstream source hasn't caught up yet; it should
        // never silently reopen on the next sync.
        $lockedClosedLocally = $existing !== null
            && $existing->closed_by !== null
            && $data->status !== 'closed';

        $manifest = Manifest::query()->updateOrCreate(
            ['source' => $sourceName, 'external_id' => $data->externalId],
            [
                'depot_id' => $depot->getKey(),
                'manifest_number' => $data->manifestNumber,
                'service_date' => $data->serviceDate,
                'departs_at' => $data->departsAt,
                ...($lockedClosedLocally ? [] : [
                    'status' => $data->status,
                    'closed_at' => $data->status === 'closed' ? $data->sourceUpdatedAt : null,
                ]),
                'trailer_label' => $data->trailerLabel,
                'trailer_registration' => $data->trailerRegistration,
                'source_updated_at' => $data->sourceUpdatedAt,
                'last_synced_at' => now(),
            ],
        );

        $destinationDepots = Depot::query()
            ->whereIn('code', $data->destinationCodes)
            ->get();

        $syncData = $destinationDepots
            ->mapWithKeys(fn (Depot $d): array => [$d->getKey() => ['is_primary' => true]])
            ->all();

        $manifest->destinations()->syncWithoutDetaching($syncData);
    }
}
