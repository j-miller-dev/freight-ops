<?php

namespace App\Actions\Loading;

use App\Enums\EventType;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Exceptions\Loading\ManifestNotReadyToClose;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * The scaler's close/dispatch decision, made locally in Freight Ops rather
 * than only mirrored from an upstream sync (see docs/domain-decisions.md).
 * Outstanding pallets or an unfinished loader sign-off warn but do not block
 * closing; an override is recorded in close_summary for audit.
 */
class CloseManifest
{
    public function handle(Manifest $manifest, User $closedBy, bool $acknowledged = false): Manifest
    {
        return DB::transaction(function () use ($manifest, $closedBy, $acknowledged): Manifest {
            $locked = Manifest::query()->whereKey($manifest->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new ManifestNotOpen($locked);
            }

            $consignments = DB::table('manifest_items')
                ->join('handling_units', 'handling_units.id', '=', 'manifest_items.handling_unit_id')
                ->join('consignments', 'consignments.id', '=', 'handling_units.consignment_id')
                ->where('manifest_items.manifest_id', $locked->getKey())
                ->groupBy('consignments.id', 'consignments.item_count')
                ->selectRaw('consignments.item_count as item_count, count(*) as on_trailer')
                ->get();

            $consignmentsTotal = $consignments->count();
            $consignmentsComplete = $consignments
                ->filter(fn (object $row): bool => (int) $row->on_trailer >= (int) $row->item_count)
                ->count();
            $loadingFinished = $locked->loading_finished_at !== null;
            $ready = $consignmentsComplete === $consignmentsTotal && $loadingFinished;

            if (! $ready && ! $acknowledged) {
                throw new ManifestNotReadyToClose(
                    manifest: $locked,
                    consignmentsTotal: $consignmentsTotal,
                    consignmentsComplete: $consignmentsComplete,
                    loadingFinished: $loadingFinished,
                );
            }

            $closedAt = now();

            $locked->forceFill([
                'status' => 'closed',
                'closed_at' => $closedAt,
                'closed_by' => $closedBy->getKey(),
                'close_summary' => [
                    'consignments_total' => $consignmentsTotal,
                    'consignments_complete' => $consignmentsComplete,
                    'loading_finished' => $loadingFinished,
                    'closed_with_warnings' => ! $ready,
                ],
            ])->save();

            $this->recordClosedEvents($locked, $closedBy, $closedAt, closedWithWarnings: ! $ready);

            return $locked;
        });
    }

    /**
     * One immutable event per pallet on the manifest, so closing shows in each
     * pallet's trace. The event id is derived from the manifest and pallet, so a
     * retried close can never write a second row.
     */
    private function recordClosedEvents(Manifest $manifest, User $closedBy, CarbonInterface $closedAt, bool $closedWithWarnings): void
    {
        ManifestItem::query()
            ->where('manifest_id', $manifest->getKey())
            ->pluck('handling_unit_id')
            ->each(function (string $handlingUnitId) use ($manifest, $closedBy, $closedAt, $closedWithWarnings): void {
                $event = new OperationalEvent;
                $event->client_event_id = Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    "manifest-close:{$manifest->getKey()}:{$handlingUnitId}",
                )->toString();
                $event->handling_unit_id = $handlingUnitId;
                $event->actor_id = $closedBy->getKey();
                $event->event_type = EventType::ManifestClosed;
                $event->occurred_at = $closedAt;
                $event->received_at = $closedAt;
                $event->metadata = [
                    'manifest_id' => $manifest->getKey(),
                    'manifest_number' => $manifest->manifest_number,
                    'closed_with_warnings' => $closedWithWarnings,
                ];
                $event->save();
            });
    }
}
