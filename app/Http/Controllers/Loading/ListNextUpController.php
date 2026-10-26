<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Support\Loading\NextUp;
use Illuminate\Http\JsonResponse;

class ListNextUpController extends Controller
{
    public function __invoke(Manifest $manifest, NextUp $nextUp): JsonResponse
    {
        return response()->json($nextUp->for($manifest));
    }
}
