<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Interfaces\Validation\Rules\BcryptCompatiblePassword;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function test_the_shared_password_policy_requires_at_least_twelve_characters(): void
    {
        self::assertTrue($this->validateWithDefaults(str_repeat('a', 12)));
        self::assertFalse($this->validateWithDefaults(str_repeat('a', 11)));
    }

    public function test_the_byte_limit_accepts_seventy_two_ascii_bytes_and_rejects_more(): void
    {
        self::assertTrue($this->validateMaximumBytes(str_repeat('a', 72)));
        self::assertFalse($this->validateMaximumBytes(str_repeat('a', 73)));
    }

    public function test_the_byte_limit_uses_bytes_instead_of_multibyte_character_count(): void
    {
        $seventyTwoBytes = str_repeat('あ', 24);
        $seventyFiveBytes = str_repeat('あ', 25);

        self::assertSame(72, strlen($seventyTwoBytes));
        self::assertSame(24, mb_strlen($seventyTwoBytes));
        self::assertTrue($this->validateMaximumBytes($seventyTwoBytes));

        self::assertSame(75, strlen($seventyFiveBytes));
        self::assertSame(25, mb_strlen($seventyFiveBytes));
        self::assertFalse($this->validateMaximumBytes($seventyFiveBytes));
    }

    public function test_the_shared_policy_rejects_a_nul_byte(): void
    {
        self::assertFalse($this->validateWithDefaults(str_repeat('a', 12)."\0"));
    }

    private function validateWithDefaults(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [Password::defaults()]],
        )->passes();
    }

    private function validateMaximumBytes(string $password): bool
    {
        return Validator::make(
            ['password' => $password],
            ['password' => [new BcryptCompatiblePassword]],
        )->passes();
    }
}
