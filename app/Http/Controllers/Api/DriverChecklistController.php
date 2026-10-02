<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChecklistTemplate;
use App\Models\DriverChecklistSubmission;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverChecklistController extends Controller
{
    public function dailyTemplate(Request $request): JsonResponse
    {
        $template = ChecklistTemplate::query()
            ->where('template_key', 'driver_daily')
            ->where('active', true)
            ->with([
                'checklistItems' => fn ($query) => $query->orderBy('sequence'),
                'fields.options',
            ])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'template' => $template,
                'assigned_vehicle' => $this->assignedTrip($request)?->vehicle,
            ],
        ]);
    }

    public function storeDailySubmission(Request $request): JsonResponse
    {
        $vehicleInput = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
        ]);
        $driverId = (string) ($request->user()?->kpfc_sub ?? $request->user()?->id ?? '');
        $trip = $this->assignedTrip($request);

        if (! $trip || (int) $trip->vehicle_id !== (int) $vehicleInput['vehicle_id']) {
            return response()->json([
                'message' => 'You can only submit a checklist for your assigned trip vehicle.',
            ], 403);
        }

        $validated = $request->validate([
            'odometer' => 'required|integer|min:0',
            'submission_date' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.item_key' => 'required|string|distinct',
            'items.*.result' => 'required|in:pass,fail,na',
            'items.*.notes' => 'nullable|string|max:5000',
            'fields' => 'nullable|array',
            'fields.*' => 'nullable|string|max:5000',
        ]);
        $validated['vehicle_id'] = $vehicleInput['vehicle_id'];

        $template = ChecklistTemplate::query()
            ->where('template_key', 'driver_daily')
            ->where('active', true)
            ->with([
                'checklistItems' => fn ($query) => $query->orderBy('sequence'),
                'fields.options',
            ])
            ->firstOrFail();

        $answers = collect($validated['items'])->keyBy('item_key');
        $templateItems = $template->checklistItems->keyBy('item_key');
        $unknownItems = $answers->keys()->diff($templateItems->keys());
        $missingItems = $templateItems
            ->filter(fn ($item) => $item->required && ! $answers->has($item->item_key))
            ->keys();

        if ($unknownItems->isNotEmpty() || $missingItems->isNotEmpty()) {
            return response()->json([
                'message' => 'Submit each required checklist item from the active template.',
                'errors' => ['items' => ['The submitted checklist items do not match the active template.']],
            ], 422);
        }

        $vehicle = $trip->vehicle;
        $submissionDate = $validated['submission_date'] ?? now()->toDateString();
        $values = [
            'vehicle_reg' => $vehicle->plate_number ?? '',
            'date' => $submissionDate,
            'odometer' => (string) $validated['odometer'],
            'driver_name' => (string) $request->user()?->name,
        ];
        $editableFieldKeys = $template->fields
            ->filter(fn ($field) => $field->field_group === 'report' || $field->field_key === 'route')
            ->pluck('field_key');

        foreach ($validated['fields'] ?? [] as $fieldKey => $value) {
            if (! $editableFieldKeys->contains($fieldKey)) {
                return response()->json([
                    'message' => 'One or more submitted fields are not editable.',
                    'errors' => ['fields' => ["The {$fieldKey} field cannot be changed."]],
                ], 422);
            }

            $values[$fieldKey] = $value ?? '';
        }

        foreach ($template->fields as $field) {
            $value = (string) ($values[$field->field_key] ?? '');

            if ($field->required && trim($value) === '') {
                return response()->json([
                    'message' => 'Complete all required checklist report fields.',
                    'errors' => ['fields' => ["The {$field->label} field is required."]],
                ], 422);
            }

            if ($field->field_type === 'select' && $value !== '' && ! $field->options->contains('option_value', $value)) {
                return response()->json([
                    'message' => 'A selected report option is invalid.',
                    'errors' => ['fields' => ["The {$field->label} selection is invalid."]],
                ], 422);
            }

            $values[$field->field_key] = $value;
        }

        $submission = DB::transaction(function () use ($answers, $driverId, $submissionDate, $template, $validated, $values, $vehicle): DriverChecklistSubmission {
            $submission = DriverChecklistSubmission::create([
                'checklist_template_id' => $template->id,
                'vehicle_id' => $vehicle->id,
                'driver_external_user_id' => $driverId,
                'submission_date' => $submissionDate,
                'odometer' => $validated['odometer'],
                'vehicle_status' => $values['driver_vehicle_status'],
                'defects' => $values['driver_defects'] ?? null,
                'action_taken' => $values['driver_action'] ?? null,
                'driver_signature' => $values['driver_signature'],
                'submitted_at' => now(),
            ]);

            foreach ($template->checklistItems as $templateItem) {
                $answer = $answers->get($templateItem->item_key, []);
                $submission->items()->create([
                    'checklist_item_id' => $templateItem->id,
                    'item_key' => $templateItem->item_key,
                    'section_title' => $templateItem->section_title,
                    'sequence' => $templateItem->sequence,
                    'label' => $templateItem->label,
                    'description' => $templateItem->description,
                    'required' => $templateItem->required,
                    'result' => $answer['result'] ?? 'pending',
                    'notes' => $answer['notes'] ?? null,
                ]);
            }

            foreach ($template->fields as $field) {
                $submission->fieldValues()->create([
                    'checklist_template_field_id' => $field->id,
                    'field_key' => $field->field_key,
                    'field_group' => $field->field_group,
                    'label' => $field->label,
                    'value' => $values[$field->field_key] ?? null,
                ]);
            }

            return $submission->load(['vehicle', 'checklistTemplate', 'items', 'fieldValues']);
        });

        return response()->json([
            'message' => 'Daily vehicle checklist submitted successfully.',
            'data' => $submission,
        ], 201);
    }

    private function assignedTrip(Request $request): ?Trip
    {
        $driverId = (string) ($request->user()?->kpfc_sub ?? $request->user()?->id ?? '');

        return Trip::query()
            ->forDriver($driverId)
            ->active()
            ->with('vehicle')
            ->latest('actual_start')
            ->first()
            ?? Trip::query()->forDriver($driverId)->upcoming()->with('vehicle')->first();
    }
}
