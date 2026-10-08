<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateManifestItemPositionController extends Controller
{
    /** Record (or clear, with nulls) where a pallet sits on the trailer. */
    public function __invoke(Request $request, Manifest $manifest, HandlingUnit $handlingUnit): JsonResponse
    {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'unit' => ['nullable', 'integer', 'min:1', 'required_with:row,side'],
            'row' => ['nullable', 'integer', 'min:1', 'required_with:unit,side'],
            'side' => ['nullable', Rule::in(['D', 'P']), 'required_with:unit,row'],
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

        $item->forceFill([
            'trailer_unit' => $unit,
            'trailer_row' => $row,
            'trailer_side' => $side,
        ])->save();

        return response()->json([
            'data' => [
                'position' => $unit === null ? null : ['unit' => $unit, 'row' => $row, 'side' => $side],
            ],
        ]);
    }
}
