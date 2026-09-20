<?php

namespace Tests\Unit\Modules\Organisation\Interfaces\Authorization;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Interfaces\Authorization\OrganisationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrganisationPolicyTest extends TestCase
{
    #[DataProvider('roleMatrix')]
    public function test_the_fixed_role_matrix(
        OrganisationRole $role,
        bool $mayUpdate,
    ): void {
        $user = new User;
        $user->setAttribute('id', 10);
        $tenant = new TenantContext(
            organisationId: 20,
            userId: 10,
            membershipId: 30,
            role: $role,
        );
        $policy = new OrganisationPolicy;

        self::assertTrue($policy->view($user, $tenant));
        self::assertSame($mayUpdate, $policy->update($user, $tenant));
    }

    public function test_a_context_for_another_user_is_never_authorised(): void
    {
        $user = new User;
        $user->setAttribute('id', 10);
        $tenant = new TenantContext(
            organisationId: 20,
            userId: 11,
            membershipId: 30,
            role: OrganisationRole::Admin,
        );
        $policy = new OrganisationPolicy;

        self::assertFalse($policy->view($user, $tenant));
        self::assertFalse($policy->update($user, $tenant));
    }

    /**
     * @return array<string, array{OrganisationRole, bool}>
     */
    public static function roleMatrix(): array
    {
        return [
            'admin' => [OrganisationRole::Admin, true],
            'recruiter' => [OrganisationRole::Recruiter, false],
            'hiring manager' => [OrganisationRole::HiringManager, false],
        ];
    }
}
