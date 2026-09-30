<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\KpfcAdminDirectoryService;
use App\Models\Shop;
use App\Models\Supplier;
use Carbon\Carbon;

class SyncKpfcDirectoriesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpfc:sync-directories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize Branch and Supplier directories from KPFC Admin';

    /**
     * Execute the console command.
     */
    public function handle(KpfcAdminDirectoryService $service)
    {
        $this->info('Starting directory synchronization from KPFC Admin...');

        try {
            $this->syncBranches($service);
            $this->syncSuppliers($service);
            
            $this->info('Synchronization completed successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function syncBranches(KpfcAdminDirectoryService $service)
    {
        $this->info('Fetching Branches (Shops)...');
        
        // Always include inactive for full reconciliation per the integration guide
        $response = $service->getBranches(true);
        $branches = $response['data'] ?? [];

        $this->info('Upserting ' . count($branches) . ' branches...');

        $upsertData = [];
        $now = Carbon::now();

        foreach ($branches as $branch) {
            $upsertData[] = [
                'id' => $branch['id'],
                'code' => $branch['name'],          // Machine-oriented name maps to code
                'name' => $branch['display_name'],  // Human-readable maps to name
                'active' => $branch['is_active'],
                'latitude' => $branch['latitude'],
                'longitude' => $branch['longitude'],
                'updated_at' => $now,
            ];
        }

        // Chunking the upsert in case the directory is very large
        foreach (array_chunk($upsertData, 500) as $chunk) {
            Shop::upsert(
                $chunk,
                ['id'], // Unique columns
                ['code', 'name', 'active', 'latitude', 'longitude', 'updated_at'] // Update these columns if exists
            );
        }
    }

    private function syncSuppliers(KpfcAdminDirectoryService $service)
    {
        $this->info('Fetching Suppliers...');
        
        $response = $service->getSuppliers(true);
        $suppliers = $response['data'] ?? [];

        $this->info('Upserting ' . count($suppliers) . ' suppliers...');

        $upsertData = [];
        $now = Carbon::now();

        foreach ($suppliers as $supplier) {
            $upsertData[] = [
                'id' => $supplier['id'],
                'name' => $supplier['name'],
                'active' => $supplier['is_active'],
                'latitude' => $supplier['latitude'],
                'longitude' => $supplier['longitude'],
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($upsertData, 500) as $chunk) {
            Supplier::upsert(
                $chunk,
                ['id'], // Unique columns
                ['name', 'active', 'latitude', 'longitude', 'updated_at'] // Update these columns if exists
            );
        }
    }
}
