<?php

namespace App\Modules\Candidate\Interfaces\Authorization;

use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;

final class CandidatePolicy
{
    public const string CREATE = 'candidates.create';

    public const string UPDATE = 'candidates.update';

    public const string VIEW = 'candidates.view';

    public const string VIEW_DOCUMENTS = 'candidate-documents.view';

    public const string MANAGE_DOCUMENTS = 'candidate-documents.manage';

    public function view(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant);
    }

    public function create(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->mayWrite($user, $tenant);
    }

    public function update(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->mayWrite($user, $tenant);
    }

    public function viewDocuments(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->view($user, $tenant);
    }

    public function manageDocuments(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->mayWrite($user, $tenant);
    }

    private function mayWrite(Authenticatable $user, TenantContext $tenant): bool
    {
        return $this->matchesAuthenticatedUser($user, $tenant)
            && in_array($tenant->role, [
                OrganisationRole::Admin,
                OrganisationRole::Recruiter,
            ], true);
    }

    private function matchesAuthenticatedUser(Authenticatable $user, TenantContext $tenant): bool
    {
        return $user->getAuthIdentifier() === $tenant->userId;
    }
}
