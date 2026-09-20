<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SessionAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_authenticate_the_user_and_regenerate_the_session(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.test',
        ]);

        $this->withSession(['before_login' => true]);
        $previousSessionId = $this->app['session']->getId();

        $response = $this->login();

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', 'login@example.test')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'created_at'],
            ]);

        $data = $response->json('data');

        self::assertIsArray($data);
        self::assertSame(['created_at', 'email', 'id', 'name'], $this->sortedKeys($data));
        self::assertNotSame($previousSessionId, $this->app['session']->getId());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('organisation_memberships', 0);
    }

    public function test_email_is_normalised_before_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'normalised@example.test',
        ]);

        $this->login([
            'email' => '  NORMALISED@EXAMPLE.TEST ',
        ])->assertOk()->assertJsonPath('data.email', 'normalised@example.test');

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_unknown_email_and_wrong_password_have_identical_responses(): void
    {
        User::factory()->create([
            'email' => 'known@example.test',
        ]);

        $wrongPassword = $this->login([
            'email' => 'known@example.test',
            'password' => 'incorrect-password',
        ]);

        $unknownEmail = $this->login([
            'email' => 'unknown@example.test',
            'password' => 'incorrect-password',
        ]);

        $wrongPassword->assertUnauthorized()->assertExactJson([
            'message' => 'The provided credentials are incorrect.',
        ]);
        $unknownEmail->assertUnauthorized();

        self::assertSame($wrongPassword->getStatusCode(), $unknownEmail->getStatusCode());
        self::assertSame($wrongPassword->json(), $unknownEmail->json());
        $this->assertGuest('web');
    }

    public function test_appending_bytes_to_an_exactly_seventy_two_byte_password_does_not_authenticate(): void
    {
        $password = str_repeat('a', 72);

        User::factory()->create([
            'email' => 'bcrypt-boundary@example.test',
            'password' => $password,
        ]);

        $this->login([
            'email' => 'bcrypt-boundary@example.test',
            'password' => $password.'suffix',
        ])->assertUnauthorized()->assertExactJson([
            'message' => 'The provided credentials are incorrect.',
        ]);

        $this->assertGuest('web');
    }

    public function test_an_authenticated_login_is_rejected_without_switching_users(): void
    {
        $currentUser = User::factory()->create([
            'email' => 'current@example.test',
        ]);
        User::factory()->create([
            'email' => 'other@example.test',
        ]);

        $this->actingAs($currentUser, 'web');

        $this->login([
            'email' => 'other@example.test',
        ])->assertStatus(409)->assertExactJson([
            'message' => 'An authenticated user cannot perform this operation.',
        ]);

        $this->assertAuthenticatedAs($currentUser, 'web');
    }

    public function test_me_returns_only_the_current_global_user_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Current User',
            'email' => 'current-user@example.test',
        ]);

        $this->actingAs($user, 'web');

        $response = $this->statefulGet('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.name', 'Current User')
            ->assertJsonPath('data.email', 'current-user@example.test');

        $data = $response->json('data');

        self::assertIsArray($data);
        self::assertSame(['created_at', 'email', 'id', 'name'], $this->sortedKeys($data));
    }

    public function test_unauthenticated_me_returns_json_unauthorized(): void
    {
        $this->statefulGet('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    public function test_logout_invalidates_the_session_and_csrf_state(): void
    {
        User::factory()->create([
            'email' => 'login@example.test',
        ]);

        $this->login()->assertOk();
        $this->withSession(['old_session_state' => 'must be removed']);

        $session = $this->app['session'];
        $previousSessionId = $session->getId();
        $previousCsrfToken = $session->token();

        $this->statefulPost('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
        self::assertNotSame($previousSessionId, $session->getId());
        self::assertNotSame($previousCsrfToken, $session->token());
        self::assertFalse($session->has('old_session_state'));

        Auth::forgetGuards();

        $this->statefulGet('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_unauthenticated_logout_returns_json_unauthorized(): void
    {
        $this->statefulPost('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function login(array $overrides = []): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/login', array_merge([
            'email' => 'login@example.test',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ], $overrides));
    }

    /**
     * @param  array<string, string>  $data
     */
    private function statefulPost(string $uri, array $data = []): TestResponse
    {
        return $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson($uri, $data);
    }

    private function statefulGet(string $uri): TestResponse
    {
        return $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->getJson($uri);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
