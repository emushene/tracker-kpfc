<?php

namespace Tests\Feature;

use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\JobCardChecklist;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use App\Models\VehicleRepair;
use App\Models\VehicleReplacement;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
