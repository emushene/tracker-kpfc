<?php

namespace App\Console\Commands;

use App\Services\Protrack\ProtrackClient;
use Illuminate\Console\Command;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;


class SyncProtrackPositions extends Command
{
    protected $signature = 'protrack:sync-positions';

    protected $description = 'Synchronize live Protrack positions';

    public function handle(ProtrackClient $protrack): int
    {
        $this->info('Synchronizing vehicle positions...');

        try {
            $count = $protrack->syncPositions();

            $this->info(
                "Successfully synchronized {$count} positions."
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error(
                'Position synchronization failed: ' .
                $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
