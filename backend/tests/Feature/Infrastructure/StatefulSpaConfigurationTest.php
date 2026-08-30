<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Session\SessionManager;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

final class StatefulSpaConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_api_middleware_group_enables_sanctum_stateful_requests(): void
    {
        $middlewareGroups = $this->app->make(Router::class)->getMiddlewareGroups();

        self::assertContains(
            EnsureFrontendRequestsAreStateful::class,
            $middlewareGroups['api'],
        );
        self::assertSame(['localhost:3000'], config('sanctum.stateful'));
        self::assertSame('carematch_session', config('session.cookie'));
        self::assertFalse((bool) config('session.secure'));
        self::assertTrue((bool) config('session.http_only'));
        self::assertSame('lax', config('session.same_site'));
    }

    public function test_the_standard_sanctum_endpoint_issues_the_csrf_cookie(): void
    {
        $response = $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->get('/sanctum/csrf-cookie');

        $response
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertCookie('carematch_session')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_cors_allows_only_the_configured_frontend_with_credentials(): void
    {
        $approvedResponse = $this
            ->withHeaders([
                'Origin' => 'http://localhost:3000',
                'Access-Control-Request-Method' => 'GET',
            ])
            ->options('/api/v1/health');

        $approvedResponse
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $unapprovedResponse = $this
            ->withHeaders([
                'Origin' => 'https://unapproved.example',
                'Access-Control-Request-Method' => 'GET',
            ])
            ->options('/api/v1/health');

        self::assertNotSame(
            'https://unapproved.example',
            $unapprovedResponse->headers->get('Access-Control-Allow-Origin'),
        );
        self::assertNotSame(
            '*',
            $unapprovedResponse->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_a_session_can_be_persisted_with_the_database_driver(): void
    {
        config()->set('session.driver', 'database');

        $sessionManager = $this->app->make(SessionManager::class);
        $sessionManager->forgetDrivers();

        $session = $sessionManager->driver();
        $sessionId = Str::random(40);

        $session->setId($sessionId);
        $session->start();
        $session->put('phase', '2-a');
        $session->save();

        $this->assertDatabaseHas('sessions', [
            'id' => $sessionId,
        ]);
    }
}
