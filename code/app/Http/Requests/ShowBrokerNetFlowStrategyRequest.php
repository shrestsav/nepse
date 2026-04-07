<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowBrokerNetFlowStrategyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'minTurnover' => ['nullable', 'numeric', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
