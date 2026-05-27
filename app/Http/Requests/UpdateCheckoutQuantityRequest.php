<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCheckoutQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'A quantity is required.',
            'quantity.integer'  => 'Quantity must be a whole number.',
            'quantity.min'      => 'Quantity must be at least 1.',
            'quantity.max'      => 'Quantity cannot exceed 50.',
        ];
    }
}