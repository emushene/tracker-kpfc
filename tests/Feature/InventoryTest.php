<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\JobCardPart;
use App\Models\MaintenanceJobCard;
use App\Models\Tool;
use App\Models\ToolAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_category_can_be_created_and_has_parts_and_tools(): void
    {
        $category = InventoryCategory::factory()->create([
            'name' => 'Brake Systems',
            'slug' => 'brake-systems',
            'type' => 'part',
        ]);

        $part = InventoryPart::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Front Brake Pads',
        ]);

        $tool = Tool::factory()->create([
            'inventory_category_id' => $category->id,
            'name' => 'Brake Caliper Tool',
        ]);

        $this->assertCount(1, $category->parts);
        $this->assertCount(1, $category->tools);
        $this->assertEquals('Brake Systems', $part->inventoryCategory->name);
        $this->assertEquals('Brake Systems', $tool->inventoryCategory->name);
    }

    public function test_inventory_part_supports_mechanical_and_cosmetic_categories(): void
    {
        $mechanicalPart = InventoryPart::factory()->mechanical()->create([
            'quantity' => 15,
            'minimum_quantity' => 5,
            'location' => 'Shelf M-01',
        ]);

        $cosmeticPart = InventoryPart::factory()->cosmetic()->create([
            'quantity' => 8,
            'minimum_quantity' => 2,
            'location' => 'Shelf C-04',
        ]);

        $this->assertEquals('mechanical', $mechanicalPart->category);
        $this->assertEquals('cosmetic_body', $cosmeticPart->category);
        $this->assertDatabaseHas('inventory_parts', [
            'id' => $mechanicalPart->id,
            'category' => 'mechanical',
            'location' => 'Shelf M-01',
        ]);
        $this->assertDatabaseHas('inventory_parts', [
            'id' => $cosmeticPart->id,
            'category' => 'cosmetic_body',
            'location' => 'Shelf C-04',
        ]);
    }

    public function test_inventory_part_detects_low_stock(): void
    {
        $adequatePart = InventoryPart::factory()->create([
            'quantity' => 20,
            'minimum_quantity' => 10,
        ]);

        $lowStockPart = InventoryPart::factory()->lowStock()->create();

        $this->assertFalse($adequatePart->isLowStock());
        $this->assertTrue($lowStockPart->isLowStock());
    }

    public function test_inventory_part_scope_low_stock(): void
    {
        InventoryPart::factory()->create([
            'quantity' => 50,
            'minimum_quantity' => 10,
        ]);

        InventoryPart::factory()->lowStock()->create();
        InventoryPart::factory()->outOfStock()->create();

        $lowStockParts = InventoryPart::lowStock()->get();

        $this->assertCount(2, $lowStockParts);
    }

    public function test_inventory_movement_records_stock_changes(): void
    {
        $part = InventoryPart::factory()->create([
            'quantity' => 30,
        ]);

        $movement = InventoryMovement::create([
            'inventory_part_id' => $part->id,
            'movement_type' => 'job_card_usage',
            'quantity' => -2,
            'balance_after' => 28,
            'reference_type' => 'maintenance_job_card',
            'reference_id' => 101,
            'actor_external_user_id' => 'USR-452',
            'notes' => 'Installed during routine inspection',
        ]);

        $this->assertInstanceOf(InventoryPart::class, $movement->inventoryPart);
        $this->assertEquals($part->id, $movement->inventoryPart->id);
        $this->assertEquals(-2, $movement->quantity);
        $this->assertEquals(28, $movement->balance_after);
    }

    public function test_job_card_parts_are_linked_to_job_card_and_part(): void
    {
        $jobCard = MaintenanceJobCard::factory()->create();
        $part = InventoryPart::factory()->create([
            'name' => 'Oil Filter Premium',
            'quantity' => 10,
        ]);

        $jobCardPart = JobCardPart::create([
            'maintenance_job_card_id' => $jobCard->id,
            'inventory_part_id' => $part->id,
            'quantity' => 1,
            'allocated_by_external_user_id' => 'MECH-007',
            'notes' => 'Fitted during scheduled maintenance',
        ]);

        $this->assertEquals($jobCard->id, $jobCardPart->maintenanceJobCard->id);
        $this->assertEquals($part->id, $jobCardPart->inventoryPart->id);
        $this->assertDatabaseHas('job_card_parts', [
            'maintenance_job_card_id' => $jobCard->id,
            'inventory_part_id' => $part->id,
            'quantity' => 1,
        ]);
    }

    public function test_tool_can_be_created_with_availability_and_condition(): void
    {
        $tool = Tool::factory()->available()->create([
            'name' => 'Torque Wrench 1/2 Inch',
            'category' => 'torque',
            'condition' => 'good',
        ]);

        $this->assertTrue($tool->isAvailable());
        $this->assertEquals('good', $tool->condition);
        $this->assertEquals('torque', $tool->category);

        $tool->update(['availability_status' => 'assigned']);
        $this->assertFalse($tool->isAvailable());
    }

    public function test_tool_assignment_tracks_mechanic_and_returns(): void
    {
        $tool = Tool::factory()->assigned()->create();
        $jobCard = MaintenanceJobCard::factory()->create();

        $assignment = ToolAssignment::create([
            'tool_id' => $tool->id,
            'mechanic_external_user_id' => 'MECH-89',
            'maintenance_job_card_id' => $jobCard->id,
            'assigned_at' => now()->subHours(2),
            'expected_return_at' => now()->addHours(2),
            'assigned_by_external_user_id' => 'MGR-01',
        ]);

        $this->assertFalse($assignment->isReturned());
        $this->assertEquals($tool->id, $assignment->tool->id);
        $this->assertEquals($jobCard->id, $assignment->maintenanceJobCard->id);

        $assignment->update([
            'returned_at' => now(),
            'condition_on_return' => 'good',
            'received_by_external_user_id' => 'MGR-01',
        ]);

        $this->assertTrue($assignment->fresh()->isReturned());
    }

    public function test_tool_assignment_scope_active(): void
    {
        $tool1 = Tool::factory()->create();
        $tool2 = Tool::factory()->create();

        ToolAssignment::factory()->active()->create(['tool_id' => $tool1->id]);
        ToolAssignment::factory()->returned()->create(['tool_id' => $tool2->id]);

        $activeAssignments = ToolAssignment::active()->get();

        $this->assertCount(1, $activeAssignments);
        $this->assertEquals($tool1->id, $activeAssignments->first()->tool_id);
    }

    public function test_maintenance_job_card_has_inventory_parts_and_tool_assignments(): void
    {
        $jobCard = MaintenanceJobCard::factory()->create();
        $part = InventoryPart::factory()->create();
        $tool = Tool::factory()->create();

        JobCardPart::factory()->create([
            'maintenance_job_card_id' => $jobCard->id,
            'inventory_part_id' => $part->id,
        ]);

        ToolAssignment::factory()->create([
            'maintenance_job_card_id' => $jobCard->id,
            'tool_id' => $tool->id,
        ]);

        $jobCard->refresh();

        $this->assertCount(1, $jobCard->jobCardParts);
        $this->assertCount(1, $jobCard->toolAssignments);
    }
}
