<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_unknown_emails_receive_the_same_public_forgot_response(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'forgotten@example.test',
        ]);

        $existing = $this->forgot('  FORGOTTEN@EXAMPLE.TEST ', '192.0.2.60');
        $unknown = $this->forgot('unknown@example.test', '192.0.2.61');

        $existing->assertAccepted()->assertExactJson($this->forgotResponse());
        $unknown->assertAccepted();

        self::assertSame($existing->getStatusCode(), $unknown->getStatusCode());
        self::assertSame($existing->json(), $unknown->json());

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertCount(1);
    }

    public function test_reset_token_is_hashed_and_the_notification_targets_the_configured_frontend(): void
    {
        Notification::fake();
        config(['carematch.frontend_url' => 'https://frontend.carematch.test']);

        $user = User::factory()->create([
            'email' => 'reset-link@example.test',
        ]);

        $this
            ->withHeader('Host', 'attacker.example.test')
            ->forgot('reset-link@example.test')
            ->assertAccepted();

        $token = null;
        $resetUrl = null;

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user, &$resetUrl, &$token): bool {
                $token = $notification->token;
                $resetUrl = $notification->toMail($user)->actionUrl;

                return true;
            },
        );

        self::assertIsString($token);
        self::assertIsString($resetUrl);

        $storedToken = DB::table('password_reset_tokens')
            ->where('email', 'reset-link@example.test')
            ->value('token');

        self::assertIsString($storedToken);
        self::assertNotSame($token, $storedToken);
        self::assertTrue(Hash::check($token, $storedToken));

        $parts = parse_url($resetUrl);

        self::assertIsArray($parts);
        self::assertSame('https', $parts['scheme'] ?? null);
        self::assertSame('frontend.carematch.test', $parts['host'] ?? null);
        self::assertSame('/reset-password', $parts['path'] ?? null);

        parse_str((string) ($parts['query'] ?? ''), $query);

        self::assertSame($token, $query['token'] ?? null);
        self::assertSame('reset-link@example.test', $query['email'] ?? null);
    }

    public function test_valid_reset_changes_credentials_invalidates_only_target_sessions_and_prevents_sequential_reuse(): void
    {
        Event::fake([PasswordReset::class]);

        $user = User::factory()->create([
            'email' => 'reset-user@example.test',
            'remember_token' => 'old-remember-token',
        ]);
        $otherUser = User::factory()->create([
            'email' => 'other-user@example.test',
        ]);
        $token = Password::createToken($user);

        $this->insertSession('target-session-one', (int) $user->getKey());
        $this->insertSession('target-session-two', (int) $user->getKey());
        $this->insertSession('other-user-session', (int) $otherUser->getKey());

        $newPassword = 'carematch-new-password';
        $payload = [
            'token' => $token,
            'email' => '  RESET-USER@EXAMPLE.TEST ',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ];

        $this->resetPassword($payload)->assertNoContent();
        $this->assertGuest('web');

        $user->refresh();

        self::assertTrue(Hash::check($newPassword, $user->password));
        self::assertFalse(Hash::check(UserFactory::DEFAULT_PASSWORD, $user->password));
        self::assertNotSame($newPassword, $user->password);
        self::assertNotSame('old-remember-token', $user->getRememberToken());
        self::assertIsString($user->getRememberToken());
        self::assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        self::assertSame(1, DB::table('sessions')->where('user_id', $otherUser->getKey())->count());

        Event::assertDispatched(
            PasswordReset::class,
            static fn (PasswordReset $event): bool => $event->user->is($user),
        );

        $this->resetPassword($payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        $this->login('reset-user@example.test', UserFactory::DEFAULT_PASSWORD)
            ->assertUnauthorized();
        $this->login('reset-user@example.test', $newPassword)
            ->assertOk();

        $this->assertDatabaseCount('organisations', 0);
        $this->assertDatabaseCount('organisation_memberships', 0);
    }

    public function test_invalid_and_expired_tokens_use_the_same_safe_error(): void
    {
        $invalidUser = User::factory()->create([
            'email' => 'invalid-token@example.test',
        ]);
        $expiredUser = User::factory()->create([
            'email' => 'expired-token@example.test',
        ]);
        $expiredToken = Password::createToken($expiredUser);

        DB::table('password_reset_tokens')
            ->where('email', 'expired-token@example.test')
            ->update(['created_at' => now()->subMinutes(61)]);

        $invalid = $this->resetPassword($this->resetPayload(
            email: (string) $invalidUser->email,
            token: 'invalid-token',
        ));
        $expired = $this->resetPassword($this->resetPayload(
            email: (string) $expiredUser->email,
            token: $expiredToken,
        ));

        $invalid->assertUnprocessable()->assertJsonValidationErrors(['token']);
        $expired->assertUnprocessable()->assertJsonValidationErrors(['token']);
        self::assertSame($invalid->json(), $expired->json());
    }

    public function test_reset_password_uses_the_shared_creation_policy(): void
    {
        $user = User::factory()->create([
            'email' => 'password-policy@example.test',
        ]);

        $short = $this->resetPayload((string) $user->email, 'unused', str_repeat('a', 11));
        $overBytes = $this->resetPayload((string) $user->email, 'unused', str_repeat('a', 73));
        $multibyteOverBytes = $this->resetPayload((string) $user->email, 'unused', str_repeat('あ', 25));
        $mismatch = $this->resetPayload((string) $user->email, 'unused');
        $mismatch['password_confirmation'] = 'different-confirmation';

        $this->resetPassword($short)->assertJsonValidationErrors(['password']);
        $this->resetPassword($overBytes)->assertJsonValidationErrors(['password']);
        $this->resetPassword($multibyteOverBytes)->assertJsonValidationErrors(['password']);
        $this->resetPassword($mismatch)->assertJsonValidationErrors(['password']);
    }

    public function test_a_seventy_two_byte_password_is_accepted(): void
    {
        $user = User::factory()->create([
            'email' => 'password-boundary@example.test',
        ]);
        $token = Password::createToken($user);
        $password = str_repeat('a', 72);

        $this->resetPassword($this->resetPayload(
            email: ' PASSWORD-BOUNDARY@EXAMPLE.TEST ',
            token: $token,
            password: $password,
        ))->assertNoContent();

        $user->refresh();

        self::assertTrue(Hash::check($password, $user->password));
    }

    public function test_a_password_containing_a_nul_byte_is_rejected_without_a_server_error(): void
    {
        $user = User::factory()->create([
            'email' => 'nul-password@example.test',
        ]);
        $password = str_repeat('a', 12)."\0";

        $this->resetPassword($this->resetPayload(
            email: (string) $user->email,
            token: 'unused-token',
            password: $password,
        ))->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    private function forgot(string $email, string $ip = '192.0.2.70'): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/forgot-password', [
            'email' => $email,
        ], $ip);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function resetPassword(array $payload, string $ip = '192.0.2.71'): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/reset-password', $payload, $ip);
    }

    private function login(string $email, string $password): TestResponse
    {
        return $this->statefulPost('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ], '192.0.2.72');
    }

    /**
     * @return array<string, string>
     */
    private function resetPayload(
        string $email,
        string $token,
        string $password = 'carematch-reset-password',
    ): array {
        return [
            'token' => $token,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    private function statefulPost(string $uri, array $data, string $ip): TestResponse
    {
        return $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson($uri, $data);
    }

    private function insertSession(string $id, int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '192.0.2.73',
            'user_agent' => 'CareMatch test',
            'payload' => 'test-payload',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function forgotResponse(): array
    {
        return [
            'message' => 'If an account exists for that email, a password reset link will be sent.',
        ];
    }
}
