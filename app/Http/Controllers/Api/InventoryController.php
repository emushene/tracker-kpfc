<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\Tool;
use App\Models\ToolAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Return high-level inventory metrics.
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'total_parts' => InventoryPart::query()->where('active', true)->count(),
            'mechanical_parts' => InventoryPart::query()->where('active', true)->where('category', 'mechanical')->count(),
            'cosmetic_parts' => InventoryPart::query()->where('active', true)->where('category', 'cosmetic_body')->count(),
            'low_stock_parts' => InventoryPart::query()->where('active', true)->whereColumn('quantity', '<', 'minimum_quantity')->count(),
            'total_tools' => Tool::query()->count(),
            'available_tools' => Tool::query()->where('availability_status', 'available')->count(),
            'assigned_tools' => Tool::query()->where('availability_status', 'assigned')->count(),
        ]);
    }

    /**
     * List inventory parts with category/low-stock filtering.
     */
    public function parts(Request $request): JsonResponse
    {
        $query = InventoryPart::query()->with('inventoryCategory')->where('active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('quantity', '<', 'minimum_quantity');
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('part_number', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->limit(100)->get(),
        ]);
    }

    /**
     * Store a new inventory part.
     */
    public function storePart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'part_number' => 'required|string|unique:inventory_parts,part_number|max:100',
            'name' => 'required|string|max:255',
            'category' => 'required|in:mechanical,cosmetic_body',
            'quantity' => 'required|integer|min:0',
            'minimum_quantity' => 'required|integer|min:0',
            'unit_of_measure' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:100',
            'unit_cost_reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
        ]);

        $part = DB::transaction(function () use ($validated) {
            $part = InventoryPart::create([
                'part_number' => strtoupper($validated['part_number']),
                'name' => $validated['name'],
                'category' => $validated['category'],
                'quantity' => $validated['quantity'],
                'minimum_quantity' => $validated['minimum_quantity'],
                'unit_of_measure' => $validated['unit_of_measure'] ?? 'piece',
                'location' => $validated['location'] ?? null,
                'unit_cost_reference' => $validated['unit_cost_reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'active' => true,
            ]);

            if ($part->quantity > 0) {
                InventoryMovement::create([
                    'inventory_part_id' => $part->id,
                    'movement_type' => 'restock',
                    'quantity' => $part->quantity,
                    'balance_after' => $part->quantity,
                    'reference_type' => 'initial_stock',
                    'notes' => 'Initial inventory count',
                ]);
            }

            return $part;
        });

        return response()->json([
            'message' => 'Inventory part added successfully.',
            'data' => $part,
        ], 201);
    }

    /**
     * Adjust stock quantity for an inventory part.
     */
    public function adjustStock(Request $request, InventoryPart $part): JsonResponse
    {
        $validated = $request->validate([
            'adjustment' => 'required|integer', // positive or negative
            'movement_type' => 'required|in:restock,adjustment,job_card_usage,return',
            'notes' => 'nullable|string',
            'actor_external_user_id' => 'nullable|string',
        ]);

        $newBalance = $part->quantity + $validated['adjustment'];
        if ($newBalance < 0) {
            return response()->json(['message' => 'Cannot adjust stock below zero.'], 422);
        }

        DB::transaction(function () use ($part, $validated, $newBalance): void {
            $part->update(['quantity' => $newBalance]);

            InventoryMovement::create([
                'inventory_part_id' => $part->id,
                'movement_type' => $validated['movement_type'],
                'quantity' => $validated['adjustment'],
                'balance_after' => $newBalance,
                'actor_external_user_id' => $validated['actor_external_user_id'] ?? null,
                'notes' => $validated['notes'] ?? 'Manual stock adjustment',
            ]);
        });

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'data' => $part->fresh(),
        ]);
    }

    /**
     * List tools with status/category filtering.
     */
    public function tools(Request $request): JsonResponse
    {
        $query = Tool::query()->with('currentAssignment');

        if ($request->filled('availability_status')) {
            $query->where('availability_status', $request->input('availability_status'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('tool_code', 'like', "%{$term}%")
                    ->orWhere('serial_number', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    /**
     * Assign a tool to a mechanic.
     */
    public function assignTool(Request $request, Tool $tool): JsonResponse
    {
        if ($tool->availability_status !== 'available') {
            return response()->json(['message' => 'Tool is currently unavailable for assignment.'], 422);
        }

        $validated = $request->validate([
            'mechanic_external_user_id' => 'required|string|max:255',
            'maintenance_job_card_id' => 'nullable|exists:maintenance_job_cards,id',
            'notes' => 'nullable|string',
        ]);

        $assignment = DB::transaction(function () use ($tool, $validated) {
            $assignment = ToolAssignment::create([
                'tool_id' => $tool->id,
                'mechanic_external_user_id' => $validated['mechanic_external_user_id'],
                'maintenance_job_card_id' => $validated['maintenance_job_card_id'] ?? null,
                'assigned_at' => now(),
                'expected_return_at' => now()->addHours(8),
                'notes' => $validated['notes'] ?? null,
            ]);

            $tool->update(['availability_status' => 'assigned']);

            return $assignment;
        });

        return response()->json([
            'message' => 'Tool successfully assigned to mechanic.',
            'data' => $assignment->load('tool'),
        ]);
    }

    /**
     * Return an assigned tool.
     */
    public function returnTool(Request $request, Tool $tool): JsonResponse
    {
        $assignment = $tool->currentAssignment;
        if (! $assignment) {
            return response()->json(['message' => 'Tool has no active assignment to return.'], 422);
        }

        $validated = $request->validate([
            'condition_on_return' => 'required|in:new,good,fair,poor,damaged',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($tool, $assignment, $validated): void {
            $assignment->update([
                'returned_at' => now(),
                'condition_on_return' => $validated['condition_on_return'],
                'notes' => $validated['notes'] ?? $assignment->notes,
            ]);

            $newAvailability = in_array($validated['condition_on_return'], ['poor', 'damaged'])
                ? 'under_maintenance'
                : 'available';

            $tool->update([
                'availability_status' => $newAvailability,
                'condition' => $validated['condition_on_return'],
            ]);
        });

        return response()->json([
            'message' => 'Tool returned successfully.',
            'data' => $tool->fresh(),
        ]);
    }
}
