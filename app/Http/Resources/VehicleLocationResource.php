<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Obtain latest position if relation is loaded or fallback query
        $position = $this->relationLoaded('positions')
            ? $this->positions->first()
            : $this->positions()->latest('gps_time')->first();

        // Resolve active deployment if loaded
        $activeDeployment = $this->relationLoaded('deployments')
            ? $this->deployments->first(fn ($d) => in_array($d->status, ['planned', 'dispatched', 'in_progress'], true))
            : $this->deployments()->whereIn('status', ['planned', 'dispatched', 'in_progress'])->first();

        // Determine operational status
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

        $latitude = $this->location_latitude !== null
            ? (float) $this->location_latitude
            : ($position?->latitude !== null ? (float) $position->latitude : null);

        $longitude = $this->location_longitude !== null
            ? (float) $this->location_longitude
            : ($position?->longitude !== null ? (float) $position->longitude : null);

        $isOnline = false;
        if ($this->online_at !== null) {
            $isOnline = $this->online_at->gt(now()->subMinutes(15));
        }

        return [
            'vehicle_id' => $this->id,
            'plate_number' => $this->plate_number,
            'imei' => $this->imei,
            'device_name' => $this->device_name,
            'device_type' => $this->device_type,
            'active' => (bool) $this->active,
            'status' => $operationalStatus,
            'is_online' => $isOnline,

            'location' => [
                'name' => $this->location_name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'updated_at' => $this->location_updated_at?->toIso8601String(),
            ],

            'telemetry' => $position ? [
                'latitude' => (float) $position->latitude,
                'longitude' => (float) $position->longitude,
                'speed' => $position->speed !== null ? (float) $position->speed : null,
                'course' => $position->course !== null ? (float) $position->course : null,
                'battery' => $position->battery !== null ? (float) $position->battery : null,
                'ignition_on' => (bool) ($position->acc_status ?? false),
                'odometer' => $position->odometer,
                'mileage' => $position->mileage,
                'gps_time' => $position->gps_time,
                'recorded_at' => $position->gps_time ? date('c', (int) $position->gps_time) : null,
            ] : null,

            'assigned_shop' => $this->assignedShop ? [
                'id' => $this->assignedShop->id,
                'name' => $this->assignedShop->name,
                'code' => $this->assignedShop->code,
                'address' => $this->assignedShop->address,
                'latitude' => $this->assignedShop->latitude ? (float) $this->assignedShop->latitude : null,
                'longitude' => $this->assignedShop->longitude ? (float) $this->assignedShop->longitude : null,
                'radius_meters' => $this->assignedShop->radius_meters,
            ] : null,

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

            'last_seen_at' => $this->last_position_at?->toIso8601String() ?? $this->online_at?->toIso8601String(),
        ];
    }
}
