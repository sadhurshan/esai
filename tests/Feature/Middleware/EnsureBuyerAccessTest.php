<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureBuyerAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EnsureBuyerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_requester_with_write_permission_is_allowed(): void
    {
        $user = User::factory()
            ->for(Company::factory())
            ->create([
                'role' => 'buyer_requester',
            ]);

        $request = Request::create('/rfqs', 'POST');
        $request->setUserResolver(static fn () => $user);

        /** @var EnsureBuyerAccess $middleware */
        $middleware = app(EnsureBuyerAccess::class);

        $response = $middleware->handle($request, static fn () => response()->json(['ok' => true]));

        $this->assertSame(Response::HTTP_OK, $response->status());
    }

    public function test_buyer_member_without_write_permission_is_denied(): void
    {
        $user = User::factory()
            ->for(Company::factory())
            ->create([
                'role' => 'buyer_member',
            ]);

        $request = Request::create('/rfqs', 'POST');
        $request->setUserResolver(static fn () => $user);

        /** @var EnsureBuyerAccess $middleware */
        $middleware = app(EnsureBuyerAccess::class);

        $response = $middleware->handle($request, static fn () => response()->json(['ok' => true]));

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->status());
    }
}
