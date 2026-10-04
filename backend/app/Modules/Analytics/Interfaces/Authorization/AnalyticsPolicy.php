<?php

namespace App\Modules\Analytics\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class AnalyticsPolicy
{
    public const string VIEW = 'analytics.view';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }
}
