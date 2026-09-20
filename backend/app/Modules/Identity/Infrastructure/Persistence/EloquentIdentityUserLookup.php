<?php

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Identity\Application\Data\IdentityUser;

final class EloquentIdentityUserLookup implements IdentityUserLookup
{
    public function findByCanonicalEmail(string $email): ?IdentityUser
    {
        $user = User::query()->where('email', $email)->first(['id', 'email']);

        return $user === null ? null : $this->toIdentityUser($user);
    }

    public function findById(int $userId): ?IdentityUser
    {
        $user = User::query()->find($userId, ['id', 'email']);

        return $user === null ? null : $this->toIdentityUser($user);
    }

    private function toIdentityUser(User $user): IdentityUser
    {
        return new IdentityUser(
            id: (int) $user->getKey(),
            email: (string) $user->getAttribute('email'),
        );
    }
}
