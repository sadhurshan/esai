<?php

namespace App\Http\Requests\Company;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class AcceptCompanyInvitationRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isAuthenticated = $this->user() !== null;

        $nameRules = ['string', 'max:160'];
        array_unshift($nameRules, $isAuthenticated ? 'nullable' : 'required');

        $passwordRules = ['confirmed'];
        if ($isAuthenticated) {
            array_unshift($passwordRules, 'nullable');
        } else {
            array_unshift($passwordRules, 'required');
            $passwordRules[] = Password::default();
        }

        return [
            'email' => ['required', 'email:filter', 'max:255'],
            'name' => $nameRules,
            'password' => $passwordRules,
        ];
    }
}
