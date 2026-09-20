<?php

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Application\Contracts\IdentityUserLookup;
use App\Modules\Identity\Application\Data\IdentityUser;

final class EloquentIdentityUserLookup implements IdentityUserLookup
{
    public function findByCanonicalEmail(string $email): ?IdentityUser
    {
        $user = User::query()->where('email', $email)->first(['id', 'name', 'email']);

        return $user === null ? null : $this->toIdentityUser($user);
    }

    public function findById(int $userId): ?IdentityUser
    {
        $user = User::query()->find($userId, ['id', 'name', 'email']);

        return $user === null ? null : $this->toIdentityUser($user);
    }

    public function findByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return User::query()
            ->whereKey($userIds)
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $user): array => [
                (int) $user->getKey() => $this->toIdentityUser($user),
            ])
            ->all();
    }

    private function toIdentityUser(User $user): IdentityUser
    {
        return new IdentityUser(
            id: (int) $user->getKey(),
            name: (string) $user->getAttribute('name'),
            email: (string) $user->getAttribute('email'),
        );
    }
}
