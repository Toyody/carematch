<?php

namespace App\Modules\Identity\Application\Contracts;

interface CanonicalEmailNormalizer
{
    public function normalize(string $email): string;
}
