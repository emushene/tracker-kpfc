<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnToBaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnToBaseController extends Controller
{
    /**
     * List return-to-base requests with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ReturnToBaseRequest::query()
            ->with(['trip.vehicle'])
            ->latest('requested_at');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('trip_id')) {
            $query->where('trip_id', $request->integer('trip_id'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_external_user_id', (string) $request->input('driver_id'));
        }

        return response()->json([
            'data' => $query->paginate($request->integer('per_page', 25)),
        ]);
    }

    /**
     * Review and approve or deny a return-to-base request.
     */
    public function decision(Request $request, ReturnToBaseRequest $returnRequest): JsonResponse
    {
        if ($returnRequest->status !== 'pending') {
            return response()->json([
                'message' => "This return request has already been {$returnRequest->status}.",
            ], 422);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
            'manager_comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $decision = $validated['decision'];
        $user = $request->user();
        $managerId = (string) ($user?->kpfc_sub ?? $user?->id ?? 'manager');

        $returnRequest->update([
            'status' => $decision,
            'decision' => $decision,
            'decision_maker_external_user_id' => $managerId,
            'decided_at' => now(),
            'manager_comments' => $validated['manager_comments'] ?? null,
        ]);

        // If approved, update the parent trip status to returning_to_base
        if ($decision === 'approved') {
            $returnRequest->trip->update([
                'status' => 'returning_to_base',
            ]);
        }

        return response()->json([
            'message' => "Return-to-base request has been {$decision}.",
            'data' => $returnRequest->fresh(['trip']),
        ]);
    }
}
