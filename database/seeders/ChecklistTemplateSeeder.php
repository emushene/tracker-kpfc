<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateField;
use App\Models\ChecklistTemplateFieldOption;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedTemplate([
                'template_key' => 'driver_daily',
                'name' => 'Isuzu Truck Daily Driver Checklist & Report',
                'category' => 'driver_daily',
                'role' => 'driver',
                'frequency' => 'Before the first trip each day',
                'description' => 'Daily visual and cab safety checks completed by the assigned driver.',
                'sections' => [
                    [
                        'title' => 'Walk-around',
                        'items' => [
                            ['key' => 'drv_tyres', 'label' => 'Tyres and wheels', 'description' => 'Look for visibly low or damaged tyres, loose wheel parts, or missing wheel nuts. Do not attempt repairs.'],
                            ['key' => 'drv_leaks', 'label' => 'Leaks or visible damage', 'description' => 'Look under the vehicle and around the body for fresh leaks or damage.'],
                            ['key' => 'drv_lights', 'label' => 'Lights and indicators', 'description' => 'Check headlights, indicators, hazard lights, brake lights, and reflectors.'],
                            ['key' => 'drv_visibility', 'label' => 'Visibility', 'description' => 'Check mirrors, windscreen, wipers, and washers are usable and clear.'],
                            ['key' => 'drv_load', 'label' => 'Load and doors', 'description' => 'Confirm the load is secure and doors, tailgate, and access steps are safe.'],
                        ],
                    ],
                    [
                        'title' => 'Cab and start-up',
                        'items' => [
                            ['key' => 'drv_seatbelt', 'label' => 'Seat belt', 'description' => 'Check the seat belt is present and fastens correctly.'],
                            ['key' => 'drv_dash', 'label' => 'Dashboard warnings', 'description' => 'After starting, note any warning light or message that remains on.'],
                            ['key' => 'drv_horn', 'label' => 'Horn', 'description' => 'Check that the horn works.'],
                            ['key' => 'drv_brakes', 'label' => 'Brakes and steering feel', 'description' => 'At low speed in a safe area, note any unusual brake response, steering, vibration, or noise.'],
                            ['key' => 'drv_fuel', 'label' => 'Fuel and AdBlue / DEF', 'description' => 'Confirm there is enough fuel and exhaust fluid for the planned trip.'],
                        ],
                    ],
                ],
                'fields' => [
                    ['key' => 'vehicle_reg', 'group' => 'metadata', 'label' => 'Vehicle Reg/ID', 'type' => 'text', 'required' => true],
                    ['key' => 'date', 'group' => 'metadata', 'label' => 'Date', 'type' => 'date', 'required' => true],
                    ['key' => 'odometer', 'group' => 'metadata', 'label' => 'Odometer (km)', 'type' => 'number', 'required' => true],
                    ['key' => 'driver_name', 'group' => 'metadata', 'label' => 'Driver name', 'type' => 'text', 'required' => true],
                    ['key' => 'route', 'group' => 'metadata', 'label' => 'Route / assignment', 'type' => 'text', 'required' => false],
                    ['key' => 'driver_vehicle_status', 'group' => 'report', 'label' => 'Vehicle status', 'type' => 'select', 'required' => true, 'options' => ['Ready for trip', 'Needs attention', 'Do not drive']],
                    ['key' => 'driver_defects', 'group' => 'report', 'label' => 'Defects or concerns found', 'type' => 'textarea', 'required' => false],
                    ['key' => 'driver_action', 'group' => 'report', 'label' => 'Action taken / person notified', 'type' => 'textarea', 'required' => false],
                    ['key' => 'driver_signature', 'group' => 'report', 'label' => 'Driver sign-off', 'type' => 'text', 'required' => true],
                ],
            ]);

            $this->seedTemplate([
                'template_key' => 'mechanic_service',
                'name' => 'Isuzu Truck Service & Mechanic Checklist',
                'category' => 'service',
                'role' => 'mechanic',
                'frequency' => 'At scheduled service or repair',
                'description' => 'Scheduled service, safety inspection, and vehicle release checks for Isuzu trucks.',
                'legacy_name' => '10,000 KM Routine Vehicle Service',
                'sections' => [
                    [
                        'title' => 'Scheduled service',
                        'items' => [
                            ['key' => 'mech_oil', 'label' => 'Engine oil and filter', 'description' => 'Drain and replace engine oil and oil filter to the manufacturer specification.'],
                            ['key' => 'mech_fuel_filter', 'label' => 'Fuel filter and sedimenter', 'description' => 'Service or replace filters as required; drain water from the sedimenter and prime the fuel system.'],
                            ['key' => 'mech_air_filter', 'label' => 'Air intake filter', 'description' => 'Inspect the air filter and housing; clean or replace as required.'],
                            ['key' => 'mech_fluids', 'label' => 'Fluids and leaks', 'description' => 'Check coolant, brake, clutch, steering, transmission, and differential fluids; inspect for leaks.'],
                            ['key' => 'mech_lubrication', 'label' => 'Chassis lubrication', 'description' => 'Lubricate service points according to the vehicle maintenance schedule.'],
                            ['key' => 'mech_belts_hoses', 'label' => 'Belts and hoses', 'description' => 'Inspect condition, security, and tension; replace damaged components as authorized.'],
                            ['key' => 'mech_battery', 'label' => 'Battery and charging', 'description' => 'Inspect terminals and mounting; test battery and charging system where required.'],
                        ],
                    ],
                    [
                        'title' => 'Safety and condition inspection',
                        'items' => [
                            ['key' => 'mech_brakes', 'label' => 'Brakes and air system', 'description' => 'Inspect brake pads or linings, discs or drums, parking brake, and air system; record defects.'],
                            ['key' => 'mech_tyres', 'label' => 'Tyres and wheel fasteners', 'description' => 'Check tyre condition and wear; verify wheel fasteners using the approved procedure and torque specification.'],
                            ['key' => 'mech_suspension', 'label' => 'Suspension and steering', 'description' => 'Inspect springs, dampers, joints, steering links, and mounts for wear or damage.'],
                            ['key' => 'mech_lights', 'label' => 'Lights and electrical items', 'description' => 'Verify exterior lights, indicators, warning lamps, horn, and relevant electrical connections.'],
                            ['key' => 'mech_exhaust', 'label' => 'Exhaust and emissions system', 'description' => 'Inspect exhaust security and visible leaks; check DPF / emissions warnings where fitted.'],
                        ],
                    ],
                    [
                        'title' => 'Completion and release',
                        'items' => [
                            ['key' => 'mech_recheck', 'label' => 'Post-service leak and warning check', 'description' => 'Run the engine and recheck for leaks, abnormal noise, and warning lights.'],
                            ['key' => 'mech_road_test', 'label' => 'Road test', 'description' => 'Complete a controlled road test when safe and authorized; record any remaining concern.'],
                            ['key' => 'mech_tools_removed', 'label' => 'Vehicle ready for release', 'description' => 'Confirm tools and materials are removed and any outstanding defect is documented.'],
                        ],
                    ],
                ],
                'fields' => [
                    ['key' => 'vehicle_reg', 'group' => 'metadata', 'label' => 'Vehicle Reg/ID', 'type' => 'text', 'required' => true],
                    ['key' => 'service_date', 'group' => 'metadata', 'label' => 'Service date', 'type' => 'date', 'required' => true],
                    ['key' => 'service_odometer', 'group' => 'metadata', 'label' => 'Odometer at service (km)', 'type' => 'number', 'required' => true],
                    ['key' => 'mechanic_name', 'group' => 'metadata', 'label' => 'Mechanic / technician', 'type' => 'text', 'required' => true],
                    ['key' => 'work_order', 'group' => 'metadata', 'label' => 'Work order / ticket number', 'type' => 'text', 'required' => false],
                    ['key' => 'next_service_odometer', 'group' => 'metadata', 'label' => 'Next service due at (km)', 'type' => 'number', 'required' => false],
                    ['key' => 'mechanic_work_done', 'group' => 'report', 'label' => 'Work performed', 'type' => 'textarea', 'required' => false],
                    ['key' => 'mechanic_parts', 'group' => 'report', 'label' => 'Parts and fluids used', 'type' => 'textarea', 'required' => false],
                    ['key' => 'mechanic_defects', 'group' => 'report', 'label' => 'Defects found / follow-up required', 'type' => 'textarea', 'required' => false],
                    ['key' => 'mechanic_release_status', 'group' => 'report', 'label' => 'Release status', 'type' => 'select', 'required' => true, 'options' => ['Service complete', 'Released with follow-up', 'Do not release']],
                    ['key' => 'mechanic_signature', 'group' => 'report', 'label' => 'Mechanic sign-off', 'type' => 'text', 'required' => true],
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function seedTemplate(array $definition): void
    {
        $template = ChecklistTemplate::query()
            ->where('template_key', $definition['template_key'])
            ->first();

        if (! $template && isset($definition['legacy_name'])) {
            $template = ChecklistTemplate::query()
                ->where('name', $definition['legacy_name'])
                ->first();
        }

        $template ??= new ChecklistTemplate;
        $template->fill([
            'template_key' => $definition['template_key'],
            'name' => $definition['name'],
            'category' => $definition['category'],
            'role' => $definition['role'],
            'frequency' => $definition['frequency'],
            'description' => $definition['description'],
            'active' => true,
        ]);
        $template->save();

        $itemSequence = 0;
        foreach ($definition['sections'] as $section) {
            foreach ($section['items'] as $item) {
                $itemSequence++;
                ChecklistItem::query()->updateOrCreate([
                    'checklist_template_id' => $template->id,
                    'sequence' => $itemSequence,
                ], [
                    'item_key' => $item['key'],
                    'section_title' => $section['title'],
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'required' => true,
                ]);
            }
        }

        $template->checklistItems()->where('sequence', '>', $itemSequence)->delete();

        $fieldKeys = [];
        foreach ($definition['fields'] as $fieldSequence => $fieldDefinition) {
            $fieldKeys[] = $fieldDefinition['key'];
            $field = ChecklistTemplateField::query()->updateOrCreate([
                'checklist_template_id' => $template->id,
                'field_key' => $fieldDefinition['key'],
            ], [
                'field_group' => $fieldDefinition['group'],
                'label' => $fieldDefinition['label'],
                'field_type' => $fieldDefinition['type'],
                'required' => $fieldDefinition['required'],
                'sequence' => $fieldSequence + 1,
            ]);

            $options = $fieldDefinition['options'] ?? [];
            foreach ($options as $optionSequence => $option) {
                ChecklistTemplateFieldOption::query()->updateOrCreate([
                    'checklist_template_field_id' => $field->id,
                    'option_value' => $option,
                ], [
                    'label' => $option,
                    'sequence' => $optionSequence + 1,
                ]);
            }

            $field->options()->whereNotIn('option_value', $options)->delete();
        }

        $template->fields()->whereNotIn('field_key', $fieldKeys)->delete();
    }
}
