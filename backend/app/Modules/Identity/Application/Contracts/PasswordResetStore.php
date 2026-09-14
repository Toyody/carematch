<?php

namespace App\Modules\Identity\Application\Contracts;

interface PasswordResetStore
{
    public function reset(int $userId, string $password): void;
}
