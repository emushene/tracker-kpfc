<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'course' => ['nullable', 'numeric', 'between:0,360'],
            'battery' => ['nullable', 'numeric'],
            'acc_status' => ['nullable', 'integer', 'in:0,1'],
            'ignition_on' => ['nullable', 'boolean'],
            'mileage' => ['nullable', 'integer', 'min:0'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'gps_time' => ['nullable', 'integer'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
