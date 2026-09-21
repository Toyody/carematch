<?php

namespace Tests\Unit\Modules\Recruitment\Interfaces\Authorization;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Interfaces\Authorization\ApplicationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApplicationPolicyTest extends TestCase
{
    #[DataProvider('roles')]
    public function test_phase_4a_permission_matrix(OrganisationRole $role, bool $mayCreate): void
    {
        $user = new User;
        $user->setAttribute('id', 7);
        $tenant = new TenantContext(11, 7, 13, $role);
        $policy = new ApplicationPolicy;

        self::assertTrue($policy->view($user, $tenant));
        self::assertSame($mayCreate, $policy->create($user, $tenant));
    }

    public function test_context_for_another_user_is_never_authorised(): void
    {
        $user = new User;
        $user->setAttribute('id', 8);
        $tenant = new TenantContext(11, 7, 13, OrganisationRole::Admin);
        $policy = new ApplicationPolicy;

        self::assertFalse($policy->view($user, $tenant));
        self::assertFalse($policy->create($user, $tenant));
    }

    /** @return array<string, array{OrganisationRole, bool}> */
    public static function roles(): array
    {
        return [
            'admin' => [OrganisationRole::Admin, true],
            'recruiter' => [OrganisationRole::Recruiter, true],
            'hiring manager' => [OrganisationRole::HiringManager, false],
        ];
    }
}
