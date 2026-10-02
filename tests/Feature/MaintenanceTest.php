<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\DriverChecklistSubmission;
use App\Models\DriverChecklistSubmissionItem;
use App\Models\InventoryPart;
use App\Models\JobCardChecklist;
use App\Models\JobCardChecklistItem;
use App\Models\JobCardPart;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMileage;
use App\Models\VehicleRepair;
use App\Models\VehicleReplacement;
use App\Notifications\MaintenanceDueNotification;
use Database\Seeders\ChecklistTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_schedule_can_be_created_for_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create();

        $schedule = MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'active' => true,
        ]);

        $this->assertDatabaseHas('maintenance_schedules', [
            'vehicle_id' => $vehicle->id,
            'service_name' => 'Oil Change',
            'schedule_type' => 'mileage',
        ]);
    }

    public function test_mileage_check_calculates_next_service_and_creates_ticket_in_alert_window(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleMileage::create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-01',
            'odometer' => 149500,
        ]);
        $schedule = MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'last_service_km' => 140000,
            'alert_threshold_km' => 500,
            'active' => true,
        ]);
        Notification::fake();

        Artisan::call('maintenance:check');

        $this->assertSame(150000, $schedule->fresh()->next_service_km);
        $this->assertDatabaseHas('maintenance_tickets', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Scheduled Maintenance: Oil Change',
            'status' => 'open',
        ]);
        Notification::assertSentOnDemand(MaintenanceDueNotification::class);
    }

    public function test_mileage_check_uses_current_odometer_when_last_service_mileage_is_missing(): void
    {
        $vehicle = Vehicle::factory()->create();
        VehicleMileage::create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-01',
            'odometer' => 50000,
        ]);
        $schedule = MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'alert_threshold_km' => 500,
            'active' => true,
        ]);
        Notification::fake();

        Artisan::call('maintenance:check');

        $this->assertSame(60000, $schedule->fresh()->next_service_km);
        $this->assertSame(0, MaintenanceTicket::where('vehicle_id', $vehicle->id)->count());
        Notification::assertNothingSent();
    }

    public function test_closing_scheduled_ticket_advances_next_service_from_latest_odometer(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleMileage::create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-01',
            'odometer' => 151200,
        ]);
        MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'last_service_km' => 140000,
            'next_service_km' => 150000,
            'active' => true,
        ]);
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'service',
            'title' => 'Scheduled Maintenance: Oil Change',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/maintenance/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertOk();

        $this->assertDatabaseHas('maintenance_schedules', [
            'vehicle_id' => $vehicle->id,
            'service_name' => 'Oil Change',
            'last_service_km' => 151200,
            'next_service_km' => 161200,
        ]);
    }

    public function test_closing_manual_ticket_does_not_advance_maintenance_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        VehicleMileage::create([
            'vehicle_id' => $vehicle->id,
            'date' => '2026-10-01',
            'odometer' => 151200,
        ]);
        MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'last_service_km' => 140000,
            'next_service_km' => 150000,
            'active' => true,
        ]);
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
            'title' => 'Repair front suspension',
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/maintenance/tickets/{$ticket->id}", ['status' => 'closed'])
            ->assertOk();

        $this->assertDatabaseHas('maintenance_schedules', [
            'vehicle_id' => $vehicle->id,
            'service_name' => 'Oil Change',
            'last_service_km' => 140000,
            'next_service_km' => 150000,
        ]);
    }

    public function test_maintenance_alert_is_linked_to_vehicle_and_schedule(): void
    {
        $vehicle = Vehicle::factory()->create();

        $schedule = MaintenanceSchedule::create([
            'vehicle_id' => $vehicle->id,
            'schedule_type' => 'mileage',
            'service_name' => 'Oil Change',
            'interval_km' => 10000,
            'active' => true,
        ]);

        $alert = MaintenanceAlert::create([
            'vehicle_id' => $vehicle->id,
            'maintenance_schedule_id' => $schedule->id,
            'alert_type' => 'service_due',
            'title' => 'Oil Change Due',
            'message' => 'Your vehicle is due for an oil change.',
            'status' => 'active',
        ]);

        $this->assertEquals($vehicle->id, $alert->vehicle->id);
        $this->assertEquals($schedule->id, $alert->maintenanceSchedule->id);
    }

    public function test_maintenance_ticket_belongs_to_vehicle(): void
    {
        $ticket = MaintenanceTicket::factory()->create();

        $this->assertInstanceOf(Vehicle::class, $ticket->vehicle);
        $this->assertEquals($ticket->vehicle_id, $ticket->vehicle->id);
    }

    public function test_ticket_api_persists_ticket_for_selected_database_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create(['plate_number' => 'DB 123A']);

        $response = $this->actingAs($admin)->postJson('/api/maintenance/tickets', [
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
            'title' => 'Brake inspection',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.vehicle_id', $vehicle->id)
            ->assertJsonPath('data.vehicle.plate_number', 'DB 123A');

        $this->assertDatabaseHas('maintenance_tickets', [
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
            'status' => 'open',
            'title' => 'Brake inspection',
        ]);
    }

    public function test_maintenance_ticket_has_correct_types(): void
    {
        $validTypes = ['repair', 'inspection', 'service', 'diagnostic'];

        foreach ($validTypes as $type) {
            $ticket = MaintenanceTicket::factory()->create(['ticket_type' => $type]);
            $this->assertContains($ticket->ticket_type, $validTypes);
        }
    }

    public function test_maintenance_job_card_belongs_to_ticket_and_vehicle(): void
    {
        $ticket = MaintenanceTicket::factory()->create();

        $jobCard = MaintenanceJobCard::factory()->create([
            'maintenance_ticket_id' => $ticket->id,
            'vehicle_id' => $ticket->vehicle_id,
        ]);

        $this->assertEquals($ticket->id, $jobCard->maintenanceTicket->id);
        $this->assertEquals($ticket->vehicle_id, $jobCard->vehicle->id);
    }

    public function test_checklist_template_has_items(): void
    {
        $template = ChecklistTemplate::factory()->create();

        ChecklistItem::create([
            'checklist_template_id' => $template->id,
            'sequence' => 1,
            'label' => 'Check oil level',
            'required' => true,
        ]);

        ChecklistItem::create([
            'checklist_template_id' => $template->id,
            'sequence' => 2,
            'label' => 'Check tyre pressure',
            'required' => true,
        ]);

        $this->assertCount(2, $template->checklistItems);
    }

    public function test_checklist_seeder_persists_both_forms_relationally_and_is_idempotent(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $this->seed(ChecklistTemplateSeeder::class);

        $driverTemplate = ChecklistTemplate::query()
            ->where('template_key', 'driver_daily')
            ->with(['checklistItems', 'fields.options'])
            ->firstOrFail();
        $mechanicTemplate = ChecklistTemplate::query()
            ->where('template_key', 'mechanic_service')
            ->with(['checklistItems', 'fields.options'])
            ->firstOrFail();

        $this->assertSame('driver', $driverTemplate->role);
        $this->assertCount(10, $driverTemplate->checklistItems);
        $this->assertCount(9, $driverTemplate->fields);
        $this->assertCount(3, $driverTemplate->fields->firstWhere('field_key', 'driver_vehicle_status')->options);
        $this->assertCount(15, $mechanicTemplate->checklistItems);
        $this->assertCount(11, $mechanicTemplate->fields);
        $this->assertSame(2, ChecklistTemplate::query()->count());

        $this->assertDatabaseHas('checklist_items', [
            'checklist_template_id' => $mechanicTemplate->id,
            'item_key' => 'mech_recheck',
            'section_title' => 'Completion and release',
        ]);
    }

    public function test_job_card_checklist_is_a_snapshot_of_template(): void
    {
        $template = ChecklistTemplate::factory()->create(['name' => 'Full Service Checklist']);

        $jobCard = MaintenanceJobCard::factory()->create();

        $snapshot = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'checklist_template_id' => $template->id,
            'template_name' => $template->name,
            'template_category' => $template->category,
        ]);

        $this->assertEquals('Full Service Checklist', $snapshot->template_name);
        $this->assertEquals($jobCard->id, $snapshot->maintenanceJobCard->id);
    }

    public function test_job_card_creation_persists_a_checklist_snapshot_and_items(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        $template = ChecklistTemplate::factory()->create([
            'name' => 'Routine Service',
            'category' => 'service',
        ]);
        ChecklistItem::create([
            'checklist_template_id' => $template->id,
            'sequence' => 1,
            'label' => 'Inspect brakes',
            'description' => 'Check pads and discs',
            'required' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/maintenance/job-cards', [
            'vehicle_id' => $vehicle->id,
            'checklist_template_id' => $template->id,
            'reported_problem' => 'Scheduled service',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.job_card_checklists.0.template_name', 'Routine Service')
            ->assertJsonPath('data.job_card_checklists.0.job_card_checklist_items.0.label', 'Inspect brakes');

        $this->assertDatabaseHas('job_card_checklists', [
            'checklist_template_id' => $template->id,
            'template_name' => 'Routine Service',
        ]);
        $this->assertDatabaseHas('job_card_checklist_items', [
            'sequence' => 1,
            'label' => 'Inspect brakes',
            'required' => true,
            'is_checked' => false,
        ]);
    }

    public function test_job_card_creation_selects_checklist_template_from_ticket_type(): void
    {
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'service',
        ]);
        $template = ChecklistTemplate::factory()->create([
            'name' => 'Routine Service',
            'category' => 'service',
        ]);
        ChecklistItem::create([
            'checklist_template_id' => $template->id,
            'sequence' => 1,
            'label' => 'Inspect brakes',
            'required' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/maintenance/job-cards', [
            'vehicle_id' => $vehicle->id,
            'maintenance_ticket_id' => $ticket->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.job_card_checklists.0.template_name', 'Routine Service')
            ->assertJsonPath('data.job_card_checklists.0.job_card_checklist_items.0.is_checked', false);
    }

    public function test_job_card_creation_falls_back_to_active_mechanic_template_for_repair_ticket(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
        ]);

        $this->actingAs($admin)
            ->postJson('/api/maintenance/job-cards', [
                'vehicle_id' => $vehicle->id,
                'maintenance_ticket_id' => $ticket->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.job_card_checklists.0.template_name', 'Isuzu Truck Service & Mechanic Checklist')
            ->assertJsonCount(15, 'data.job_card_checklists.0.job_card_checklist_items');
    }

    public function test_existing_job_card_can_attach_mechanic_checklist_without_creating_duplicates(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
        ]);
        $jobCard = MaintenanceJobCard::factory()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_ticket_id' => $ticket->id,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/checklists")
            ->assertCreated()
            ->assertJsonPath('data.job_card_checklists.0.template_name', 'Isuzu Truck Service & Mechanic Checklist')
            ->assertJsonCount(15, 'data.job_card_checklists.0.job_card_checklist_items');

        $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/checklists")
            ->assertOk();

        $this->assertDatabaseCount('job_card_checklists', 1);
        $this->assertDatabaseCount('job_card_checklist_items', 15);
    }

    public function test_job_card_with_no_checklist_can_attach_the_mechanic_template(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $admin = User::factory()->admin()->create();
        $vehicle = Vehicle::factory()->create();
        $ticket = MaintenanceTicket::factory()->create([
            'vehicle_id' => $vehicle->id,
            'ticket_type' => 'repair',
        ]);
        $jobCard = MaintenanceJobCard::factory()->create([
            'vehicle_id' => $vehicle->id,
            'maintenance_ticket_id' => $ticket->id,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/checklists")
            ->assertCreated()
            ->assertJsonPath('data.job_card_checklists.0.template_name', 'Isuzu Truck Service & Mechanic Checklist')
            ->assertJsonCount(15, 'data.job_card_checklists.0.job_card_checklist_items');
    }

    public function test_ticket_list_includes_linked_job_card_count(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = MaintenanceTicket::factory()->create();
        MaintenanceJobCard::factory()->create([
            'maintenance_ticket_id' => $ticket->id,
            'vehicle_id' => $ticket->vehicle_id,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/maintenance/tickets')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ticket->id)
            ->assertJsonPath('data.0.maintenance_job_cards_count', 1);
    }

    public function test_job_card_checklist_item_update_persists_checked_state(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create();
        $checklist = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'template_name' => 'Routine Service',
        ]);
        $item = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 1,
            'label' => 'Inspect brakes',
            'required' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/maintenance/job-card-checklist-items/{$item->id}", [
                'is_checked' => true,
                'notes' => 'Pads and discs checked',
            ])
            ->assertOk()
            ->assertJsonPath('data.is_checked', true);

        $this->assertDatabaseHas('job_card_checklist_items', [
            'id' => $item->id,
            'is_checked' => true,
            'notes' => 'Pads and discs checked',
        ]);
    }

    public function test_job_card_checklist_item_preserves_fail_result(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create();
        $checklist = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'template_name' => 'Routine Service',
        ]);
        $item = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 1,
            'label' => 'Inspect brakes',
            'required' => true,
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/maintenance/job-card-checklist-items/{$item->id}", ['result' => 'fail'])
            ->assertOk()
            ->assertJsonPath('data.result', 'fail');

        $this->assertDatabaseHas('job_card_checklist_items', [
            'id' => $item->id,
            'is_checked' => false,
            'result' => 'fail',
        ]);
    }

    public function test_fleet_manager_can_review_driver_checklist_submissions(): void
    {
        $admin = User::factory()->admin()->create();
        $template = ChecklistTemplate::factory()->create([
            'template_key' => 'driver_daily',
            'role' => 'driver',
        ]);
        $vehicle = Vehicle::factory()->create(['plate_number' => 'KDG 456Z']);
        $submission = DriverChecklistSubmission::create([
            'checklist_template_id' => $template->id,
            'vehicle_id' => $vehicle->id,
            'driver_external_user_id' => 'driver-17',
            'submission_date' => '2026-10-01',
            'odometer' => 62450,
            'vehicle_status' => 'Needs attention',
            'defects' => 'Right indicator not working',
            'action_taken' => 'Fleet manager notified',
            'submitted_at' => now(),
        ]);
        DriverChecklistSubmissionItem::create([
            'driver_checklist_submission_id' => $submission->id,
            'item_key' => 'drv_lights',
            'section_title' => 'Walk-around',
            'sequence' => 1,
            'label' => 'Lights and indicators',
            'required' => true,
            'result' => 'fail',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/maintenance/driver-checklist-submissions?vehicle_id='.$vehicle->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle.plate_number', 'KDG 456Z')
            ->assertJsonPath('data.0.items.0.result', 'fail');
    }

    public function test_vehicle_repair_is_linked_to_vehicle(): void
    {
        $repair = VehicleRepair::factory()->create();

        $this->assertInstanceOf(Vehicle::class, $repair->vehicle);
        $this->assertEquals($repair->vehicle_id, $repair->vehicle->id);
    }

    public function test_vehicle_replacement_is_linked_to_vehicle(): void
    {
        $replacement = VehicleReplacement::factory()->create();

        $this->assertInstanceOf(Vehicle::class, $replacement->vehicle);
        $this->assertEquals($replacement->vehicle_id, $replacement->vehicle->id);
    }

    public function test_vehicle_has_maintenance_relationships(): void
    {
        $vehicle = Vehicle::factory()->create();

        MaintenanceSchedule::factory()->create(['vehicle_id' => $vehicle->id]);
        MaintenanceTicket::factory()->create(['vehicle_id' => $vehicle->id]);
        VehicleRepair::factory()->create(['vehicle_id' => $vehicle->id]);
        VehicleReplacement::factory()->create(['vehicle_id' => $vehicle->id]);

        $vehicle->refresh();

        $this->assertCount(1, $vehicle->maintenanceSchedules);
        $this->assertCount(1, $vehicle->maintenanceTickets);
        $this->assertCount(1, $vehicle->repairs);
        $this->assertCount(1, $vehicle->replacements);
    }

    public function test_checklist_item_supports_repaired_and_replaced_results(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create();
        $checklist = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'template_name' => 'Routine Service',
        ]);
        $item = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 1,
            'label' => 'Check alternator belt',
            'required' => true,
        ]);

        // Test repaired
        $this->actingAs($admin)
            ->patchJson("/api/maintenance/job-card-checklist-items/{$item->id}", [
                'result' => 'repaired',
                'notes' => 'Belt adjusted and tightened',
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'repaired')
            ->assertJsonPath('data.is_checked', true);

        $this->assertDatabaseHas('job_card_checklist_items', [
            'id' => $item->id,
            'result' => 'repaired',
            'is_checked' => true,
        ]);

        // Test replaced
        $this->actingAs($admin)
            ->patchJson("/api/maintenance/job-card-checklist-items/{$item->id}", [
                'result' => 'replaced',
                'notes' => 'Fitted new serpentine belt',
            ])
            ->assertOk()
            ->assertJsonPath('data.result', 'replaced')
            ->assertJsonPath('data.is_checked', true);
    }

    public function test_inventory_part_can_be_attached_to_job_card_and_deducts_inventory(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create(['status' => 'in_progress']);
        $checklist = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'template_name' => 'Brake Overhaul',
        ]);
        $item = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 1,
            'label' => 'Front Brake Pads',
            'required' => true,
            'result' => 'fail',
        ]);

        $part = InventoryPart::factory()->create([
            'name' => 'Heavy Duty Brake Pad Set',
            'quantity' => 10,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/parts", [
                'inventory_part_id' => $part->id,
                'quantity' => 2,
                'job_card_checklist_item_id' => $item->id,
                'notes' => 'Replaced worn front pads',
            ]);

        $response->assertCreated()
            ->assertJsonPath('remaining_stock', 8)
            ->assertJsonPath('data.inventory_part.name', 'Heavy Duty Brake Pad Set')
            ->assertJsonPath('data.quantity', 2);

        // Verify stock deducted
        $this->assertEquals(8, $part->fresh()->quantity);

        // Verify inventory movement recorded
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_part_id' => $part->id,
            'movement_type' => 'job_card_usage',
            'quantity' => -2,
            'balance_after' => 8,
            'reference_type' => 'maintenance_job_card',
            'reference_id' => $jobCard->id,
        ]);

        // Verify checklist item was marked as replaced
        $this->assertEquals('replaced', $item->fresh()->result);
        $this->assertTrue($item->fresh()->is_checked);

        // Verify replacement logged on vehicle
        $this->assertDatabaseHas('vehicle_replacements', [
            'vehicle_id' => $jobCard->vehicle_id,
            'maintenance_job_card_id' => $jobCard->id,
            'part_name' => 'Heavy Duty Brake Pad Set',
        ]);
    }

    public function test_insufficient_inventory_stock_prevents_part_attachment(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create();
        $part = InventoryPart::factory()->create([
            'name' => 'Oil Filter',
            'quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/parts", [
                'inventory_part_id' => $part->id,
                'quantity' => 3,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);

        // Stock unchanged
        $this->assertEquals(1, $part->fresh()->quantity);
    }

    public function test_job_card_part_can_be_detached_and_restores_inventory(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create();
        $part = InventoryPart::factory()->create(['quantity' => 5]);

        $jobCardPart = JobCardPart::create([
            'maintenance_job_card_id' => $jobCard->id,
            'inventory_part_id' => $part->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/maintenance/job-cards/{$jobCard->id}/parts/{$jobCardPart->id}");

        $response->assertOk()
            ->assertJsonPath('remaining_stock', 7);

        $this->assertEquals(7, $part->fresh()->quantity);
        $this->assertDatabaseMissing('job_card_parts', ['id' => $jobCardPart->id]);
        $this->assertDatabaseHas('inventory_movements', [
            'inventory_part_id' => $part->id,
            'movement_type' => 'return',
            'quantity' => 2,
            'balance_after' => 7,
        ]);
    }

    public function test_batch_save_checklist_updates_items_and_completes_job_card(): void
    {
        $admin = User::factory()->admin()->create();
        $jobCard = MaintenanceJobCard::factory()->create(['status' => 'open']);
        $checklist = JobCardChecklist::create([
            'maintenance_job_card_id' => $jobCard->id,
            'template_name' => 'Full Service',
        ]);
        $item1 = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 1,
            'label' => 'Engine Oil',
            'result' => 'unchecked',
        ]);
        $item2 = JobCardChecklistItem::create([
            'job_card_checklist_id' => $checklist->id,
            'sequence' => 2,
            'label' => 'Brake Fluid',
            'result' => 'unchecked',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/maintenance/job-cards/{$jobCard->id}/save-checklist", [
                'status' => 'completed',
                'work_performed' => 'Full service conducted, oil replaced, brake fluid topped up',
                'diagnosis' => 'Vehicle in good overall mechanical health',
                'items' => [
                    ['id' => $item1->id, 'result' => 'pass', 'notes' => 'Synthetic 5W-30 filled'],
                    ['id' => $item2->id, 'result' => 'repaired', 'notes' => 'Bleed valve tightened'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertEquals('completed', $jobCard->fresh()->status);
        $this->assertNotNull($jobCard->fresh()->completed_at);
        $this->assertEquals('pass', $item1->fresh()->result);
        $this->assertTrue($item1->fresh()->is_checked);
        $this->assertEquals('repaired', $item2->fresh()->result);
        $this->assertTrue($item2->fresh()->is_checked);
    }
}
