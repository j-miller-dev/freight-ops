<?php

namespace App\Http\Controllers\Loading;

use App\Enums\LoadWarningType;
use App\Exceptions\Loading\SegregationClash;
use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\WarningAcknowledgement;
use App\Support\Loading\ManifestSegregation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateManifestItemPositionController extends Controller
{
    /** Record (or clear, with nulls) where a pallet sits on the trailer. */
    public function __invoke(
        Request $request,
        Manifest $manifest,
        HandlingUnit $handlingUnit,
        ManifestSegregation $segregation,
    ): JsonResponse {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'unit' => ['nullable', 'integer', 'min:1', 'required_with:row,side'],
            'row' => ['nullable', 'integer', 'min:1', 'required_with:unit,side'],
            'side' => ['nullable', Rule::in(['D', 'P']), 'required_with:unit,row'],
            'acknowledged_warnings' => ['sometimes', 'array'],
            'acknowledged_warnings.*' => [Rule::enum(LoadWarningType::class)],
        ]);

        $item = ManifestItem::query()
            ->where('manifest_id', $manifest->getKey())
            ->where('handling_unit_id', $handlingUnit->getKey())
            ->firstOrFail();

        $unit = $validated['unit'] ?? null;
        $row = $validated['row'] ?? null;
        $side = $validated['side'] ?? null;

        if ($unit !== null) {
            $rows = $manifest->trailer_type->rows();

            if (! isset($rows[$unit - 1]) || $row > $rows[$unit - 1]) {
                throw ValidationException::withMessages([
                    'row' => 'That position does not exist on this trailer.',
                ]);
            }

            $occupant = ManifestItem::query()
                ->where('manifest_id', $manifest->getKey())
                ->where('trailer_unit', $unit)
                ->where('trailer_row', $row)
                ->where('trailer_side', $side)
                ->where('id', '!=', $item->getKey())
                ->with('handlingUnit:id,barcode')
                ->first();

            if ($occupant) {
                return response()->json([
                    'error' => [
                        'code' => 'position_taken',
                        'message' => 'That position is already taken by '.$occupant->handlingUnit->barcode.'.',
                        'status' => 409,
                    ],
                ], 409);
            }
        }

        // Putting a pallet where it breaks a rule needs the loader's say-so.
        // Taking it off the map (no unit) can never cause a clash.
        $issues = $unit === null ? [] : $segregation->introducedByPlacement($manifest, $handlingUnit, $unit);

        if ($issues !== [] && ! in_array(
            LoadWarningType::SegregationClash->value,
            $validated['acknowledged_warnings'] ?? [],
            true,
        )) {
            throw new SegregationClash($handlingUnit, $manifest, $issues);
        }

        $item->forceFill([
            'trailer_unit' => $unit,
            'trailer_row' => $row,
            'trailer_side' => $side,
        ])->save();

        if ($issues !== []) {
            $acknowledgement = new WarningAcknowledgement;
            $acknowledgement->warning_type = LoadWarningType::SegregationClash;
            $acknowledgement->handling_unit_id = $handlingUnit->getKey();
            $acknowledgement->manifest_id = $manifest->getKey();
            $acknowledgement->acknowledged_by = $request->user()->getKey();
            $acknowledgement->client_event_id = (string) Str::uuid();
            $acknowledgement->acknowledged_at = now();
            $acknowledgement->metadata = [
                'placement' => ['unit' => $unit, 'row' => $row, 'side' => $side],
                'is_food' => $handlingUnit->is_food,
                'dg_class' => $handlingUnit->dg_class,
                'issues' => $issues,
            ];
            $acknowledgement->save();
        }

        return response()->json([
            'data' => [
                'position' => $unit === null ? null : ['unit' => $unit, 'row' => $row, 'side' => $side],
            ],
        ]);
    }
}
