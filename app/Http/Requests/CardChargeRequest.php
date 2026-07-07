<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The card-payment POST accepts ONLY an opaque Pin card_token (plus the CSRF
 * token handled by middleware). Every other value — amount, email, course —
 * is derived server-side from the CheckoutSession and must never be trusted
 * from this request.
 */
class CardChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_token' => ['required', 'string', 'max:255'],
        ];
    }
}
