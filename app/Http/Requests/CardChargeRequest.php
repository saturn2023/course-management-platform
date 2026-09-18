<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCheckoutDetails;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The card-payment POST carries the student and billing details entered on the
 * checkout page plus an opaque Pin card_token (and the CSRF token handled by
 * middleware). Everything financial — amount, currency, course — is still
 * derived server-side from the CheckoutSession and must never be trusted from
 * this request.
 */
class CardChargeRequest extends FormRequest
{
    use ValidatesCheckoutDetails;

    /**
     * Card checkout is open to guests and to logged-in users who are not
     * purchase-order-only clients. This runs before the rules, so an
     * unauthorised submission is rejected with 403 rather than leaking
     * validation feedback.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user === null || $user->canPayByCard();
    }

    public function rules(): array
    {
        return $this->checkoutDetailRules() + [
            'card_token' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return $this->checkoutDetailMessages() + [
            'card_token.required' => 'Your card could not be verified. Please re-enter your card details and try again.',
        ];
    }

    public function attributes(): array
    {
        return $this->checkoutDetailAttributes();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateStudentCount($validator));
    }
}
