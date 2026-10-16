<?php

namespace App\Actions\Loading;

use App\Exceptions\Loading\ManifestNotOpen;
use App\Exceptions\Loading\ManifestNotReadyToClose;
use App\Models\Manifest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            $locked = Manifest::query()->lockForUpdate()->findOrFail($manifest->getKey());

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

            $locked->forceFill([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $closedBy->getKey(),
                'close_summary' => [
                    'consignments_total' => $consignmentsTotal,
                    'consignments_complete' => $consignmentsComplete,
                    'loading_finished' => $loadingFinished,
                    'closed_with_warnings' => ! $ready,
                ],
            ])->save();

            return $locked;
        });
    }
}
