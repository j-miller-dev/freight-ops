<?php

namespace App\Http\Controllers\Loading;

use App\Exceptions\Loading\ManifestNotOpen;
use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\ManifestUnitLabel;
use App\Support\Loading\LoadCategories;
use App\Support\Loading\ManifestSegregation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateManifestUnitLabelsController extends Controller
{
    /**
     * Say what a trailer unit (1 = A, 2 = B, 3 = C) is carrying, so the crew
     * can see it and the segregation rules know where freight is. Replaces the
     * unit's whole label set.
     */
    public function __invoke(
        Request $request,
        Manifest $manifest,
        int $unit,
        ManifestSegregation $segregation,
    ): JsonResponse {
        Gate::authorize('load', $manifest);

        $validated = $request->validate([
            'categories' => ['present', 'array', 'max:20'],
            'categories.*' => ['string', Rule::in(LoadCategories::keys())],
        ]);

        if ($unit < 1 || $unit > count($manifest->trailer_type->rows())) {
            throw ValidationException::withMessages([
                'unit' => 'That part of the trailer does not exist.',
            ]);
        }

        $categories = array_values(array_unique($validated['categories']));

        DB::transaction(function () use ($manifest, $unit, $categories, $request): void {
            $locked = Manifest::query()->whereKey($manifest->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'open') {
                throw new ManifestNotOpen($locked);
            }

            ManifestUnitLabel::query()
                ->where('manifest_id', $manifest->getKey())
                ->where('unit', $unit)
                ->whereNotIn('category', $categories)
                ->delete();

            $existing = ManifestUnitLabel::query()
                ->where('manifest_id', $manifest->getKey())
                ->where('unit', $unit)
                ->pluck('category')
                ->all();

            foreach (array_diff($categories, $existing) as $category) {
                $label = new ManifestUnitLabel;
                $label->manifest_id = $manifest->getKey();
                $label->unit = $unit;
                $label->category = $category;
                $label->set_by = $request->user()->getKey();
                $label->save();
            }
        });

        return response()->json(['data' => ['segregation' => $segregation->for($manifest)]]);
    }
}
