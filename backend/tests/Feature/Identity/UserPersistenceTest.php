<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Factories\UserFactory;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UserPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_factory_creates_a_normalised_user_resolved_by_the_auth_provider(): void
    {
        $user = User::factory()->create();

        self::assertSame(trim(strtolower($user->email)), $user->email);
        self::assertTrue(Hash::check(UserFactory::DEFAULT_PASSWORD, $user->password));
        self::assertNotSame(UserFactory::DEFAULT_PASSWORD, $user->password);

        $provider = Auth::createUserProvider('users');

        self::assertInstanceOf(EloquentUserProvider::class, $provider);

        $retrievedUser = $provider->retrieveById($user->getAuthIdentifier());

        self::assertInstanceOf(User::class, $retrievedUser);
        self::assertTrue($retrievedUser->is($user));
    }

    public function test_sensitive_attributes_are_hidden_from_serialisation(): void
    {
        $user = User::factory()->create([
            'remember_token' => 'sensitive-remember-token',
        ]);

        $serialisedUser = $user->toArray();

        self::assertArrayNotHasKey('password', $serialisedUser);
        self::assertArrayNotHasKey('remember_token', $serialisedUser);
    }

    public function test_an_existing_password_hash_is_not_hashed_again(): void
    {
        $passwordHash = Hash::make('already-hashed-password');

        $user = User::factory()->create([
            'password' => $passwordHash,
        ]);

        self::assertSame($passwordHash, $user->password);
        self::assertTrue(Hash::check('already-hashed-password', $user->password));
    }
}
