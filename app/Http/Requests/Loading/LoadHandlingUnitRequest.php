<?php

namespace App\Http\Requests\Loading;

use App\Enums\LoadWarningType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoadHandlingUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'load',
            $this->route('manifest'),
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'barcode' => [
                'required',
                'string',
                'max:255',
            ],
            'client_event_id' => [
                'required',
                'uuid',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'acknowledged_warnings' => [
                'sometimes',
                'array',
            ],
            'acknowledged_warnings.*' => [
                Rule::enum(LoadWarningType::class),
            ],
        ];
    }

    public function acknowledgedWarnings(): array
    {
        return collect($this->validated('acknowledged_warnings', []))
            ->map(fn (string $warning) => LoadWarningType::from($warning))
            ->all();
    }
}
