<?php

namespace App\Modules\Identity\Application\Support;

final class EmailNormalizer
{
    public function normalize(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }
}
