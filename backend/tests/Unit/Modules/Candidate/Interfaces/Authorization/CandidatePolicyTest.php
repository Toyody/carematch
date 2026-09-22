<?php

namespace Tests\Unit\Modules\Candidate\Interfaces\Authorization;

use App\Modules\Candidate\Interfaces\Authorization\CandidatePolicy;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CandidatePolicyTest extends TestCase
{
    #[DataProvider('roles')]
    public function test_candidate_permission_matrix(
        OrganisationRole $role,
        bool $mayWrite,
    ): void {
        $user = new User;
        $user->setAttribute('id', 10);
        $tenant = new TenantContext(20, 10, 30, $role);
        $policy = new CandidatePolicy;

        self::assertTrue($policy->view($user, $tenant));
        self::assertSame($mayWrite, $policy->create($user, $tenant));
        self::assertSame($mayWrite, $policy->update($user, $tenant));
        self::assertTrue($policy->viewDocuments($user, $tenant));
        self::assertSame($mayWrite, $policy->manageDocuments($user, $tenant));
    }

    public function test_a_context_for_another_user_is_never_authorised(): void
    {
        $user = new User;
        $user->setAttribute('id', 11);
        $tenant = new TenantContext(20, 10, 30, OrganisationRole::Admin);
        $policy = new CandidatePolicy;

        self::assertFalse($policy->view($user, $tenant));
        self::assertFalse($policy->create($user, $tenant));
        self::assertFalse($policy->update($user, $tenant));
        self::assertFalse($policy->viewDocuments($user, $tenant));
        self::assertFalse($policy->manageDocuments($user, $tenant));
    }

    /**
     * @return array<string, array{OrganisationRole, bool}>
     */
    public static function roles(): array
    {
        return [
            'admin' => [OrganisationRole::Admin, true],
            'recruiter' => [OrganisationRole::Recruiter, true],
            'hiring manager' => [OrganisationRole::HiringManager, false],
        ];
    }
}
