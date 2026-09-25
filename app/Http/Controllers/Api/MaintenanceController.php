<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use App\Models\VehicleRepair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * Return high-level maintenance overview metrics.
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'open_tickets' => MaintenanceTicket::query()->whereIn('status', ['open', 'prioritized'])->count(),
            'in_progress_tickets' => MaintenanceTicket::query()->where('status', 'in_progress')->count(),
            'active_job_cards' => MaintenanceJobCard::query()->whereIn('status', ['open', 'in_progress'])->count(),
            'active_alerts' => MaintenanceAlert::query()->where('status', 'active')->count(),
            'recent_repairs_count' => VehicleRepair::query()->where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    /**
     * List maintenance tickets with optional filtering.
     */
    public function tickets(Request $request): JsonResponse
    {
        $query = MaintenanceTicket::query()->with('vehicle')->latest();

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('ticket_type')) {
            $query->where('ticket_type', $request->input('ticket_type'));
        }

        return response()->json([
            'data' => $query->limit(50)->get(),
        ]);
    }

    /**
     * Create a new maintenance ticket.
     */
    public function storeTicket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'ticket_type' => 'required|in:repair,inspection,service,diagnostic',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'assigned_to_external_user_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $ticket = MaintenanceTicket::create([
            'ticket_number' => 'TCK-' . strtoupper(uniqid()),
            'vehicle_id' => $validated['vehicle_id'],
            'ticket_type' => $validated['ticket_type'],
            'status' => 'open',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'normal',
            'assigned_to_external_user_id' => $validated['assigned_to_external_user_id'] ?? null,
            'opened_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Maintenance ticket created successfully.',
            'data' => $ticket->load('vehicle'),
        ], 201);
    }

    /**
     * List job cards.
     */
    public function jobCards(Request $request): JsonResponse
    {
        $query = MaintenanceJobCard::query()
            ->with(['vehicle', 'maintenanceTicket', 'jobCardParts.inventoryPart', 'toolAssignments.tool'])
            ->latest();

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'data' => $query->limit(50)->get(),
        ]);
    }

    /**
     * Create a new job card.
     */
    public function storeJobCard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'maintenance_ticket_id' => 'nullable|exists:maintenance_tickets,id',
            'mechanic_external_user_id' => 'nullable|string|max:255',
            'mileage_at_service' => 'nullable|integer',
            'reported_problem' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'work_performed' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $jobCard = MaintenanceJobCard::create([
            'job_card_number' => 'JC-' . strtoupper(uniqid()),
            'vehicle_id' => $validated['vehicle_id'],
            'maintenance_ticket_id' => $validated['maintenance_ticket_id'] ?? null,
            'mechanic_external_user_id' => $validated['mechanic_external_user_id'] ?? null,
            'mileage_at_service' => $validated['mileage_at_service'] ?? null,
            'reported_problem' => $validated['reported_problem'] ?? null,
            'diagnosis' => $validated['diagnosis'] ?? null,
            'work_performed' => $validated['work_performed'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'open',
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Job card created successfully.',
            'data' => $jobCard->load('vehicle'),
        ], 201);
    }

    /**
     * List active maintenance alerts.
     */
    public function alerts(): JsonResponse
    {
        $alerts = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceSchedule'])
            ->where('status', 'active')
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $alerts,
        ]);
    }

    /**
     * Retrieve maintenance records specific to a vehicle.
     */
    public function vehicleDetails(Vehicle $vehicle): JsonResponse
    {
        return response()->json([
            'schedules' => $vehicle->maintenanceSchedules()->where('active', true)->get(),
            'alerts' => $vehicle->maintenanceAlerts()->where('status', 'active')->get(),
            'tickets' => $vehicle->maintenanceTickets()->latest()->limit(5)->get(),
            'job_cards' => $vehicle->maintenanceJobCards()->latest()->limit(5)->get(),
            'repairs' => $vehicle->repairs()->latest()->limit(5)->get(),
        ]);
    }
}
