<?php

namespace App\Console\Commands;

use App\Actions\Loading\SyncManifests;
use App\Contracts\ManifestSource;
use Illuminate\Console\Command;

class SyncManifestsCommand extends Command
{
    protected $signature = 'manifests:sync';

    protected $description = 'Sync manifests from the configured source';

    public function handle(SyncManifests $action, ManifestSource $source): void
    {
        $action->handle($source);

        $this->info('Manifests synced successfully.');
    }
}
