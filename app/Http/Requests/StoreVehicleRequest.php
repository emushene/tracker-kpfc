<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
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
            'imei' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'imei')],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'string', 'max:100'],
            'simcard' => ['nullable', 'string', 'max:50'],
            'iccid' => ['nullable', 'string', 'max:50'],
            'assigned_shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'active' => ['nullable', 'boolean'],
            'activated_at' => ['nullable', 'date'],
            'location_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'location_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance for custom business rules.
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
