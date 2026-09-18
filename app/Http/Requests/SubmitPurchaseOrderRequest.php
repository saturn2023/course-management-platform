<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesCheckoutDetails;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The purchase-order POST carries the student and billing details entered on
 * the checkout page plus the PO number and document. The file rules are
 * unchanged from the previous standalone PO page.
 */
class SubmitPurchaseOrderRequest extends FormRequest
{
    use ValidatesCheckoutDetails;

    /**
     * Purchase order is available only to logged-in admins and PO clients.
     * Guests and normal customers are rejected here, before validation.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->canPayByPurchaseOrder();
    }

    public function rules(): array
    {
        return $this->checkoutDetailRules() + [
            'purchase_order_number' => [
                'required',
                'string',
                'max:100',
            ],
            'purchase_order_document' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:25600',
            ],
        ];
    }

    public function messages(): array
    {
        return $this->checkoutDetailMessages() + [
            'purchase_order_number.required'   => 'A purchase order number is required.',
            'purchase_order_document.required' => 'Please attach your purchase order document.',
            'purchase_order_document.mimes'    => 'The purchase order must be a PDF, JPG or PNG file.',
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
