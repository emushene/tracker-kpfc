<?php

namespace App\Http\Requests;

use App\Models\Location;
use App\Models\Shop;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeploymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for creating/dispatching a vehicle deployment.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Destination type: either a permanent shop or a custom location.
            'destination_type' => ['required', 'string', 'in:shop,location'],

            // Destination record ID corresponding to the selected destination_type.
            'destination_id' => ['required', 'integer'],

            // Optional operational purpose of the dispatch (e.g. Delivery, Maintenance).
            'purpose' => ['nullable', 'string', 'max:255'],

            // Optional notes or special instructions for the deployment.
            'notes' => ['nullable', 'string', 'max:1000'],

            // Deployment status: defaults to dispatched if not specified.
            'status' => ['nullable', 'string', 'in:planned,dispatched'],
        ];
    }

    /**
     * Validate business constraints: destination existence/activity and single active deployment.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehicle = $this->route('vehicle');

            // 1. Ensure the vehicle exists and does not already have an active deployment.
            if ($vehicle instanceof Vehicle) {
                $hasActiveDeployment = $vehicle->deployments()
                    ->whereIn('status', ['planned', 'dispatched', 'in_progress'])
                    ->exists();

                if ($hasActiveDeployment) {
                    $validator->errors()->add(
                        'vehicle',
                        'This vehicle already has an active deployment in progress.'
                    );
                }
            }

            // 2. Validate that the targeted destination exists and is active.
            $type = $this->input('destination_type');
            $id = $this->input('destination_id');

            if ($type === 'shop' && $id !== null) {
                $shop = Shop::find($id);

                if ($shop === null) {
                    $validator->errors()->add('destination_id', 'The selected shop destination does not exist.');
                } elseif (! $shop->active) {
                    $validator->errors()->add('destination_id', 'The selected shop destination is inactive.');
                }
            } elseif ($type === 'location' && $id !== null) {
                $location = Location::find($id);

                if ($location === null) {
                    $validator->errors()->add('destination_id', 'The selected location destination does not exist.');
                } elseif (! $location->active) {
                    $validator->errors()->add('destination_id', 'The selected location destination is inactive.');
                }
            }
        });
    }
}
