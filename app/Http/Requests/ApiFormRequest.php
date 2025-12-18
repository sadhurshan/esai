<?php

namespace App\Http\Requests;

use App\Http\Controllers\Api\Concerns\RespondsWithEnvelope;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class ApiFormRequest extends FormRequest
{
    use RespondsWithEnvelope;

    /**
     * Handle a failed validation attempt by emitting the envelope error response.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            $this->fail('Validation failed', 422, $validator->errors()->toArray())
        );
    }

    /**
     * Emit an envelope-compliant response when authorization fails.
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            $this->fail('This action is unauthorized.', 403)
        );
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
