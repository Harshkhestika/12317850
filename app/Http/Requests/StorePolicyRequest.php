<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_request_id' => ['required', 'string', 'max:255'],
            'agent_id' => ['required', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'date_format:Y-m-d', 'before:today'],
            'destination' => ['required', 'string', 'in:DOMESTIC,INTERNATIONAL'],
            'trip_start' => ['required', 'date', 'date_format:Y-m-d'],
            'trip_end' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:trip_start'],
            'plan_code' => ['required', 'string', 'in:BASIC,PLUS,PREMIUM'],
            'sum_insured' => ['required', 'numeric', 'in:100000,300000,500000'],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'trip_end.after_or_equal' => 'The trip_end date must be greater than or equal to trip_start date.',
            'plan_code.in' => 'Unsupported plan_code. Valid plans: BASIC, PLUS, PREMIUM.',
            'destination.in' => 'Unsupported destination. Valid destinations: DOMESTIC, INTERNATIONAL.',
            'sum_insured.in' => 'Unsupported sum_insured coverage. Valid amounts: 100000, 300000, 500000.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given policy intake data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
