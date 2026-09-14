<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Application\Contracts\UserRegistrationStore;
use App\Modules\Identity\Application\Exceptions\EmailAlreadyRegistered;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

final class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_register_and_is_authenticated_with_a_regenerated_session(): void
    {
        $this->withSession(['before_registration' => true]);
        $previousSessionId = $this->app['session']->getId();

        $response = $this->register([
            'name' => 'Registered User',
            'email' => '  REGISTERED@EXAMPLE.TEST ',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Registered User')
            ->assertJsonPath('data.email', 'registered@example.test')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'created_at'],
            ]);

        $user = User::query()->where('email', 'registered@example.test')->sole();

        self::assertTrue(Hash::check('carematch-registration-password', $user->password));
        self::assertNotSame('carematch-registration-password', $user->password);
        self::assertNotSame($previousSessionId, $this->app['session']->getId());
        $this->assertAuthenticatedAs($user, 'web');

        $data = $response->json('data');

        self::assertIsArray($data);
        self::assertSame(['created_at', 'email', 'id', 'name'], $this->sortedKeys($data));
        self::assertFalse(Schema::hasTable('organisations'));
        self::assertFalse(Schema::hasTable('organisation_memberships'));
    }

    public function test_an_authenticated_user_receives_conflict_without_creating_another_user(): void
    {
        $existingUser = User::factory()->create();

        $this->actingAs($existingUser, 'web');

        $this->register([
            'email' => 'another@example.test',
        ])->assertStatus(409)->assertExactJson([
            'message' => 'An authenticated user cannot perform this operation.',
        ]);

        self::assertSame(1, User::query()->count());
        $this->assertAuthenticatedAs($existingUser, 'web');
    }

    public function test_a_duplicate_normalised_email_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'existing@example.test',
        ]);

        $this->register([
            'email' => ' EXISTING@EXAMPLE.TEST ',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        self::assertSame(1, User::query()->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->register([
            'email' => 'not-an-email',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_a_password_below_twelve_characters_is_rejected(): void
    {
        $this->register([
            'password' => 'short-pass',
            'password_confirmation' => 'short-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_a_password_over_seventy_two_bytes_is_rejected(): void
    {
        $password = str_repeat('a', 73);

        $this->register([
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_a_multibyte_password_over_seventy_two_bytes_is_rejected(): void
    {
        $password = str_repeat('あ', 25);

        self::assertSame(75, strlen($password));

        $this->register([
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_a_password_containing_a_nul_byte_is_rejected_without_a_server_error(): void
    {
        $password = str_repeat('a', 12)."\0";

        $this->register([
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_an_exactly_seventy_two_byte_password_can_be_created_and_authenticated(): void
    {
        $password = str_repeat('a', 72);

        $this->register([
            'email' => 'seventy-two-bytes@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertCreated();

        $user = User::query()->where('email', 'seventy-two-bytes@example.test')->sole();

        self::assertTrue(Hash::check($password, $user->password));

        Auth::guard('web')->logout();
        $this->app['session']->invalidate();
        Auth::forgetGuards();

        $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/v1/auth/login', [
                'email' => 'seventy-two-bytes@example.test',
                'password' => $password,
            ])
            ->assertOk();
    }

    public function test_a_password_confirmation_mismatch_is_rejected(): void
    {
        $this->register([
            'password_confirmation' => 'a-different-confirmation',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_an_email_unique_race_uses_the_normal_email_validation_contract(): void
    {
        $store = Mockery::mock(UserRegistrationStore::class);
        $store->shouldReceive('create')
            ->once()
            ->andThrow(new EmailAlreadyRegistered);

        $this->app->instance(UserRegistrationStore::class, $store);

        $this->register([
            'email' => 'race@example.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    public function test_the_store_maps_only_the_email_unique_constraint(): void
    {
        User::factory()->create([
            'email' => 'race@example.test',
        ]);

        $store = $this->app->make(UserRegistrationStore::class);

        try {
            DB::transaction(fn () => $store->create(
                'Race User',
                'race@example.test',
                'carematch-registration-password',
            ));
            self::fail('The duplicate email should have been rejected.');
        } catch (EmailAlreadyRegistered $exception) {
            self::assertInstanceOf(
                UniqueConstraintViolationException::class,
                $exception->getPrevious(),
            );
            self::assertSame('users_email_unique', $exception->getPrevious()->index);
        }
    }

    public function test_the_store_does_not_map_an_unrelated_unique_constraint(): void
    {
        $existingUser = User::factory()->create();
        $store = $this->app->make(UserRegistrationStore::class);

        DB::statement("SELECT setval('users_id_seq', ?, false)", [$existingUser->getKey()]);

        try {
            DB::transaction(fn () => $store->create(
                'Primary Key Race',
                'primary-key-race@example.test',
                'carematch-registration-password',
            ));
            self::fail('The primary-key collision should have been rejected.');
        } catch (EmailAlreadyRegistered) {
            self::fail('A primary-key violation must not be mapped to an email conflict.');
        } catch (UniqueConstraintViolationException $exception) {
            self::assertSame('users_pkey', $exception->index);
        } finally {
            DB::statement(
                "SELECT setval('users_id_seq', (SELECT COALESCE(MAX(id), 0) + 1 FROM users), false)",
            );
        }
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/v1/auth/register', array_merge([
                'name' => 'Registration Test User',
                'email' => 'registration@example.test',
                'password' => 'carematch-registration-password',
                'password_confirmation' => 'carematch-registration-password',
            ], $overrides));
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
