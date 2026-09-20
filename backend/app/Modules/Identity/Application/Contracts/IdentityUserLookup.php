<?php

namespace App\Modules\Identity\Application\Contracts;

use App\Modules\Identity\Application\Data\IdentityUser;

interface IdentityUserLookup
{
    public function findByCanonicalEmail(string $email): ?IdentityUser;

    public function findById(int $userId): ?IdentityUser;

    /**
     * @param  list<int>  $userIds
     * @return array<int, IdentityUser>
     */
    public function findByIds(array $userIds): array;
}
