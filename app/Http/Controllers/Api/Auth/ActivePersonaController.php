<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\SwitchPersonaRequest;
use App\Services\Auth\PersonaResolver;
use App\Support\RequestPersonaResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivePersonaController extends ApiController
{
    public function __construct(private readonly PersonaResolver $personaResolver)
    {
    }

    public function store(SwitchPersonaRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->fail('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $requestedKey = $request->validated('key');
        $personas = $this->personaResolver->resolve($user);

        $active = collect($personas)->firstWhere('key', $requestedKey);

        if ($active === null) {
            return $this->fail('Persona not found.', Response::HTTP_UNPROCESSABLE_ENTITY, [
                'key' => ['The selected persona is invalid.'],
            ]);
        }

        RequestPersonaResolver::remember($request, $active);

        return $this->ok([
            'personas' => $personas,
            'active_persona' => $active,
        ], 'Persona updated.');
    }
}
