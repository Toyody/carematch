<?php

namespace Database\Factories;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    public const DEFAULT_PASSWORD = 'carematch-test-password';

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    private static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Test User',
            'email' => sprintf('user-%s@example.test', Str::uuid()),
            'email_verified_at' => null,
            'password' => self::$password ??= Hash::make(self::DEFAULT_PASSWORD),
        ];
    }
}
