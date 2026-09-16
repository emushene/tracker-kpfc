<?php

namespace App\Http\Requests;

use App\Models\Shop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignShopRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Default to true; authorization policies can be added as auth is implemented.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for assigning a vehicle to a home shop.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The shop ID to set as the vehicle's permanent home base.
            // Nullable allows clearing the assignment if necessary.
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ];
    }

    /**
     * Configure the validator instance for custom business rule checks.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $shopId = $this->input('shop_id');

            // If a shop ID was supplied, verify that the shop is active.
            if ($shopId !== null) {
                $shop = Shop::find($shopId);

                if ($shop !== null && ! $shop->active) {
                    $validator->errors()->add(
                        'shop_id',
                        'The selected shop is inactive and cannot be assigned.'
                    );
                }
            }
        });
    }
}
