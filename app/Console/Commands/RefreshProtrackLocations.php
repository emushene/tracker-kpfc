<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RefreshProtrackLocations extends Command
{
    protected $signature = 'protrack:refresh-locations';

    protected $description = 'Refresh Protrack positions, resolve locations, and sync them to Google Sheets';

    public function handle(): int
    {
        $this->info('Starting Protrack location refresh...');

        // Step 1: Synchronize latest GPS positions from Protrack365.
        $this->info('1/3 Synchronizing Protrack positions...');

        $exitCode = Artisan::call('protrack:sync-positions');

        $this->output->write(Artisan::output());

        if ($exitCode !== Command::SUCCESS) {
            $this->error('Position synchronization failed.');

            return Command::FAILURE;
        }

        // Step 2: Resolve coordinates into human-readable locations.
        $this->info('2/3 Resolving vehicle locations...');

        $exitCode = Artisan::call('vehicles:update-locations');

        $this->output->write(Artisan::output());

        if ($exitCode !== Command::SUCCESS) {
            $this->error('Location resolution failed.');

            return Command::FAILURE;
        }

        // Step 3: Update the existing Google Sheet rows.
        $this->info('3/3 Synchronizing locations to Google Sheets...');

        $exitCode = Artisan::call('protrack:sync-google-sheets');

        $this->output->write(Artisan::output());

        if ($exitCode !== Command::SUCCESS) {
            $this->error('Google Sheets synchronization failed.');

            return Command::FAILURE;
        }

        $this->info('Protrack location refresh completed successfully.');

        return Command::SUCCESS;
    }
}

