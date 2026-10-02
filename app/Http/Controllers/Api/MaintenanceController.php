<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\DriverChecklistSubmission;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\JobCardChecklistFieldValue;
use App\Models\JobCardChecklistItem;
use App\Models\JobCardPart;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceJobCard;
use App\Models\MaintenanceSchedule;
use App\Models\MaintenanceTicket;
use App\Models\Vehicle;
use App\Models\VehicleMileage;
use App\Models\VehicleRepair;
use App\Models\VehicleReplacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $query = MaintenanceTicket::query()->with('vehicle')->withCount('maintenanceJobCards')->latest();

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
            ->with([
                'vehicle',
                'maintenanceTicket',
                'jobCardParts.inventoryPart',
                'toolAssignments.tool',
                'jobCardChecklists.jobCardChecklistItems.jobCardParts.inventoryPart',
                'jobCardChecklists.fieldValues.checklistTemplateField.options',
            ])
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

    public function driverChecklistSubmissions(Request $request): JsonResponse
    {
        $query = DriverChecklistSubmission::query()
            ->with(['vehicle', 'items', 'fieldValues'])
            ->latest('submitted_at');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('submission_date')) {
            $query->whereDate('submission_date', $request->input('submission_date'));
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
            'checklist_template_id' => 'nullable|integer|exists:checklist_templates,id,active,1',
            'reported_problem' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'work_performed' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $jobCard = DB::transaction(function () use ($validated): MaintenanceJobCard {
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

            $template = $this->resolveJobCardChecklistTemplate($validated);

            if ($template) {
                $this->attachChecklistSnapshot($jobCard, $template);
            }

            return $jobCard->load([
                'vehicle',
                'maintenanceTicket',
                'jobCardChecklists.jobCardChecklistItems',
                'jobCardChecklists.fieldValues.checklistTemplateField.options',
            ]);
        });

        return response()->json([
            'message' => 'Job card created successfully.',
            'data' => $jobCard,
        ], 201);
    }

    public function attachJobCardChecklist(MaintenanceJobCard $jobCard): JsonResponse
    {
        $created = false;
        $jobCard = DB::transaction(function () use ($jobCard, &$created): MaintenanceJobCard {
            $jobCard = MaintenanceJobCard::query()->lockForUpdate()->findOrFail($jobCard->id);

            if ($jobCard->jobCardChecklists()->exists()) {
                return $jobCard;
            }

            $template = $this->resolveJobCardChecklistTemplate([
                'maintenance_ticket_id' => $jobCard->maintenance_ticket_id,
            ]);

            if ($template) {
                $this->attachChecklistSnapshot($jobCard, $template);
                $created = true;
            }

            return $jobCard;
        });

        $jobCard->load([
            'vehicle',
            'maintenanceTicket',
            'jobCardParts.inventoryPart',
            'toolAssignments.tool',
            'jobCardChecklists.jobCardChecklistItems.jobCardParts.inventoryPart',
            'jobCardChecklists.fieldValues.checklistTemplateField.options',
        ]);

        if ($jobCard->jobCardChecklists->isEmpty()) {
            return response()->json([
                'message' => 'No active mechanic checklist template is available for this job card.',
            ], 422);
        }

        return response()->json([
            'message' => $created ? 'Mechanic checklist attached successfully.' : 'Mechanic checklist is already attached.',
            'data' => $jobCard,
        ], $created ? 201 : 200);
    }

    private function attachChecklistSnapshot(MaintenanceJobCard $jobCard, ChecklistTemplate $template): void
    {
        $snapshot = $jobCard->jobCardChecklists()->create([
            'checklist_template_id' => $template->id,
            'template_name' => $template->name,
            'template_category' => $template->category,
        ]);

        $snapshot->jobCardChecklistItems()->createMany(
            $template->checklistItems->map(fn ($templateItem): array => [
                'item_key' => $templateItem->item_key,
                'section_title' => $templateItem->section_title,
                'sequence' => $templateItem->sequence,
                'label' => $templateItem->label,
                'description' => $templateItem->description,
                'required' => $templateItem->required,
                'is_checked' => false,
                'result' => 'unchecked',
            ])->all()
        );

        $snapshot->fieldValues()->createMany(
            $template->fields->map(fn ($field): array => [
                'checklist_template_field_id' => $field->id,
                'field_key' => $field->field_key,
                'field_group' => $field->field_group,
                'label' => $field->label,
                'value' => null,
            ])->all()
        );
    }

    /**
     * Update a checklist item on a job card.
     */
    public function updateJobCardChecklistItem(Request $request, JobCardChecklistItem $checklistItem): JsonResponse
    {
        $validated = $request->validate([
            'is_checked' => 'sometimes|boolean',
            'result' => 'sometimes|in:unchecked,pass,fail,repaired,replaced,na',
            'notes' => 'sometimes|nullable|string',
        ]);

        if (array_key_exists('result', $validated)) {
            $validated['is_checked'] = in_array($validated['result'], ['pass', 'repaired', 'replaced'], true);
            $validated['checked_at'] = $validated['result'] === 'unchecked' ? null : now();
        }

        if (! array_key_exists('result', $validated) && array_key_exists('is_checked', $validated)) {
            $validated['result'] = $validated['is_checked'] ? 'pass' : 'unchecked';
            $validated['checked_at'] = $validated['is_checked'] ? now() : null;
        }

        $checklistItem->update($validated);

        return response()->json([
            'message' => 'Checklist item updated successfully.',
            'data' => $checklistItem->load('jobCardParts.inventoryPart'),
        ]);
    }

    public function updateJobCardChecklistFieldValue(Request $request, JobCardChecklistFieldValue $fieldValue): JsonResponse
    {
        $validated = $request->validate([
            'value' => 'nullable|string|max:5000',
        ]);

        $field = $fieldValue->checklistTemplateField()->with('options')->first();
        $value = $validated['value'] ?? null;

        if ($field?->required && trim((string) $value) === '') {
            return response()->json([
                'message' => 'This checklist field is required.',
                'errors' => ['value' => ['A value is required.']],
            ], 422);
        }

        if ($field?->field_type === 'select' && $value !== null && ! $field->options->contains('option_value', $value)) {
            return response()->json([
                'message' => 'The selected checklist option is invalid.',
                'errors' => ['value' => ['Select one of the available options.']],
            ], 422);
        }

        $fieldValue->update(['value' => $value]);

        return response()->json([
            'message' => 'Checklist report field updated successfully.',
            'data' => $fieldValue,
        ]);
    }

    /**
     * Resolve an explicitly selected template or infer one from the linked ticket.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveJobCardChecklistTemplate(array $validated): ?ChecklistTemplate
    {
        $templates = ChecklistTemplate::query()
            ->where('active', true)
            ->where('role', 'mechanic')
            ->with([
                'checklistItems' => fn ($query) => $query->orderBy('sequence'),
                'fields.options',
            ]);

        if (isset($validated['checklist_template_id'])) {
            return $templates->find($validated['checklist_template_id']);
        }

        $ticketType = isset($validated['maintenance_ticket_id'])
            ? MaintenanceTicket::query()->whereKey($validated['maintenance_ticket_id'])->value('ticket_type')
            : 'service';

        if (! $ticketType) {
            return null;
        }

        return (clone $templates)->where('category', $ticketType)->first()
            ?? (clone $templates)->where('category', 'general')->first()
            ?? (clone $templates)->first();
    }

    /**
     * List active maintenance alerts.
     */
    public function alerts(): JsonResponse
    {
        $alerts = MaintenanceAlert::query()
            ->with(['vehicle', 'maintenanceSchedule'])
            ->latest()
            ->limit(30)
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
            'status' => 'sometimes|in:open,prioritized,in_progress,closed',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'sometimes|in:low,normal,high,urgent',
            'notes' => 'nullable|string',
        ]);

        $wasClosed = $ticket->status === 'closed';

        DB::transaction(function () use ($ticket, $validated, $wasClosed): void {
            $ticket->update($validated);

            if (! $wasClosed && ($validated['status'] ?? null) === 'closed') {
                $this->advanceMileageScheduleForClosedTicket($ticket);
            }
        });

        return response()->json(['message' => 'Ticket updated successfully.', 'data' => $ticket]);
    }

    private function advanceMileageScheduleForClosedTicket(MaintenanceTicket $ticket): void
    {
        $titlePrefix = 'Scheduled Maintenance: ';

        if (! str_starts_with($ticket->title, $titlePrefix)) {
            return;
        }

        $serviceName = substr($ticket->title, strlen($titlePrefix));
        $currentOdometer = VehicleMileage::query()
            ->where('vehicle_id', $ticket->vehicle_id)
            ->whereNotNull('odometer')
            ->latest('date')
            ->value('odometer');

        if ($currentOdometer === null) {
            return;
        }

        $schedules = MaintenanceSchedule::query()
            ->where('vehicle_id', $ticket->vehicle_id)
            ->where('schedule_type', 'mileage')
            ->where('service_name', $serviceName)
            ->where('active', true)
            ->whereNotNull('interval_km')
            ->get();

        foreach ($schedules as $schedule) {
            $schedule->update([
                'last_service_km' => (int) $currentOdometer,
                'last_service_at' => now(),
                'next_service_km' => (int) $currentOdometer + $schedule->interval_km,
            ]);
        }
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
        return response()->json([
            'data' => $jobCard->load([
                'vehicle',
                'maintenanceTicket',
                'jobCardParts.inventoryPart',
                'toolAssignments.tool',
                'jobCardChecklists.jobCardChecklistItems.jobCardParts.inventoryPart',
                'jobCardChecklists.fieldValues.checklistTemplateField.options',
            ]),
        ]);
    }

    /**
     * Attach an inventory part to a job card and calculate/deduct stock.
     */
    public function attachJobCardPart(Request $request, MaintenanceJobCard $jobCard): JsonResponse
    {
        $validated = $request->validate([
            'inventory_part_id' => 'required|exists:inventory_parts,id',
            'quantity' => 'required|integer|min:1',
            'job_card_checklist_item_id' => 'nullable|exists:job_card_checklist_items,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $part = InventoryPart::findOrFail($validated['inventory_part_id']);

        if ($part->quantity < $validated['quantity']) {
            return response()->json([
                'message' => "Insufficient stock in inventory for {$part->name}. Only {$part->quantity} {$part->unit_of_measure} available.",
                'errors' => [
                    'quantity' => ["Only {$part->quantity} {$part->unit_of_measure} available in stock."],
                ],
            ], 422);
        }

        $jobCardPart = DB::transaction(function () use ($jobCard, $part, $validated, $request): JobCardPart {
            // 1. Decrement part inventory quantity
            $part->decrement('quantity', $validated['quantity']);
            $newBalance = $part->fresh()->quantity;

            // 2. Record inventory movement
            InventoryMovement::create([
                'inventory_part_id' => $part->id,
                'movement_type' => 'job_card_usage',
                'quantity' => -$validated['quantity'],
                'balance_after' => $newBalance,
                'reference_type' => 'maintenance_job_card',
                'reference_id' => $jobCard->id,
                'actor_external_user_id' => $request->user()?->external_id ?? $request->user()?->name ?? 'User',
                'notes' => "Allocated to Job Card {$jobCard->job_card_number}".($validated['notes'] ? ': '.$validated['notes'] : ''),
            ]);

            // 3. If tied to a checklist item, ensure item result is marked as replaced
            if (! empty($validated['job_card_checklist_item_id'])) {
                $checklistItem = JobCardChecklistItem::find($validated['job_card_checklist_item_id']);
                if ($checklistItem) {
                    $checklistItem->update([
                        'result' => 'replaced',
                        'is_checked' => true,
                        'checked_at' => now(),
                        'notes' => $checklistItem->notes ? $checklistItem->notes.' | Replaced: '.$part->name : 'Replaced with: '.$part->name,
                    ]);
                }
            }

            // 4. Record replacement on vehicle history
            VehicleReplacement::create([
                'vehicle_id' => $jobCard->vehicle_id,
                'maintenance_job_card_id' => $jobCard->id,
                'mechanic_external_user_id' => $jobCard->mechanic_external_user_id,
                'part_name' => $part->name,
                'new_part_description' => "Part #{$part->part_number} ({$part->category})",
                'reason' => $validated['notes'] ?? 'Replaced during service inspection',
                'mileage_at_replacement' => $jobCard->mileage_at_service,
                'replaced_at' => now(),
                'notes' => "Allocated from inventory. Quantity: {$validated['quantity']} {$part->unit_of_measure}",
            ]);

            // 5. Create JobCardPart
            return $jobCard->jobCardParts()->create([
                'inventory_part_id' => $part->id,
                'job_card_checklist_item_id' => $validated['job_card_checklist_item_id'] ?? null,
                'quantity' => $validated['quantity'],
                'notes' => $validated['notes'] ?? null,
                'allocated_by_external_user_id' => $request->user()?->external_id ?? $request->user()?->name ?? 'User',
            ]);
        });

        return response()->json([
            'message' => "Part {$part->name} successfully allocated and {$validated['quantity']} {$part->unit_of_measure} deducted from inventory.",
            'data' => $jobCardPart->load('inventoryPart'),
            'remaining_stock' => $part->fresh()->quantity,
        ], 201);
    }

    /**
     * Remove an inventory part from a job card and restore stock to inventory.
     */
    public function detachJobCardPart(MaintenanceJobCard $jobCard, JobCardPart $jobCardPart): JsonResponse
    {
        if ($jobCardPart->maintenance_job_card_id !== $jobCard->id) {
            return response()->json(['message' => 'Job card part does not belong to this job card.'], 404);
        }

        $part = $jobCardPart->inventoryPart;

        DB::transaction(function () use ($jobCard, $jobCardPart, $part): void {
            if ($part) {
                $part->increment('quantity', $jobCardPart->quantity);
                $newBalance = $part->fresh()->quantity;

                InventoryMovement::create([
                    'inventory_part_id' => $part->id,
                    'movement_type' => 'return',
                    'quantity' => $jobCardPart->quantity,
                    'balance_after' => $newBalance,
                    'reference_type' => 'maintenance_job_card',
                    'reference_id' => $jobCard->id,
                    'actor_external_user_id' => request()->user()?->external_id ?? request()->user()?->name ?? 'User',
                    'notes' => "Returned from Job Card {$jobCard->job_card_number}",
                ]);
            }

            $jobCardPart->delete();
        });

        return response()->json([
            'message' => 'Part returned to inventory and stock restored.',
            'remaining_stock' => $part ? $part->fresh()->quantity : null,
        ]);
    }

    /**
     * Batch save checklist items, notes, field values, and optional status update for a job card.
     */
    public function saveJobCardChecklist(Request $request, MaintenanceJobCard $jobCard): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required_with:items|exists:job_card_checklist_items,id',
            'items.*.result' => 'sometimes|in:unchecked,pass,fail,repaired,replaced,na',
            'items.*.notes' => 'nullable|string',
            'field_values' => 'nullable|array',
            'field_values.*.id' => 'required_with:field_values|exists:job_card_checklist_field_values,id',
            'field_values.*.value' => 'nullable|string|max:5000',
            'status' => 'sometimes|in:open,in_progress,completed',
            'work_performed' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($jobCard, $validated): void {
            if (! empty($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $item = JobCardChecklistItem::find($itemData['id']);
                    if ($item) {
                        $updateData = [];
                        if (isset($itemData['result'])) {
                            $updateData['result'] = $itemData['result'];
                            $updateData['is_checked'] = in_array($itemData['result'], ['pass', 'repaired', 'replaced'], true);
                            $updateData['checked_at'] = $itemData['result'] === 'unchecked' ? null : now();
                        }
                        if (array_key_exists('notes', $itemData)) {
                            $updateData['notes'] = $itemData['notes'];
                        }
                        if (! empty($updateData)) {
                            $item->update($updateData);
                        }
                    }
                }
            }

            if (! empty($validated['field_values'])) {
                foreach ($validated['field_values'] as $fieldData) {
                    $fieldVal = JobCardChecklistFieldValue::find($fieldData['id']);
                    if ($fieldVal && array_key_exists('value', $fieldData)) {
                        $fieldVal->update(['value' => $fieldData['value']]);
                    }
                }
            }

            $jobCardUpdates = [];
            foreach (['status', 'work_performed', 'diagnosis', 'notes'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $jobCardUpdates[$field] = $validated[$field];
                }
            }

            if (($jobCardUpdates['status'] ?? null) === 'completed' && $jobCard->status !== 'completed') {
                $jobCardUpdates['completed_at'] = now();
            }

            if (! empty($jobCardUpdates)) {
                $jobCard->update($jobCardUpdates);
            }
        });

        return response()->json([
            'message' => 'Job card and checklist saved successfully.',
            'data' => $jobCard->fresh()->load([
                'vehicle',
                'maintenanceTicket',
                'jobCardParts.inventoryPart',
                'toolAssignments.tool',
                'jobCardChecklists.jobCardChecklistItems.jobCardParts.inventoryPart',
                'jobCardChecklists.fieldValues.checklistTemplateField.options',
            ]),
        ]);
    }

    /**
     * Update a job card.
     */
    public function updateJobCard(Request $request, MaintenanceJobCard $jobCard): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:open,in_progress,completed',
            'mileage_at_service' => 'nullable|integer',
            'reported_problem' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'work_performed' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Stamp completed_at when the card transitions to completed
        if (($validated['status'] ?? null) === 'completed' && $jobCard->status !== 'completed') {
            $validated['completed_at'] = now();
        }

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
            'status' => 'sometimes|in:active,acknowledged,resolved',
            'title' => 'sometimes|string|max:255',
            'message' => 'nullable|string',
        ]);

        // Stamp resolved_at / acknowledged_at on status transitions
        if (($validated['status'] ?? null) === 'resolved' && $alert->status !== 'resolved') {
            $validated['resolved_at'] = now();
        }

        if (($validated['status'] ?? null) === 'acknowledged' && $alert->status === 'active') {
            $validated['acknowledged_at'] = now();
        }

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
