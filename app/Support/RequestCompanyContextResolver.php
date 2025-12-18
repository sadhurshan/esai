<?php

namespace App\Support;

use App\Models\User;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use App\Support\RequestPersonaResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RequestCompanyContextResolver
{
    /**
     * @return array{user:User, companyId:int, persona:?ActivePersona}|null
     */
    public static function resolve(Request $request): ?array
    {
        $user = self::resolveRequestUser($request);

        if (! $user instanceof User) {
            return null;
        }

        $persona = RequestPersonaResolver::resolve($request, $user);

        if ($persona !== null) {
            $companyId = $persona->companyId();
        } else {
            $companyId = self::resolveUserCompanyId($user);
        }

        if ($companyId === null) {
            return null;
        }

        if ($user->company_id === null) {
            $user->company_id = $companyId;
        }

        $request->setUserResolver(static fn () => $user);

        return ['user' => $user, 'companyId' => $companyId, 'persona' => $persona];
    }

    public static function resolveRequestUser(Request $request): ?User
    {
        if (config('auth.guards.sanctum') !== null) {
            try {
                $sanctumUser = $request->user('sanctum');
            } catch (\InvalidArgumentException) {
                $sanctumUser = null;
            }

            if ($sanctumUser instanceof User) {
                return $sanctumUser;
            }
        }

        $defaultUser = $request->user();

        if ($defaultUser instanceof User) {
            return $defaultUser;
        }

        $sessionId = self::resolveSessionId($request);

        if ($sessionId === null) {
            return null;
        }

        $session = DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->first();

        if (! $session || ! $session->user_id) {
            return null;
        }

        $user = User::find((int) $session->user_id);

        if (! $user instanceof User) {
            return null;
        }

        $request->setUserResolver(static fn () => $user);

        DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->update(['last_activity' => Carbon::now()->getTimestamp()]);

        return $user;
    }

    public static function resolveUserCompanyId(User $user): ?int
    {
        $personaCompanyId = ActivePersonaContext::companyId();

        if ($personaCompanyId !== null) {
            return $personaCompanyId;
        }

        if ($user->company_id !== null) {
            return (int) $user->company_id;
        }

        $companyId = DB::table('company_user')
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->value('company_id');

        if ($companyId) {
            return (int) $companyId;
        }

        $ownedCompanyId = DB::table('companies')
            ->where('owner_user_id', $user->id)
            ->orderByDesc('created_at')
            ->value('id');

        return $ownedCompanyId ? (int) $ownedCompanyId : null;
    }

    private static function resolveSessionId(Request $request): ?string
    {
        $cookieName = config('session.cookie');

        if ($cookieName && $request->cookies->has($cookieName)) {
            $value = (string) $request->cookies->get($cookieName);

            if ($value !== '') {
                return $value;
            }
        }

        $bearer = $request->bearerToken();

        return $bearer !== '' ? $bearer : null;
    }
}
