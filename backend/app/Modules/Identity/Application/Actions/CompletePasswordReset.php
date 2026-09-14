<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Contracts\PasswordResetStore;

final readonly class CompletePasswordReset
{
    public function __construct(
        private PasswordResetStore $passwords,
    ) {}

    public function handle(int $userId, string $password): void
    {
        $this->passwords->reset($userId, $password);
    }
}
