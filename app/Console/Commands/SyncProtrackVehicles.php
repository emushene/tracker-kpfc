<?php

namespace App\Console\Commands;

use App\Services\Protrack\ProtrackClient;
use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;


class SyncProtrackVehicles extends Command
{
    protected $signature = 'protrack:sync-vehicles';

    protected $description = 'Synchronize Protrack vehicles into the database';

    public function handle(ProtrackClient $protrack): int
    {
        $this->info('Synchronizing Protrack vehicles...');

        try {
            $count = $protrack->syncVehicles();

            $this->info(
                "Successfully synchronized {$count} vehicles."
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error(
                'Vehicle synchronization failed: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}