<?php

namespace App\Http\Controllers\Loading;

use App\Enums\TrailerEquipment;
use App\Enums\UserRole;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\ManifestEquipment;
use App\Models\User;
use App\Notifications\ManifestLoadingFinished;
use App\Support\Loading\ManifestEquipmentCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class FinishManifestLoadingController extends Controller
{
    /** The loader says they are done with this trailer; scalers are told. */
    public function store(Request $request, Manifest $manifest, ManifestEquipmentCounts $counts): JsonResponse
    {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'equipment' => ['required', 'array'],
            'equipment.*' => ['integer', 'min:0', 'max:999'],
        ]);

        $items = array_filter(
            $validated['equipment'],
            fn ($quantity, $item) => TrailerEquipment::tryFrom((string) $item) !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        $firstTime = false;

        DB::transaction(function () use ($manifest, $request, $items, &$firstTime): void {
            // Once the scaler has closed it, the loader's sign-off is history.
            $locked = Manifest::query()->whereKey($manifest->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new ManifestNotOpen($locked);
            }

            foreach ($items as $item => $quantity) {
                ManifestEquipment::query()->updateOrCreate(
                    ['manifest_id' => $manifest->getKey(), 'item' => $item],
                    ['quantity' => $quantity],
                );
            }

            $firstTime = $locked->loading_finished_at === null;

            if ($firstTime) {
                $locked->forceFill([
                    'loading_finished_at' => now(),
                    'loading_finished_by' => $request->user()->getKey(),
                ])->save();
            }
        });

        $manifest->refresh();
        $equipment = $counts->for($manifest);

        // Only the first finish raises the alert; editing counts afterwards does not.
        if ($firstTime) {
            $recipients = User::query()
                ->whereIn('role', array_map(fn ($role) => $role->value, UserRole::trailerAlertRoles()))
                ->whereKeyNot($request->user()->getKey())
                ->get();

            Notification::send($recipients, new ManifestLoadingFinished(
                $manifest->loadMissing('trailer'),
                $request->user(),
                $manifest->manifestItems()->count(),
                $equipment,
            ));
        }

        return response()->json(['data' => $this->state($manifest, $equipment)]);
    }

    /** Take the "finished" mark back off, e.g. when it was tapped by mistake. */
    public function destroy(Manifest $manifest, ManifestEquipmentCounts $counts): JsonResponse
    {
        Gate::authorize('load', $manifest);

        // The loader can take their own sign-off back, but not once the scaler
        // has closed the manifest: only the scaler can reopen it from then on.
        DB::transaction(function () use ($manifest): void {
            $locked = Manifest::query()->whereKey($manifest->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new ManifestNotOpen($locked);
            }

            $locked->forceFill(['loading_finished_at' => null, 'loading_finished_by' => null])->save();
        });

        $manifest->refresh();

        return response()->json(['data' => $this->state($manifest, $counts->for($manifest))]);
    }

    /**
     * @param  array<string, int>  $equipment
     * @return array<string, mixed>
     */
    private function state(Manifest $manifest, array $equipment): array
    {
        return [
            'finished' => $manifest->loading_finished_at === null ? null : [
                'at' => $manifest->loading_finished_at->toISOString(),
                'by' => $manifest->finishedBy()->value('name'),
            ],
            'equipment' => $equipment,
        ];
    }
}
