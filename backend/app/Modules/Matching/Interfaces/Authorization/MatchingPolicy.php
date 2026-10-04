<?php

namespace App\Modules\Matching\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class MatchingPolicy
{
    public const string VIEW = 'candidate-matches.view';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }
}
