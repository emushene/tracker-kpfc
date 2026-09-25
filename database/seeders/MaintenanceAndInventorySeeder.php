<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\JobCardPart;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\Tool;
use App\Models\ToolAssignment;
use App\Models\Vehicle;
use App\Models\VehicleRepair;
use Illuminate\Database\Seeder;

class MaintenanceAndInventorySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Categories
        $mechCat = InventoryCategory::firstOrCreate(
            ['slug' => 'mechanical'],
            ['name' => 'Mechanical Parts', 'type' => 'part', 'description' => 'Powertrain, braking, steering and engine components']
        );

        $cosmCat = InventoryCategory::firstOrCreate(
            ['slug' => 'cosmetic-body'],
            ['name' => 'Cosmetic & Body', 'type' => 'part', 'description' => 'Lighting, mirrors, body panels, trims and wipers']
        );

        $toolCat = InventoryCategory::firstOrCreate(
            ['slug' => 'workshop-tools'],
            ['name' => 'Workshop & Diagnostic Tools', 'type' => 'tool', 'description' => 'Diagnostics, torque tools, jacks and heavy gear']
        );

        // 2. Inventory Parts
        $parts = [
            [
                'part_number' => 'PRT-OIL-5W30',
                'name' => 'Engine Oil Synthetic 5W-30 (1L)',
                'inventory_category_id' => $mechCat->id,
                'category' => 'mechanical',
                'quantity' => 45,
                'minimum_quantity' => 15,
                'unit_of_measure' => 'liter',
                'location' => 'Bay 1 - Shelf A',
                'unit_cost_reference' => 'KES 1,200',
            ],
            [
                'part_number' => 'PRT-FLT-OIL01',
                'name' => 'Heavy Duty Oil Filter',
                'inventory_category_id' => $mechCat->id,
                'category' => 'mechanical',
                'quantity' => 8,
                'minimum_quantity' => 12, // LOW STOCK!
                'unit_of_measure' => 'piece',
                'location' => 'Bin M-12',
                'unit_cost_reference' => 'KES 850',
            ],
            [
                'part_number' => 'PRT-BRK-FR01',
                'name' => 'Front Ceramic Brake Pad Set',
                'inventory_category_id' => $mechCat->id,
                'category' => 'mechanical',
                'quantity' => 4,
                'minimum_quantity' => 10, // LOW STOCK!
                'unit_of_measure' => 'set',
                'location' => 'Rack B-03',
                'unit_cost_reference' => 'KES 3,500',
            ],
            [
                'part_number' => 'PRT-BLT-ALT02',
                'name' => 'Alternator Drive Belt',
                'inventory_category_id' => $mechCat->id,
                'category' => 'mechanical',
                'quantity' => 18,
                'minimum_quantity' => 5,
                'unit_of_measure' => 'piece',
                'location' => 'Hook M-05',
                'unit_cost_reference' => 'KES 1,100',
            ],
            [
                'part_number' => 'PRT-MIR-LH01',
                'name' => 'Left-hand Side Wing Mirror',
                'inventory_category_id' => $cosmCat->id,
                'category' => 'cosmetic_body',
                'quantity' => 3,
                'minimum_quantity' => 4, // LOW STOCK!
                'unit_of_measure' => 'piece',
                'location' => 'C-Rack 02',
                'unit_cost_reference' => 'KES 4,200',
            ],
            [
                'part_number' => 'PRT-LGT-H4',
                'name' => 'H4 Halogen Headlamp Bulb (Pair)',
                'inventory_category_id' => $cosmCat->id,
                'category' => 'cosmetic_body',
                'quantity' => 24,
                'minimum_quantity' => 8,
                'unit_of_measure' => 'set',
                'location' => 'Cabinet Elec-1',
                'unit_cost_reference' => 'KES 750',
            ],
            [
                'part_number' => 'PRT-WPR-24IN',
                'name' => 'All-Weather Wiper Blades 24"',
                'inventory_category_id' => $cosmCat->id,
                'category' => 'cosmetic_body',
                'quantity' => 14,
                'minimum_quantity' => 6,
                'unit_of_measure' => 'pair',
                'location' => 'Bin C-09',
                'unit_cost_reference' => 'KES 900',
            ],
        ];

        foreach ($parts as $partData) {
            $part = InventoryPart::updateOrCreate(
                ['part_number' => $partData['part_number']],
                $partData
            );

            if ($part->wasRecentlyCreated) {
                InventoryMovement::create([
                    'inventory_part_id' => $part->id,
                    'movement_type' => 'restock',
                    'quantity' => $part->quantity,
                    'balance_after' => $part->quantity,
                    'reference_type' => 'initial_seed',
                    'notes' => 'Initial workshop stock inventory',
                ]);
            }
        }

        // 3. Tools
        $tools = [
            [
                'tool_code' => 'TL-OBD-01',
                'name' => 'Autel MaxiCOM Diagnostic Scanner',
                'category' => 'diagnostic',
                'availability_status' => 'available',
                'condition' => 'good',
                'serial_number' => 'AUT-984210',
                'location' => 'Tool Crib Locker 1',
                'inventory_category_id' => $toolCat->id,
            ],
            [
                'tool_code' => 'TL-TRQ-02',
                'name' => 'Norbar 1/2" Torque Wrench (60-300Nm)',
                'category' => 'torque',
                'availability_status' => 'assigned',
                'condition' => 'good',
                'serial_number' => 'NOR-77218',
                'location' => 'Tool Crib Locker 2',
                'inventory_category_id' => $toolCat->id,
            ],
            [
                'tool_code' => 'TL-JCK-03',
                'name' => 'Hydraulic Trolley Jack 3.5 Ton',
                'category' => 'workshop',
                'availability_status' => 'available',
                'condition' => 'good',
                'serial_number' => 'JCK-35T-09',
                'location' => 'Bay 2 Floor',
                'inventory_category_id' => $toolCat->id,
            ],
            [
                'tool_code' => 'TL-IMP-04',
                'name' => 'DeWalt Cordless High-Torque Impact Wrench',
                'category' => 'workshop',
                'availability_status' => 'available',
                'condition' => 'good',
                'serial_number' => 'DW-18V-4421',
                'location' => 'Charging Bench',
                'inventory_category_id' => $toolCat->id,
            ],
            [
                'tool_code' => 'TL-BLD-05',
                'name' => 'Pneumatic Brake Fluid Bleeder Kit',
                'category' => 'specialized',
                'availability_status' => 'under_maintenance',
                'condition' => 'fair',
                'serial_number' => 'BLEED-119',
                'location' => 'Repair Bench',
                'inventory_category_id' => $toolCat->id,
            ],
        ];

        foreach ($tools as $toolData) {
            $tool = Tool::updateOrCreate(['tool_code' => $toolData['tool_code']], $toolData);

            if ($tool->availability_status === 'assigned' && ! $tool->currentAssignment) {
                ToolAssignment::create([
                    'tool_id' => $tool->id,
                    'mechanic_external_user_id' => 'MECH-042 (John Mwangi)',
                    'assigned_at' => now()->subHours(3),
                    'expected_return_at' => now()->addHours(5),
                    'notes' => 'Checked out for rear axle suspension retorque',
                ]);
            }
        }

        // 4. Checklist Template
        $template = ChecklistTemplate::firstOrCreate(
            ['name' => '10,000 KM Routine Vehicle Service'],
            [
                'category' => 'service',
                'description' => 'Comprehensive preventative maintenance checklist covering engine, brakes and safety items',
                'active' => true,
            ]
        );

        $checklistItems = [
            'Inspect and top up brake, coolant, and washer fluids',
            'Drain and replace engine oil and oil filter',
            'Inspect brake pads and rotor thickness',
            'Check tyre pressures (including spare) and tread depth',
            'Inspect front suspension, ball joints, and tie rods',
            'Test all exterior lighting and indicator bulbs',
        ];

        foreach ($checklistItems as $idx => $label) {
            ChecklistItem::firstOrCreate([
                'checklist_template_id' => $template->id,
                'sequence' => $idx + 1,
            ], [
                'label' => $label,
                'required' => true,
            ]);
        }

        // 5. Connect to some vehicles if vehicles exist
        $vehicles = Vehicle::take(5)->get();
        if ($vehicles->isNotEmpty()) {
            $v1 = $vehicles[0];
            $v2 = $vehicles->count() > 1 ? $vehicles[1] : $v1;

            // Schedule & Alert
            $sched = MaintenanceSchedule::firstOrCreate([
                'vehicle_id' => $v1->id,
                'service_name' => '10,000 KM Engine Oil & Filter',
            ], [
                'schedule_type' => 'mileage',
                'interval_km' => 10000,
                'last_service_km' => 140000,
                'last_service_at' => now()->subMonths(2),
                'next_service_km' => 150000,
                'alert_threshold_km' => 1000,
                'active' => true,
            ]);

            MaintenanceAlert::firstOrCreate([
                'vehicle_id' => $v1->id,
                'maintenance_schedule_id' => $sched->id,
            ], [
                'alert_type' => 'service_due',
                'title' => 'Scheduled 10,000 KM Service Approaching',
                'message' => 'Vehicle is within 750 km of next engine lubrication and filter replacement interval.',
                'current_km' => 149250,
                'threshold_km' => 150000,
                'status' => 'active',
            ]);

            // Ticket 1
            $ticket = MaintenanceTicket::firstOrCreate([
                'vehicle_id' => $v1->id,
                'title' => 'Excessive brake squeal and vibration on braking',
            ], [
                'ticket_number' => 'TCK-2026-0089',
                'ticket_type' => 'repair',
                'status' => 'in_progress',
                'description' => 'Driver reported pronounced squeal from front left axle when decelerating above 40 km/h.',
                'reported_by_external_user_id' => 'DRV-102',
                'assigned_to_external_user_id' => 'MECH-042 (John Mwangi)',
                'priority' => 'high',
                'opened_at' => now()->subHours(6),
            ]);

            // Job Card
            $jc = MaintenanceJobCard::firstOrCreate([
                'vehicle_id' => $v1->id,
                'job_card_number' => 'JC-2026-0045',
            ], [
                'maintenance_ticket_id' => $ticket->id,
                'mechanic_external_user_id' => 'MECH-042 (John Mwangi)',
                'mileage_at_service' => 149250,
                'reported_problem' => 'Front brake pad wear warning & vibration',
                'diagnosis' => 'Front brake pads worn down to 2mm. Disc rotor glazed but within spec.',
                'work_performed' => 'Replaced front ceramic pads, deglazed rotors, bled brake lines.',
                'status' => 'in_progress',
                'started_at' => now()->subHours(2),
            ]);

            $padPart = InventoryPart::where('part_number', 'PRT-BRK-FR01')->first();
            if ($padPart) {
                JobCardPart::firstOrCreate([
                    'maintenance_job_card_id' => $jc->id,
                    'inventory_part_id' => $padPart->id,
                ], [
                    'quantity' => 1,
                    'allocated_by_external_user_id' => 'MECH-042',
                    'notes' => 'Fitted to front axle caliper carriers',
                ]);
            }

            // Ticket 2 on vehicle 2
            MaintenanceTicket::firstOrCreate([
                'vehicle_id' => $v2->id,
                'title' => 'Routine 30-day safety & lighting inspection',
            ], [
                'ticket_number' => 'TCK-2026-0092',
                'ticket_type' => 'inspection',
                'status' => 'open',
                'description' => 'Standard monthly safety checklist before inter-branch dispatch run.',
                'priority' => 'normal',
                'opened_at' => now()->subDay(),
            ]);

            // Repair log
            VehicleRepair::firstOrCreate([
                'vehicle_id' => $v2->id,
                'problem' => 'Broken passenger side wing mirror glass',
            ], [
                'diagnosis' => 'Mirror housing intact, glass fractured',
                'repair_performed' => 'Replaced mirror glass insert and recalibrated electric adjust motor',
                'mileage_at_repair' => 84100,
                'repaired_at' => now()->subDays(5)->toDateString(),
                'cost_reference' => 'KES 4,200',
            ]);
        }
    }
}
