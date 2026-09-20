<?php

namespace App\Modules\Identity\Application\Support;

use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;

final class EmailNormalizer implements CanonicalEmailNormalizer
{
    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
