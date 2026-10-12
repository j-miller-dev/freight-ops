<?php

namespace App\Support\Unload;

use App\Models\HandlingUnit;
use App\Models\Location;
use Illuminate\Support\Collection;

class DirectionPayload
{
    /**
     * @param  Collection<int, Location>|null  $alternatives  other lanes to offer when the suggested one is full
     * @return array<string, mixed>
     */
    public static function for(
        HandlingUnit $handlingUnit,
        BayDirection $direction,
        ?Collection $alternatives = null,
    ): array {
        $consignment = $handlingUnit->consignment;

        return [
            'handling_unit_id' => $handlingUnit->getKey(),
            'barcode' => $handlingUnit->barcode,
            'piece_number' => $handlingUnit->piece_number,
            'connote_number' => $consignment->connote_number,
            'item_count' => $consignment->item_count,
            'sender_name' => $consignment->sender_name,
            'dg_class' => $handlingUnit->dg_class,
            'un_number' => $handlingUnit->un_number,
            'is_food' => $handlingUnit->is_food,
            'current_location_code' => $handlingUnit->currentLocation?->code,
            'bay' => [
                'location_id' => $direction->location->getKey(),
                'code' => $direction->location->code,
                'name' => $direction->location->name,
                'is_holding' => $direction->isHolding,
                'max_hold_days' => $direction->maxHoldDays,
                'lane' => $direction->lane,
            ],
            'reason' => $direction->reason,
            'suggested_lane' => $direction->suggested?->lane,
            'alternatives' => ($alternatives ?? collect())
                ->map(fn (Location $location): array => [
                    'location_id' => $location->getKey(),
                    'code' => $location->code,
                    'lane' => $location->lane,
                ])
                ->values()
                ->all(),
        ];
    }
}
