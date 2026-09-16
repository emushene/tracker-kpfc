<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    /**
     * Transform the vehicle resource into an array for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Obtain latest position if relation is loaded or query fallback
        $position = $this->relationLoaded('positions')
            ? $this->positions->first()
            : null;

        // Resolve active deployment if relation is loaded or helper method
        $activeDeployment = $this->relationLoaded('deployments')
            ? $this->deployments->first(fn ($d) => in_array($d->status, ['planned', 'dispatched', 'in_progress'], true))
            : null;

        // Derive high-level fleet operational status
        $operationalStatus = 'idle';
        if ($activeDeployment) {
            $operationalStatus = 'deployed';
        } elseif ($position && ($position->speed ?? 0) > 0) {
            $operationalStatus = 'moving';
        } elseif ($position && ($position->acc_status ?? 0) === 1) {
            $operationalStatus = 'idling';
        } elseif ($this->active) {
            $operationalStatus = 'parked';
        }

        return [
            // Vehicle identity
            'id' => $this->id,
            'imei' => $this->imei,
            'device_name' => $this->device_name,
            'plate_number' => $this->plate_number,
            'device_type' => $this->device_type,
            'simcard' => $this->simcard,
            'active' => (bool) $this->active,
            'status' => $operationalStatus,

            // Live location and timestamps
            'location_name' => $this->location_name,
            'location_latitude' => $this->location_latitude ? (float) $this->location_latitude : null,
            'location_longitude' => $this->location_longitude ? (float) $this->location_longitude : null,
            'location_updated_at' => $this->location_updated_at?->toIso8601String(),
            'last_position_at' => $this->last_position_at?->toIso8601String(),
            'online_at' => $this->online_at?->toIso8601String(),

            // Latest telemetry details if available
            'latest_telemetry' => $position ? [
                'latitude' => (float) $position->latitude,
                'longitude' => (float) $position->longitude,
                'speed' => (float) $position->speed,
                'course' => (float) $position->course,
                'battery' => $position->battery !== null ? (float) $position->battery : null,
                'ignition_on' => (bool) ($position->acc_status ?? false),
                'odometer' => $position->odometer,
                'gps_time' => $position->gps_time,
            ] : null,

            // Permanent Home Shop assignment
            'assigned_shop_id' => $this->assigned_shop_id,
            'assigned_shop' => $this->assignedShop ? new ShopResource($this->assignedShop) : null,

            // Active mission / deployment
            'active_deployment' => $activeDeployment ? new VehicleDeploymentResource($activeDeployment) : null,

            // Driving route & ETA metrics
            'routing' => [
                'destination_type' => $this->route_destination_type,
                'destination_id' => $this->route_destination_id,
                'distance_meters' => $this->road_distance_meters,
                'distance_km' => $this->road_distance_meters !== null
                    ? round($this->road_distance_meters / 1000, 2)
                    : null,
                'duration_seconds' => $this->road_duration_seconds,
                'duration_minutes' => $this->road_duration_seconds !== null
                    ? (int) round($this->road_duration_seconds / 60)
                    : null,
                'calculated_at' => $this->route_calculated_at?->toIso8601String(),
            ],

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
