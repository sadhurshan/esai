<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AuthenticateApiSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticateApiSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_attaches_active_persona_from_session_payload(): void
    {
        $user = User::factory()->create();
        $sessionId = 'sess-'.Str::random(16);
        $personaPayload = [
            'key' => 'supplier:'.$user->company_id,
            'type' => 'supplier',
            'company_id' => $user->company_id ?? 1,
            'supplier_id' => 1234,
        ];

        DB::table(config('session.table', 'sessions'))->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'testing-suite',
            'payload' => base64_encode(serialize([
                '_token' => Str::random(40),
                'active_persona' => $personaPayload,
            ])),
            'last_activity' => now()->getTimestamp(),
        ]);

        $request = Request::create('/middleware-test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$sessionId);

        $middleware = app(AuthenticateApiSession::class);

        $capturedPersona = null;
        $response = $middleware->handle($request, function (Request $handled) use (&$capturedPersona) {
            $capturedPersona = $handled->attributes->get('session.active_persona');

            return response()->noContent();
        });

        $this->assertSame(204, $response->getStatusCode());
        $this->assertIsArray($capturedPersona);
        $this->assertSame($personaPayload['supplier_id'], $capturedPersona['supplier_id']);
    }
}
