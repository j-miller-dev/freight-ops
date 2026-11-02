<?php

namespace App\Support\Unload;

use App\Enums\EventType;
use App\Enums\HandlingUnitStatus;
use App\Models\HandlingUnit;
use App\Models\HoldingRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every active holding lane with freight in it, and whether each pallet
 * still deserves to sit there. The signal a runner needs without having to
 * remember to go and check.
 */
class HoldingLaneBoard
{
    public function __construct(
        private readonly BayDirector $director,
        private readonly HoldingReadiness $readiness,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function lanes(): array
    {
        $lanes = $this->director->holdingLanes();

        if ($lanes->isEmpty()) {
            return [];
        }

        $pallets = HandlingUnit::query()
            ->whereIn('current_location_id', $lanes->pluck('id'))
            ->where('current_status', HandlingUnitStatus::Held)
            ->with('consignment:id,sender_name,service_code,destination_depot_id,connote_number')
            ->get(['id', 'current_location_id', 'consignment_id']);

        if ($pallets->isEmpty()) {
            return [];
        }

        $heldSince = $this->heldSince($pallets->pluck('id')->all());
        $rules = HoldingRule::query()->where('is_active', true)->get()->keyBy('sender_name');
        $destinationIds = $pallets->pluck('consignment.destination_depot_id')->filter()->unique()->values()->all();
        $nearestDepartures = $this->nearestDepartures($destinationIds);

        $statuses = $pallets->map(function (HandlingUnit $pallet) use ($heldSince, $rules, $nearestDepartures): array {
            $consignment = $pallet->consignment;
            $rule = $consignment?->sender_name !== null ? $rules->get($consignment->sender_name) : null;
            $since = $heldSince[$pallet->getKey()] ?? null;
            $nearest = $consignment?->destination_depot_id !== null
                ? $nearestDepartures[$consignment->destination_depot_id] ?? null
                : null;

            $facts = new HoldingPalletFacts(
                daysHeld: $since === null ? null : (int) $since->diffInDays(now()),
                maxHoldDays: $rule?->max_hold_days,
                minutesToNearestDeparture: $nearest === null
                    ? null
                    : (int) round(now()->diffInSeconds($nearest['departs_at'], false) / 60),
                nearestDepartureCode: $nearest['code'] ?? null,
            );

            return [
                'location_id' => $pallet->current_location_id,
                'status' => $this->readiness->forPallet($facts),
            ];
        });

        $rank = ['settled' => 0, 'opportunity' => 1, 'overdue' => 2];

        return $lanes
            ->map(function ($lane) use ($statuses, $rank): ?array {
                $here = $statuses->filter(fn (array $row): bool => $row['location_id'] === $lane->getKey());

                if ($here->isEmpty()) {
                    return null;
                }

                $worstRow = $here->sortByDesc(fn (array $row) => $rank[$row['status']->status])->first();

                if ($worstRow === null) {
                    return null;
                }

                return [
                    'id' => $lane->getKey(),
                    'code' => $lane->code,
                    'lane' => $lane->lane,
                    'pallet_count' => $here->count(),
                    'overdue_count' => $here->filter(fn (array $row): bool => $row['status']->status === 'overdue')->count(),
                    'opportunity_count' => $here->filter(fn (array $row): bool => $row['status']->status === 'opportunity')->count(),
                    'status' => $worstRow['status']->status,
                    'reasons' => $worstRow['status']->reasons,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * When each pallet arrived at wherever it currently is. The latest
     * staged/moved event is always the one that set its current location,
     * since both are written together in the same transaction.
     *
     * @param  list<string>  $handlingUnitIds
     * @return array<string, Carbon>
     */
    private function heldSince(array $handlingUnitIds): array
    {
        return DB::table('operational_events')
            ->whereIn('handling_unit_id', $handlingUnitIds)
            ->whereIn('event_type', [EventType::Staged->value, EventType::Moved->value])
            ->groupBy('handling_unit_id')
            ->selectRaw('handling_unit_id, max(occurred_at) as held_since')
            ->pluck('held_since', 'handling_unit_id')
            ->map(fn (string $at) => Carbon::parse($at))
            ->all();
    }

    /**
     * The soonest open manifest's departure serving each destination depot.
     *
     * @param  list<string>  $destinationDepotIds
     * @return array<string, array{departs_at: string, code: string}>
     */
    private function nearestDepartures(array $destinationDepotIds): array
    {
        if ($destinationDepotIds === []) {
            return [];
        }

        return DB::table('manifests')
            ->join('manifest_destinations', 'manifest_destinations.manifest_id', '=', 'manifests.id')
            ->where('manifests.status', 'open')
            ->whereNotNull('manifests.departs_at')
            ->whereIn('manifest_destinations.destination_depot_id', $destinationDepotIds)
            ->orderBy('manifests.departs_at')
            ->get([
                'manifest_destinations.destination_depot_id as destination_depot_id',
                'manifests.departs_at',
                'manifests.manifest_number',
            ])
            // Rows arrive soonest-departure first, so the first one kept per
            // destination is the nearest.
            ->unique('destination_depot_id')
            ->mapWithKeys(fn (object $row): array => [
                $row->destination_depot_id => ['departs_at' => $row->departs_at, 'code' => $row->manifest_number],
            ])
            ->all();
    }
}
