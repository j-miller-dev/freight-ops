<?php

namespace App\Http\Requests\Unload;

use Illuminate\Foundation\Http\FormRequest;

class DirectHandlingUnitRequest extends FormRequest
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
        ];
    }
}
