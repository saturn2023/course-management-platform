<?php

namespace App\Http\Requests;

use App\Models\CheckoutSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveCheckoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'students'                  => ['required', 'array', 'min:1', 'max:50'],
            'students.*.first_name'     => ['required', 'string', 'max:100'],
            'students.*.last_name'      => ['required', 'string', 'max:100'],
            'students.*.email'          => ['required', 'email', 'max:255'],
            'students.*.phone'          => ['required', 'string', 'max:30'],

            'billing'                   => ['required', 'array'],
            'billing.first_name'        => ['required', 'string', 'max:100'],
            'billing.last_name'         => ['required', 'string', 'max:100'],
            'billing.company'           => ['nullable', 'string', 'max:255'],
            'billing.address_1'         => ['nullable', 'string', 'max:255'],
            'billing.address_2'         => ['nullable', 'string', 'max:255'],
            'billing.city'              => ['nullable', 'string', 'max:100'],
            'billing.postcode'          => ['nullable', 'string', 'max:20'],
            'billing.phone'             => ['nullable', 'string', 'max:30'],
            'billing.email'             => ['required', 'email', 'max:255'],
            'billing.abn'               => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'students.required'             => 'Student details are required.',
            'students.array'                => 'Student details must be a list.',
            'students.*.first_name.required' => 'Each student must have a first name.',
            'students.*.last_name.required'  => 'Each student must have a last name.',
            'students.*.email.required'      => 'Each student must have an email address.',
            'students.*.email.email'         => 'Each student must have a valid email address.',
            'students.*.phone.required'      => 'Each student must have a phone number.',

            'billing.first_name.required'    => 'Billing first name is required.',
            'billing.last_name.required'     => 'Billing last name is required.',
            'billing.email.required'         => 'Billing email is required.',
            'billing.email.email'            => 'Billing email must be a valid email address.',
        ];
    }

    public function attributes(): array
    {
        return [
            'billing.first_name' => 'first name',
            'billing.last_name'  => 'last name',
            'billing.email'      => 'email',
        ];
    }

    /**
     * Cross-field rule: number of students must equal the checkout quantity.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $session = $this->route('checkoutSession');

            if (! $session instanceof CheckoutSession) {
                return;
            }

            $students = $this->input('students', []);
            $expected = (int) $session->quantity;
            $actual   = is_array($students) ? count($students) : 0;

            if ($actual !== $expected) {
                $validator->errors()->add(
                    'students',
                    "Please provide details for {$expected} student(s). You submitted {$actual}."
                );
            }
        });
    }
}