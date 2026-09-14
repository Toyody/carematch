<?php

namespace App\Modules\Identity\Interfaces\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class BcryptCompatiblePassword implements ValidationRule
{
    public const MAXIMUM_BYTES = 72;

    public static function accepts(string $password): bool
    {
        return ! str_contains($password, "\0")
            && strlen($password) <= self::MAXIMUM_BYTES;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (str_contains($value, "\0")) {
            $fail('The :attribute contains a character unsupported by the configured password hasher.');

            return;
        }

        if (strlen($value) > self::MAXIMUM_BYTES) {
            $fail('The :attribute must not exceed :maximum bytes.')->translate([
                'maximum' => self::MAXIMUM_BYTES,
            ]);
        }
    }
}
