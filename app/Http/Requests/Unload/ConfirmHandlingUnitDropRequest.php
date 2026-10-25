<?php

namespace App\Http\Requests\Unload;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmHandlingUnitDropRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
            // The bay the runner was shown; the server re-checks it is still right.
            'location_id' => ['required', 'uuid'],
            'client_event_id' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
            // Set when the confirm waited in the device's offline queue: the
            // pallet is already down, so where it was put is the truth.
            'queued' => ['sometimes', 'boolean'],
        ];
    }
}
