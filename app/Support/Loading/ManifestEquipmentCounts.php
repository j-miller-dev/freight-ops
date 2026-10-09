<?php

namespace App\Support\Loading;

use App\Enums\TrailerEquipment;
use App\Models\Manifest;

class ManifestEquipmentCounts
{
    /**
     * Every equipment item for a manifest, zero where nothing is recorded.
     *
     * @return array<string, int>
     */
    public function for(Manifest $manifest): array
    {
        $recorded = $manifest->equipment()->pluck('quantity', 'item');
        $counts = [];

        foreach (TrailerEquipment::cases() as $item) {
            $counts[$item->value] = (int) ($recorded[$item->value] ?? 0);
        }

        return $counts;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function items(): array
    {
        return array_map(
            fn (TrailerEquipment $item): array => ['key' => $item->value, 'label' => $item->label()],
            TrailerEquipment::cases(),
        );
    }
}
