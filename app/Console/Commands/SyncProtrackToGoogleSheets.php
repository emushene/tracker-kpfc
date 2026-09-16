<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;
use Throwable;

class SyncProtrackToGoogleSheets extends Command
{
    protected $signature = 'protrack:sync-google-sheets';

    protected $description = 'Synchronize vehicle locations to the Protrack365 Google Sheet';

    public function handle(GoogleSheetsService $google): int
    {
        $this->info('Synchronizing vehicle locations to Google Sheets...');

        try {
            $rows = $google->readVehicles();

            if (empty($rows)) {
                $this->error('The Protrack365 sheet is empty.');
                return self::FAILURE;
            }

            /*
             * Build IMEI => Google Sheet row number.
             *
             * Row 1 is the header.
             * Google Sheets row numbers start at 1.
             */
            $imeiRows = [];

            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                $imei = trim($row[2] ?? '');

                if ($imei !== '') {
                    $imeiRows[$imei] = $index + 1;
                }
            }

            $vehicles = Vehicle::query()
                ->whereNotNull('imei')
                ->where('imei', '!=', '')
                ->get();

            $updated = 0;
            $skipped = 0;
            $notFound = 0;

            foreach ($vehicles as $vehicle) {
                $imei = trim($vehicle->imei);

                if (!isset($imeiRows[$imei])) {
                    $this->warn(
                        "IMEI {$imei} not found in Google Sheet."
                    );

                    $notFound++;
                    continue;
                }

                $location = trim((string) $vehicle->location_name);

                if ($location === '') {
                    $skipped++;
                    continue;
                }

                $rowNumber = $imeiRows[$imei];

                /*
                 * Update ONLY column F (Location).
                 */
                $google->updateLocation(
                    $rowNumber,
                    $location
                );

                $updated++;

                $this->line(
                    "{$vehicle->plate_number} ({$imei}) → {$location}"
                );
            }

            $this->info("Updated: {$updated}");
            $this->info("Skipped: {$skipped}");
            $this->info("IMEI not found: {$notFound}");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Google Sheets synchronization failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}