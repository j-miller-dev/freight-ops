<?php

use App\Exceptions\Loading\ClientEventConflict;
use App\Exceptions\Loading\ConsignmentSplit;
use App\Exceptions\Loading\DestinationMismatch;
use App\Exceptions\Loading\HandlingUnitAlreadyAssigned;
use App\Exceptions\Loading\ManifestNotOpen;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The loading page routes are ordinary Inertia visits, so they keep
        // the default HTML/redirect error handling; only the scan/list/simulate
        // endpoints behind them speak JSON.
        $isLoadingApi = fn (Request $request): bool => $request->routeIs('loading.*')
            && ! $request->routeIs('loading.index', 'loading.depot', 'loading.manifest');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson()
                || $isLoadingApi($request),
        );

        $exceptions->render(function (
            Throwable $exception,
            Request $request,
        ) use ($isLoadingApi) {
            if (! $isLoadingApi($request)) {
                return null;
            }

            $error = match (true) {
                $exception instanceof ManifestNotOpen => [
                    'code' => 'manifest_not_open',
                    'message' => $exception->getMessage(),
                    'status' => 409,
                ],

                $exception instanceof DestinationMismatch => [
                    'code' => 'destination_mismatch',
                    'message' => $exception->getMessage(),
                    'status' => 422,
                    'details' => [
                        'destination_code' => $exception->palletDestination->code,
                        'destination_name' => $exception->palletDestination->name,
                        'manifest_number' => $exception->selectedManifest->manifest_number,
                    ],
                ],

                $exception instanceof HandlingUnitAlreadyAssigned => [
                    'code' => 'handling_unit_already_assigned',
                    'message' => $exception->getMessage(),
                    'status' => 409,
                    'details' => (function () use ($exception): array {
                        $assignment = $exception->existingAssignment
                            ->loadMissing(['manifest', 'loader']);

                        return [
                            'previous_manifest_number' => $assignment->manifest->manifest_number,
                            'previous_loader_name' => $assignment->loader->name,
                            'previous_loaded_at' => $assignment->loaded_at?->toISOString(),
                            'selected_manifest_number' => $exception->selectedManifest->manifest_number,
                        ];
                    })(),
                ],

                $exception instanceof ConsignmentSplit => [
                    'code' => 'consignment_split',
                    'message' => $exception->getMessage(),
                    'status' => 409,
                    'details' => [
                        'total_count' => $exception->consignment->item_count,
                        // This consignment's pallets on the selected manifest, plus the one being scanned.
                        'on_selected_after_scan' => $exception->selectedManifest->manifestItems()
                            ->whereHas('handlingUnit', fn ($query) => $query->where('consignment_id', $exception->consignment->getKey()))
                            ->count() + 1,
                        'conflicts' => $exception->conflictsByManifest()
                            ->map(fn (array $conflict) => [
                                'manifest_number' => $conflict['manifest']->manifest_number,
                                'pallet_count' => $conflict['pallet_count'],
                            ])
                            ->values()
                            ->all(),
                    ],
                ],

                $exception instanceof ClientEventConflict => [
                    'code' => 'client_event_conflict',
                    'message' => $exception->getMessage(),
                    'status' => 409,
                ],

                default => null,
            };

            if ($error === null) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => $error['code'],
                    'message' => $error['message'],
                    'details' => $error['details'] ?? [],
                ],
            ], $error['status']);
        });
    })->create();
