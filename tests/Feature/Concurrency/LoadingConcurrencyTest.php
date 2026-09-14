<?php

use App\Actions\Loading\LoadHandlingUnit;
use App\Exceptions\Loading\HandlingUnitAlreadyAssigned;
use App\Models\Consignment;
use App\Models\Depot;
use App\Models\HandlingUnit;
use App\Models\Manifest;
use App\Models\ManifestItem;
use App\Models\OperationalEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

it('allows exactly one concurrent loader to assign a pallet', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('This integration test requires MySQL.');
    }

    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('This integration test requires pcntl.');
    }

    $destination = Depot::factory()->create();
    $firstManifest = Manifest::factory()->create(['status' => 'open']);
    $secondManifest = Manifest::factory()->create(['status' => 'open']);
    $firstManifest->destinations()->attach($destination, ['is_primary' => true]);
    $secondManifest->destinations()->attach($destination, ['is_primary' => true]);

    $consignment = Consignment::factory()->create([
        'destination_depot_id' => $destination->getKey(),
        'item_count' => 1,
    ]);
    $pallet = HandlingUnit::factory()->for($consignment)->create();
    $firstLoader = User::factory()->create();
    $secondLoader = User::factory()->create();

    $resultFiles = [
        tempnam(sys_get_temp_dir(), 'loading-race-'),
        tempnam(sys_get_temp_dir(), 'loading-race-'),
    ];
    $jobs = [
        [$firstManifest, $firstLoader, $resultFiles[0]],
        [$secondManifest, $secondLoader, $resultFiles[1]],
    ];
    $children = [];

    foreach ($jobs as [$manifest, $loader, $resultFile]) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork concurrency test worker.');
        }

        if ($pid === 0) {
            DB::purge();
            $payload = null;

            try {
                $assignment = app(LoadHandlingUnit::class)->handle(
                    manifest: $manifest->fresh(),
                    handlingUnit: $pallet->fresh(),
                    loader: $loader->fresh(),
                    clientEventId: (string) Str::uuid(),
                    occurredAt: now(),
                );
                $payload = [
                    'result' => 'assigned',
                    'assignment_id' => $assignment->getKey(),
                ];
            } catch (HandlingUnitAlreadyAssigned $exception) {
                $payload = [
                    'result' => 'conflict',
                    'exception' => $exception::class,
                ];
            } catch (Throwable $exception) {
                $payload = [
                    'result' => 'error',
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ];
            }

            file_put_contents($resultFile, json_encode($payload, JSON_THROW_ON_ERROR));
            exit(0);
        }

        $children[] = $pid;
    }

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $results = collect($resultFiles)
        ->map(function (string $file): array {
            $contents = file_get_contents($file);
            unlink($file);

            return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        });

    expect($results->where('result', 'assigned'))->toHaveCount(1)
        ->and($results->where('result', 'conflict'))->toHaveCount(1)
        ->and($results->where('result', 'error'))->toHaveCount(0)
        ->and(ManifestItem::query()->count())->toBe(1)
        ->and(OperationalEvent::query()->count())->toBe(1)
        ->and($pallet->fresh()->current_status->value)->toBe('loaded');
});
