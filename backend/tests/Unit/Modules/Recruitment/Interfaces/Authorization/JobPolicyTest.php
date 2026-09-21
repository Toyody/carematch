<?php

namespace Tests\Unit\Modules\Recruitment\Interfaces\Authorization;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Recruitment\Interfaces\Authorization\JobPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobPolicyTest extends TestCase
{
    #[DataProvider('roles')]
    public function test_job_permission_matrix(OrganisationRole $role, bool $mayWrite): void
    {
        $user = new User;
        $user->setAttribute('id', 10);
        $tenant = new TenantContext(20, 10, 30, $role);
        $policy = new JobPolicy;

        self::assertTrue($policy->view($user, $tenant));
        self::assertSame($mayWrite, $policy->write($user, $tenant));
    }

    public function test_a_context_for_another_user_is_never_authorised(): void
    {
        $user = new User;
        $user->setAttribute('id', 11);
        $tenant = new TenantContext(20, 10, 30, OrganisationRole::Admin);
        $policy = new JobPolicy;

        self::assertFalse($policy->view($user, $tenant));
        self::assertFalse($policy->write($user, $tenant));
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
