<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PasswordResetRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_limits_existing_and_unknown_emails_equivalently(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'forgot-limit@example.test',
        ]);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $existing = $this->forgot(
                email: $attempt % 2 === 0
                    ? 'forgot-limit@example.test'
                    : ' FORGOT-LIMIT@EXAMPLE.TEST ',
                ip: '192.0.2.80',
            );
            $unknown = $this->forgot('unknown-limit@example.test', '192.0.2.81');

            $existing->assertAccepted();
            $unknown->assertAccepted();
            self::assertSame($existing->json(), $unknown->json());
        }

        $existingLimited = $this->forgot('forgot-limit@example.test', '192.0.2.80');
        $unknownLimited = $this->forgot('unknown-limit@example.test', '192.0.2.81');

        $this->assertRateLimited($existingLimited);
        $this->assertRateLimited($unknownLimited);
        self::assertSame($existingLimited->json(), $unknownLimited->json());
    }

    public function test_reset_password_limits_token_variants_by_normalised_email_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $email = $attempt % 2 === 0
                ? 'reset-limit@example.test'
                : ' RESET-LIMIT@EXAMPLE.TEST ';

            $this->resetPassword([
                'token' => 'invalid-token-'.$attempt,
                'email' => $email,
                'password' => 'carematch-reset-password',
                'password_confirmation' => 'carematch-reset-password',
            ], '192.0.2.82')->assertUnprocessable();
        }

        $this->assertRateLimited($this->resetPassword([
            'token' => 'another-invalid-token',
            'email' => 'reset-limit@example.test',
            'password' => 'carematch-reset-password',
            'password_confirmation' => 'carematch-reset-password',
        ], '192.0.2.82'));

        $this->resetPassword([
            'token' => 'another-invalid-token',
            'email' => 'different@example.test',
            'password' => 'carematch-reset-password',
            'password_confirmation' => 'carematch-reset-password',
        ], '192.0.2.82')->assertUnprocessable();

        $this->resetPassword([
            'token' => 'another-invalid-token',
            'email' => 'reset-limit@example.test',
            'password' => 'carematch-reset-password',
            'password_confirmation' => 'carematch-reset-password',
        ], '192.0.2.83')->assertUnprocessable();
    }

    public function test_malformed_email_inputs_are_safely_limited_before_validation(): void
    {
        $forgotInputs = [
            [],
            ['email' => ['not', 'a', 'string']],
            ['email' => null],
        ];

        foreach ($forgotInputs as $payload) {
            $this->statefulPost('/api/v1/auth/forgot-password', $payload, '192.0.2.84')
                ->assertUnprocessable();
        }

        $this->assertRateLimited($this->statefulPost(
            '/api/v1/auth/forgot-password',
            ['email' => ['still', 'invalid']],
            '192.0.2.84',
        ));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $payload = $attempt % 2 === 0
                ? ['email' => ['not', 'a', 'string']]
                : [];

            $this->statefulPost('/api/v1/auth/reset-password', $payload, '192.0.2.85')
                ->assertUnprocessable();
        }

        $this->assertRateLimited($this->statefulPost(
            '/api/v1/auth/reset-password',
            ['email' => null],
            '192.0.2.85',
        ));
    }

    public function test_authenticated_recovery_requests_do_not_consume_guest_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->forgot('guest-recovery@example.test', '192.0.2.86')->assertStatus(409);
            $this->resetPassword([
                'token' => 'invalid-token',
                'email' => 'guest-recovery@example.test',
                'password' => 'carematch-reset-password',
                'password_confirmation' => 'carematch-reset-password',
            ], '192.0.2.86')->assertStatus(409);
        }

        Auth::guard('web')->logout();
        $this->app['session']->invalidate();
        Auth::forgetGuards();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->forgot('guest-recovery@example.test', '192.0.2.86')->assertAccepted();
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->resetPassword([
                'token' => 'invalid-token-'.$attempt,
                'email' => 'guest-recovery@example.test',
                'password' => 'carematch-reset-password',
                'password_confirmation' => 'carematch-reset-password',
            ], '192.0.2.86')->assertUnprocessable();
        }

        $this->assertRateLimited($this->forgot('guest-recovery@example.test', '192.0.2.86'));
        $this->assertRateLimited($this->resetPassword([
            'token' => 'another-invalid-token',
            'email' => 'guest-recovery@example.test',
            'password' => 'carematch-reset-password',
            'password_confirmation' => 'carematch-reset-password',
        ], '192.0.2.86'));
    }

    private function forgot(string $email, string $ip): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/forgot-password', [
            'email' => $email,
        ], $ip);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resetPassword(array $payload, string $ip): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/reset-password', $payload, $ip);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function statefulPost(string $uri, array $data, string $ip): TestResponse
    {
        return $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson($uri, $data);
    }

    private function assertRateLimited(TestResponse $response): void
    {
        $response->assertStatus(429)->assertExactJson([
            'message' => 'Too many attempts. Please try again later.',
        ]);

        $retryAfter = $response->headers->get('Retry-After');

        self::assertNotNull($retryAfter);
        self::assertTrue(ctype_digit($retryAfter));
        self::assertGreaterThanOrEqual(1, (int) $retryAfter);
        self::assertLessThanOrEqual(60, (int) $retryAfter);
    }
}
