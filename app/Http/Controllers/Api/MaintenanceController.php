<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
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
            'ticket_number' => 'TCK-'.strtoupper(uniqid()),
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
            'job_card_number' => 'JC-'.strtoupper(uniqid()),
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

    /**
     * Show a maintenance ticket.
     */
    public function showTicket(MaintenanceTicket $ticket): JsonResponse
    {
        return response()->json(['data' => $ticket->load('vehicle')]);
    }

    /**
     * Update a maintenance ticket.
     */
    public function updateTicket(Request $request, MaintenanceTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'notes' => 'nullable|string',
        ]);

        $ticket->update($validated);

        return response()->json(['message' => 'Ticket updated successfully.', 'data' => $ticket]);
    }

    /**
     * Delete a maintenance ticket.
     */
    public function destroyTicket(MaintenanceTicket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted successfully.']);
    }

    /**
     * Show a job card.
     */
    public function showJobCard(MaintenanceJobCard $jobCard): JsonResponse
    {
        return response()->json(['data' => $jobCard->load(['vehicle', 'maintenanceTicket'])]);
    }

    /**
     * Update a job card.
     */
    public function updateJobCard(Request $request, MaintenanceJobCard $jobCard): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string',
            'mileage_at_service' => 'nullable|integer',
            'reported_problem' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'work_performed' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $jobCard->update($validated);

        return response()->json(['message' => 'Job card updated successfully.', 'data' => $jobCard]);
    }

    /**
     * Delete a job card.
     */
    public function destroyJobCard(MaintenanceJobCard $jobCard): JsonResponse
    {
        $jobCard->delete();

        return response()->json(['message' => 'Job card deleted successfully.']);
    }

    /**
     * Show an alert.
     */
    public function showAlert(MaintenanceAlert $alert): JsonResponse
    {
        return response()->json(['data' => $alert->load(['vehicle', 'maintenanceSchedule'])]);
    }

    /**
     * Create an alert.
     */
    public function storeAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'maintenance_schedule_id' => 'nullable|exists:maintenance_schedules,id',
            'alert_type' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'message' => 'nullable|string',
            'status' => 'sometimes|string',
        ]);

        $alert = MaintenanceAlert::create($validated);

        return response()->json(['message' => 'Alert created successfully.', 'data' => $alert], 201);
    }

    /**
     * Update an alert.
     */
    public function updateAlert(Request $request, MaintenanceAlert $alert): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string',
            'title' => 'sometimes|string|max:255',
            'message' => 'nullable|string',
        ]);

        $alert->update($validated);

        return response()->json(['message' => 'Alert updated successfully.', 'data' => $alert]);
    }

    /**
     * Delete an alert.
     */
    public function destroyAlert(MaintenanceAlert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(['message' => 'Alert deleted successfully.']);
    }
}
