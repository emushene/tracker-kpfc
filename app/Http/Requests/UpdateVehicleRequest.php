<?php

namespace App\Http\Requests;

use App\Models\Shop;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for updating a vehicle record.
     *
     * All fields are optional — only supplied fields are updated.
     * IMEI uniqueness check ignores the vehicle being updated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'imei' => ['sometimes', 'string', 'max:20', Rule::unique('vehicles', 'imei')->ignore($vehicle->id)],
            'plate_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'device_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'simcard' => ['sometimes', 'nullable', 'string', 'max:50'],
            'iccid' => ['sometimes', 'nullable', 'string', 'max:50'],
            'assigned_shop_id' => ['sometimes', 'nullable', 'integer', 'exists:shops,id'],
            'active' => ['sometimes', 'boolean'],
            'activated_at' => ['sometimes', 'nullable', 'date'],
            'location_latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'location_longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Reject assignments to inactive shops.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $shopId = $this->input('assigned_shop_id');

            if ($shopId !== null) {
                $shop = Shop::find($shopId);

                if ($shop !== null && ! $shop->active) {
                    $validator->errors()->add(
                        'assigned_shop_id',
                        'The selected shop is inactive and cannot be assigned.'
                    );
                }
            }
        });
    }
}
