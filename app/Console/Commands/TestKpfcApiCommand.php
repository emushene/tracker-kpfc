<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KpfcAdminDirectoryService;

class TestKpfcApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpfc:test-api {--directory=branches : Which directory to test (branches or suppliers)} {--inactive : Include inactive records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the KPFC Admin API integration and dump the payload';

    /**
     * Execute the console command.
     */
    public function handle(KpfcAdminDirectoryService $service)
    {
        $directory = $this->option('directory');
        $includeInactive = $this->option('inactive');

        $this->info("Fetching {$directory} directory...");

        try {
            if ($directory === 'branches') {
                $data = $service->getBranches($includeInactive);
            } elseif ($directory === 'suppliers') {
                $data = $service->getSuppliers($includeInactive);
            } else {
                $this->error("Invalid directory specified. Use 'branches' or 'suppliers'.");
                return Command::FAILURE;
            }

            $this->info("Successfully fetched data! Payload:");
            
            // Limit output if it's very large, just show meta and first few items
            $preview = [
                'meta' => $data['meta'] ?? null,
                'data_count' => count($data['data'] ?? []),
                'sample_data' => array_slice($data['data'] ?? [], 0, 3)
            ];

            $this->line(json_encode($preview, JSON_PRETTY_PRINT));
            
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("API Call Failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
