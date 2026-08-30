<?php

namespace App\Modules\Identity\Interfaces\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class MaximumPasswordBytes implements ValidationRule
{
    public const MAXIMUM_BYTES = 72;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) <= self::MAXIMUM_BYTES) {
            return;
        }

        $fail('The :attribute must not exceed :maximum bytes.')->translate([
            'maximum' => self::MAXIMUM_BYTES,
        ]);
    }
}
