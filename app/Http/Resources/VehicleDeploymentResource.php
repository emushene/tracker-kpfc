<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDeploymentResource extends JsonResource
{
    /**
     * Transform the vehicle deployment resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resolve destination model (either Shop or Location)
        $destination = $this->destination;

        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'destination_type' => $this->destination_type,
            'destination_id' => $this->destination_id,
            'destination' => $destination ? [
                'type' => $this->destination_type,
                'id' => $destination->id,
                'name' => $destination->name,
                'address' => $destination->address,
                'latitude' => (float) $destination->latitude,
                'longitude' => (float) $destination->longitude,
            ] : null,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'dispatched_at' => $this->dispatched_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
