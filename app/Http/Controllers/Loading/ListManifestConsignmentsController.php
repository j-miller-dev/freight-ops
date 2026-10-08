<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Support\Loading\ManifestConsignmentBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ListManifestConsignmentsController extends Controller
{
    public function __invoke(
        Request $request,
        Manifest $manifest,
        ManifestConsignmentBoard $board,
    ): JsonResponse {
        $validated = $request->validate([
            'filter' => ['sometimes', Rule::in(ManifestConsignmentBoard::FILTERS)],
        ]);

        return response()->json(
            $board->paginate($manifest, $validated['filter'] ?? 'all', perPage: 6),
        );
    }
}
