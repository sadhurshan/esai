<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Company\RegisterCompanyAction;
use App\Actions\Company\StoreCompanyDocumentAction;
use App\Events\CompanyPendingVerification;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\SelfRegistrationRequest;
use App\Models\User;
use App\Services\Auth\AuthResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SelfRegistrationController extends ApiController
{
    public function __construct(
        private readonly RegisterCompanyAction $registerCompanyAction,
        private readonly StoreCompanyDocumentAction $storeCompanyDocumentAction,
        private readonly AuthResponseFactory $authResponseFactory,
    ) {}

    public function register(SelfRegistrationRequest $request): JsonResponse
    {
        if ($request->user()) {
            return $this->fail('Already authenticated.', 409);
        }

        $data = $request->validated();

        /** @var User|null $user */
        $user = null;
        $company = null;

        DB::transaction(function () use (&$user, &$company, $data): void {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'owner',
            ]);

            $company = $this->registerCompanyAction->execute([
                'name' => $data['company_name'],
                'registration_no' => $data['registration_no'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'country' => $data['country'] ?? null,
                'email_domain' => $data['company_domain'],
                'primary_contact_name' => $data['name'],
                'primary_contact_email' => $data['email'],
                'primary_contact_phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'website' => $data['website'] ?? null,
            ], $user);
        });

        if ($company !== null) {
            foreach ($request->companyDocuments() as $document) {
                $this->storeCompanyDocumentAction->execute($company, $document['type'], $document['file']);
            }

            event(new CompanyPendingVerification($company));
        }

        Auth::login($user);
        $request->session()->regenerate();

        $authPayload = $this->authResponseFactory->make($user->fresh('company'), $request->session()->getId());

        return $this->ok($authPayload, 'Registration successful.');
    }
}
