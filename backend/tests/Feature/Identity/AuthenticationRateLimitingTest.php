<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class AuthenticationRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sixth_failed_login_is_limited_with_retry_after(): void
    {
        User::factory()->create([
            'email' => 'limited@example.test',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login(
                email: 'limited@example.test',
                password: 'incorrect-password',
            )->assertUnauthorized();
        }

        $response = $this->login(
            email: 'limited@example.test',
            password: 'incorrect-password',
        );

        $this->assertRateLimited($response);
    }

    public function test_unknown_email_and_wrong_password_follow_equivalent_limits(): void
    {
        User::factory()->create([
            'email' => 'known@example.test',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $wrongPassword = $this->login(
                email: 'known@example.test',
                password: 'incorrect-password',
                ip: '192.0.2.10',
            );
            $unknownEmail = $this->login(
                email: 'unknown@example.test',
                password: 'incorrect-password',
                ip: '192.0.2.11',
            );

            $wrongPassword->assertUnauthorized();
            $unknownEmail->assertUnauthorized();
            self::assertSame($wrongPassword->json(), $unknownEmail->json());
        }

        $wrongPasswordLimited = $this->login(
            email: 'known@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.10',
        );
        $unknownEmailLimited = $this->login(
            email: 'unknown@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.11',
        );

        $this->assertRateLimited($wrongPasswordLimited);
        $this->assertRateLimited($unknownEmailLimited);
        self::assertSame($wrongPasswordLimited->json(), $unknownEmailLimited->json());
    }

    public function test_successful_login_clears_previous_failures(): void
    {
        User::factory()->create([
            'email' => 'recovered@example.test',
        ]);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->login(
                email: 'recovered@example.test',
                password: 'incorrect-password',
            )->assertUnauthorized();
        }

        $this->login(
            email: 'recovered@example.test',
            password: UserFactory::DEFAULT_PASSWORD,
        )->assertOk();

        $this->logout();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login(
                email: 'recovered@example.test',
                password: 'incorrect-password',
            )->assertUnauthorized();
        }

        $this->assertRateLimited($this->login(
            email: 'recovered@example.test',
            password: 'incorrect-password',
        ));
    }

    public function test_bcrypt_incompatible_credentials_are_generic_failures_and_consume_attempts(): void
    {
        User::factory()->create([
            'email' => 'bcrypt-incompatible@example.test',
        ]);

        $incompatiblePasswords = [
            'nul byte' => str_repeat('a', 12)."\0",
            'more than seventy two bytes' => str_repeat('a', 73),
        ];

        foreach ($incompatiblePasswords as $case => $password) {
            $ip = $case === 'nul byte' ? '192.0.2.12' : '192.0.2.13';

            $this->login(
                email: 'bcrypt-incompatible@example.test',
                password: $password,
                ip: $ip,
            )->assertUnauthorized()->assertExactJson([
                'message' => 'The provided credentials are incorrect.',
            ]);

            for ($attempt = 2; $attempt <= 5; $attempt++) {
                $this->login(
                    email: 'bcrypt-incompatible@example.test',
                    password: 'incorrect-password',
                    ip: $ip,
                )->assertUnauthorized();
            }

            $this->assertRateLimited($this->login(
                email: 'bcrypt-incompatible@example.test',
                password: 'incorrect-password',
                ip: $ip,
            ));
        }
    }

    public function test_normalised_email_variants_share_a_login_limit(): void
    {
        User::factory()->create([
            'email' => 'normalised@example.test',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $email = $attempt % 2 === 0
                ? 'normalised@example.test'
                : '  NORMALISED@EXAMPLE.TEST ';

            $this->login(
                email: $email,
                password: 'incorrect-password',
            )->assertUnauthorized();
        }

        $this->assertRateLimited($this->login(
            email: ' Normalised@Example.Test ',
            password: 'incorrect-password',
        ));
    }

    public function test_login_limits_are_separate_by_email_and_ip_combination(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login(
                email: 'first@example.test',
                password: 'incorrect-password',
                ip: '192.0.2.20',
            )->assertUnauthorized();
        }

        $this->login(
            email: 'second@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.20',
        )->assertUnauthorized();

        $this->login(
            email: 'first@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.21',
        )->assertUnauthorized();

        $this->assertRateLimited($this->login(
            email: 'first@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.20',
        ));
    }

    public function test_registration_is_limited_per_ip_and_counts_invalid_requests(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->register(ip: '192.0.2.30')->assertUnprocessable();
        }

        $this->assertRateLimited($this->register(ip: '192.0.2.30'));

        $this->register(ip: '192.0.2.31')->assertUnprocessable();
    }

    public function test_successful_registration_counts_towards_the_ip_limit(): void
    {
        $this->register(
            ip: '192.0.2.40',
            data: [
                'name' => 'Rate Limited Registration',
                'email' => 'registration-limit@example.test',
                'password' => 'carematch-registration-password',
                'password_confirmation' => 'carematch-registration-password',
            ],
        )->assertCreated();

        $this->logout();

        $this->register(ip: '192.0.2.40')->assertUnprocessable();
        $this->register(ip: '192.0.2.40')->assertUnprocessable();
        $this->assertRateLimited($this->register(ip: '192.0.2.40'));
    }

    public function test_authenticated_requests_are_rejected_before_guest_limiters_are_consumed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web');

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->login(
                email: 'guest-limit@example.test',
                password: 'incorrect-password',
                ip: '192.0.2.50',
            )->assertStatus(409);

            $this->register(ip: '192.0.2.50')->assertStatus(409);
        }

        Auth::guard('web')->logout();
        $this->app['session']->invalidate();
        Auth::forgetGuards();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->login(
                email: 'guest-limit@example.test',
                password: 'incorrect-password',
                ip: '192.0.2.50',
            )->assertUnauthorized();
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->register(ip: '192.0.2.50')->assertUnprocessable();
        }

        $this->assertRateLimited($this->login(
            email: 'guest-limit@example.test',
            password: 'incorrect-password',
            ip: '192.0.2.50',
        ));
        $this->assertRateLimited($this->register(ip: '192.0.2.50'));
        self::assertFalse(Schema::hasTable('organisations'));
        self::assertFalse(Schema::hasTable('organisation_memberships'));
    }

    private function login(
        string $email,
        string $password,
        string $ip = '192.0.2.1',
    ): TestResponse {
        return $this->statefulPost('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ], $ip);
    }

    /**
     * @param  array<string, string>  $data
     */
    private function register(string $ip, array $data = []): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/register', $data, $ip);
    }

    private function logout(): void
    {
        $this->statefulPost('/api/v1/auth/logout')->assertNoContent();
        Auth::forgetGuards();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function statefulPost(
        string $uri,
        array $data = [],
        string $ip = '192.0.2.1',
    ): TestResponse {
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
