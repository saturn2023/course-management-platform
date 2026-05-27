<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutLoadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100'],
            'plan' => ['required', 'string', 'max:100'],
            'schedule' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'A course code is required.',
            'plan.required' => 'A payment plan must be selected.',
            'quantity.integer' => 'Quantity must be a valid number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity is too large.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => $this->input('quantity', 1),
        ]);
    }
}