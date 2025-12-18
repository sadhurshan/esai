<?php

namespace App\Support;

use App\Models\SupplierContact;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestPersonaResolver
{
    private const ATTRIBUTE_KEY = 'session.active_persona';

    public static function resolve(Request $request, User $user): ?ActivePersona
    {
        $payload = self::resolvePersonaPayload($request);

        $persona = ActivePersona::fromArray($payload);

        if ($persona === null) {
            return null;
        }

        if (! self::personaBelongsToUser($persona, $user)) {
            self::forgetPersona($request);

            return null;
        }

        return $persona;
    }

    public static function remember(Request $request, ?array $persona): void
    {
        if ($persona === null) {
            self::forgetPersona($request);

            return;
        }

        $normalized = ActivePersona::fromArray($persona);

        if ($normalized === null) {
            self::forgetPersona($request);

            return;
        }

        if ($request->hasSession()) {
            $request->session()->put('active_persona', $normalized->toArray());
        }

        $request->attributes->set(self::ATTRIBUTE_KEY, $normalized->toArray());
    }

    private static function personaBelongsToUser(ActivePersona $persona, User $user): bool
    {
        if ($persona->type() === ActivePersona::TYPE_BUYER) {
            return self::isCompanyMember($user, $persona->companyId());
        }

        return self::isSupplierContact($user, $persona);
    }

    private static function isCompanyMember(User $user, int $companyId): bool
    {
        if ((int) $user->company_id === $companyId) {
            return true;
        }

        $membership = DB::table('company_user')
            ->where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->exists();

        return $membership;
    }

    private static function isSupplierContact(User $user, ActivePersona $persona): bool
    {
        $supplierId = $persona->supplierId();

        if ($supplierId === null) {
            return false;
        }

        return SupplierContact::query()
            ->where('user_id', $user->id)
            ->where('company_id', $persona->companyId())
            ->where('supplier_id', $supplierId)
            ->exists();
    }

    private static function resolvePersonaPayload(Request $request): ?array
    {
        if ($request->hasSession()) {
            $payload = $request->session()->get('active_persona');

            if (is_array($payload)) {
                return $payload;
            }
        }

        $attributePayload = $request->attributes->get(self::ATTRIBUTE_KEY);

        return is_array($attributePayload) ? $attributePayload : null;
    }

    private static function forgetPersona(Request $request): void
    {
        if ($request->hasSession()) {
            $request->session()->forget('active_persona');
        }

        $request->attributes->set(self::ATTRIBUTE_KEY, null);
    }
}
