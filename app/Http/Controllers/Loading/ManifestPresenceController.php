<?php

namespace App\Http\Controllers\Loading;

use App\Http\Controllers\Controller;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\ManifestPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ManifestPresenceController extends Controller
{
    /**
     * Heartbeat from an open manifest page: record that this person is here,
     * and answer with who else has been here recently. There is no websocket
     * service in production, so the page just asks again every few seconds.
     */
    public function __invoke(Request $request, Manifest $manifest): JsonResponse
    {
        Gate::authorize('load', $manifest);

        $me = $request->user();
        $now = now();

        ManifestPresence::query()->upsert(
            [[
                'manifest_id' => $manifest->getKey(),
                'user_id' => $me->getKey(),
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['manifest_id', 'user_id'],
            ['last_seen_at', 'updated_at'],
        );

        $others = ManifestPresence::query()
            ->where('manifest_id', $manifest->getKey())
            ->where('user_id', '!=', $me->getKey())
            ->where('last_seen_at', '>=', $now->copy()->subSeconds(ManifestPresence::ACTIVE_FOR_SECONDS))
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();

        $loadedBy = ManifestItem::query()
            ->where('manifest_id', $manifest->getKey())
            ->whereIn('loaded_by', $others->pluck('user_id'))
            ->selectRaw('loaded_by, count(*) as loaded')
            ->groupBy('loaded_by')
            ->pluck('loaded', 'loaded_by');

        return response()->json([
            'data' => $others
                ->map(fn (ManifestPresence $presence): array => [
                    'id' => $presence->user_id,
                    'name' => $presence->user->name,
                    'loaded_count' => (int) ($loadedBy[$presence->user_id] ?? 0),
                ])
                ->values(),
        ]);
    }
}
