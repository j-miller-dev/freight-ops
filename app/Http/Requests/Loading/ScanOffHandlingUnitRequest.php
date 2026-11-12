<?php

namespace App\Http\Requests\Loading;

use Illuminate\Foundation\Http\FormRequest;

class ScanOffHandlingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('load', $this->route('manifest')) ?? false;
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
            'client_event_id' => ['required', 'uuid'],
            'occurred_at' => ['required', 'date'],
        ];
    }
}
