<?php

namespace Tests\Feature\Organisation;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Actions\ChangeOrganisationMembershipRole;
use App\Modules\Organisation\Application\Actions\ListOrganisationMemberships;
use App\Modules\Organisation\Application\Data\OrganisationRole;
use App\Modules\Organisation\Application\Data\TenantContext;
use App\Modules\Organisation\Application\Exceptions\MembershipManagementForbidden;
use App\Modules\Organisation\Application\Exceptions\TenantMembershipUnavailable;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OrganisationMembershipManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_manage_memberships(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);

        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/memberships")
            ->assertUnauthorized();
        $this->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/1", [
            'role' => 'admin',
        ])->assertUnauthorized();
        $this->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/1/deactivate")
            ->assertUnauthorized();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_members_cannot_manage_memberships(string $role): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($actor, $role);
        [, $targetMembership] = $this->createOrganisationMembership(
            $target,
            'recruiter',
            organisation: $organisation,
        );

        $this->actingAs($actor, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/memberships")
            ->assertForbidden();
        $this->actingAs($actor, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$targetMembership->getKey()}", [
                'role' => 'owner',
                'user_id' => $actor->getKey(),
            ])->assertForbidden();
        $this->actingAs($actor, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$targetMembership->getKey()}/deactivate")
            ->assertForbidden();

        $targetMembership->refresh();
        self::assertSame('recruiter', $targetMembership->getAttribute('role'));
        self::assertNull($targetMembership->getAttribute('deactivated_at'));
    }

    public function test_admin_lists_active_and_deactivated_members_with_one_batched_identity_query(): void
    {
        $admin = User::factory()->create(['name' => 'Admin User', 'email' => 'admin@example.test']);
        $active = User::factory()->create(['name' => 'Active User', 'email' => 'active@example.test']);
        $inactive = User::factory()->create(['name' => 'Inactive User', 'email' => 'inactive@example.test']);
        $other = User::factory()->create(['name' => 'Other User', 'email' => 'other@example.test']);
        [$organisation, $adminMembership] = $this->createOrganisationMembership($admin, 'admin');
        [, $activeMembership] = $this->createOrganisationMembership($active, 'recruiter', organisation: $organisation);
        [, $inactiveMembership] = $this->createOrganisationMembership(
            $inactive,
            'hiring_manager',
            deactivated: true,
            organisation: $organisation,
        );
        [$otherOrganisation] = $this->createOrganisationMembership($other, 'admin');
        $this->createOrganisationMembership($active, 'recruiter', organisation: $otherOrganisation);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($admin, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/memberships");
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $adminMembership->getKey())
            ->assertJsonPath('data.1.id', $activeMembership->getKey())
            ->assertJsonPath('data.1.user.name', 'Active User')
            ->assertJsonPath('data.1.user.email', 'active@example.test')
            ->assertJsonPath('data.1.role', 'recruiter')
            ->assertJsonPath('data.2.id', $inactiveMembership->getKey())
            ->assertJsonPath('data.2.role', 'hiring_manager')
            ->assertJsonPath('data.2.deactivated_at', fn (mixed $value): bool => is_string($value));

        $body = $response->getContent();
        self::assertStringNotContainsString('password', $body);
        self::assertStringNotContainsString('remember_token', $body);
        self::assertStringNotContainsString('carematch_session', $body);
        self::assertStringNotContainsString('other@example.test', $body);

        $identityQueries = array_filter(
            $queries,
            static fn (array $query): bool => str_contains($query['query'], 'from "users"'),
        );
        self::assertCount(1, $identityQueries);
    }

    public function test_inaccessible_tenants_have_consistent_not_found_membership_semantics(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$otherOrganisation, $otherMembership] = $this->createOrganisationMembership($otherAdmin, 'admin');
        [$inactiveOrganisation] = $this->createOrganisationMembership($user, 'admin', deactivated: true);

        foreach ([$otherOrganisation->getKey(), $inactiveOrganisation->getKey(), 999999] as $organisationId) {
            $this->actingAs($user, 'web')
                ->getJson("/api/v1/organisations/{$organisationId}/memberships")
                ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
            $this->actingAs($user, 'web')
                ->patchJson("/api/v1/organisations/{$organisationId}/memberships/{$otherMembership->getKey()}", [
                    'role' => 'recruiter',
                ])->assertNotFound()->assertExactJson(['message' => 'Not Found']);
            $this->actingAs($user, 'web')
                ->postJson("/api/v1/organisations/{$organisationId}/memberships/{$otherMembership->getKey()}/deactivate")
                ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        }
    }

    public function test_admin_changes_roles_and_same_role_update_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create(['name' => 'Member', 'email' => 'member@example.test']);
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [, $membership] = $this->createOrganisationMembership($member, 'recruiter', organisation: $organisation);
        $path = "/api/v1/organisations/{$organisation->getKey()}/memberships/{$membership->getKey()}";

        $this->actingAs($admin, 'web')->patchJson($path, ['role' => 'hiring_manager'])
            ->assertOk()
            ->assertJsonPath('data.id', $membership->getKey())
            ->assertJsonPath('data.user.id', $member->getKey())
            ->assertJsonPath('data.user.name', 'Member')
            ->assertJsonPath('data.user.email', 'member@example.test')
            ->assertJsonPath('data.role', 'hiring_manager')
            ->assertJsonPath('data.deactivated_at', null);

        $this->actingAs($admin, 'web')->patchJson($path, ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin');
        $updatedAt = $membership->fresh()?->getAttribute('updated_at');

        $this->actingAs($admin, 'web')->patchJson($path, ['role' => 'admin'])
            ->assertOk()->assertJsonPath('data.role', 'admin');
        self::assertEquals($updatedAt, $membership->fresh()?->getAttribute('updated_at'));
    }

    public function test_admin_may_demote_another_admin_or_themselves_when_another_admin_remains(): void
    {
        $firstAdmin = User::factory()->create();
        $secondAdmin = User::factory()->create();
        [$organisation, $firstMembership] = $this->createOrganisationMembership($firstAdmin, 'admin');
        [, $secondMembership] = $this->createOrganisationMembership($secondAdmin, 'admin', organisation: $organisation);

        $this->actingAs($firstAdmin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$secondMembership->getKey()}", [
                'role' => 'recruiter',
            ])->assertOk();

        $secondMembership->refresh()->forceFill(['role' => 'admin'])->save();

        $this->actingAs($firstAdmin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$firstMembership->getKey()}", [
                'role' => 'hiring_manager',
            ])->assertOk();

        self::assertSame('hiring_manager', $firstMembership->fresh()?->getAttribute('role'));
        self::assertSame('admin', $secondMembership->fresh()?->getAttribute('role'));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_final_active_admin_cannot_be_demoted(string $role): void
    {
        $admin = User::factory()->create();
        [$organisation, $membership] = $this->createOrganisationMembership($admin, 'admin');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$membership->getKey()}", [
                'role' => $role,
            ])->assertConflict()->assertExactJson([
                'message' => 'An organisation must retain at least one active Admin.',
            ]);

        self::assertSame('admin', $membership->fresh()?->getAttribute('role'));
        self::assertNull($membership->fresh()?->getAttribute('deactivated_at'));
    }

    public function test_role_update_rejects_cross_tenant_deactivated_invalid_and_client_controlled_targets(): void
    {
        $admin = User::factory()->create();
        $inactiveUser = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [, $inactiveMembership] = $this->createOrganisationMembership(
            $inactiveUser,
            'recruiter',
            deactivated: true,
            organisation: $organisation,
        );
        [$otherOrganisation, $otherMembership] = $this->createOrganisationMembership($otherAdmin, 'admin');

        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$otherMembership->getKey()}", [
                'role' => 'recruiter',
            ])->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/999999", [
                'role' => 'recruiter',
            ])->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$inactiveMembership->getKey()}", [
                'role' => 'admin',
            ])->assertConflict()->assertExactJson([
                'message' => 'A deactivated membership role cannot be changed.',
            ]);
        $this->actingAs($admin, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$inactiveMembership->getKey()}", [
                'role' => 'owner',
                'organisation_id' => $otherOrganisation->getKey(),
                'user_id' => $otherAdmin->getKey(),
                'membership_id' => $otherMembership->getKey(),
                'deactivated_at' => now()->toAtomString(),
                'actor_user_id' => $otherAdmin->getKey(),
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'role', 'organisation_id', 'user_id', 'membership_id', 'deactivated_at', 'actor_user_id',
            ]);

        self::assertSame('recruiter', $inactiveMembership->fresh()?->getAttribute('role'));
        self::assertNotNull($inactiveMembership->fresh()?->getAttribute('deactivated_at'));
        self::assertSame('admin', $otherMembership->fresh()?->getAttribute('role'));
    }

    public function test_admin_deactivates_members_without_deleting_and_repeat_is_idempotent(): void
    {
        $admin = User::factory()->create();
        $recruiter = User::factory()->create();
        $manager = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [, $recruiterMembership] = $this->createOrganisationMembership($recruiter, 'recruiter', organisation: $organisation);
        [, $managerMembership] = $this->createOrganisationMembership($manager, 'hiring_manager', organisation: $organisation);

        foreach ([$recruiterMembership, $managerMembership] as $membership) {
            $path = "/api/v1/organisations/{$organisation->getKey()}/memberships/{$membership->getKey()}/deactivate";
            $this->actingAs($admin, 'web')->postJson($path)->assertNoContent();
            $firstDeactivatedAt = $membership->fresh()?->getAttribute('deactivated_at');
            self::assertNotNull($firstDeactivatedAt);
            $this->actingAs($admin, 'web')->postJson($path)->assertNoContent();
            self::assertEquals($firstDeactivatedAt, $membership->fresh()?->getAttribute('deactivated_at'));
            $this->assertDatabaseHas('organisation_memberships', ['id' => $membership->getKey()]);
        }
    }

    public function test_admin_may_deactivate_another_admin_or_themselves_when_another_admin_remains(): void
    {
        config()->set('app.debug', false);
        $firstAdmin = User::factory()->create();
        $secondAdmin = User::factory()->create();
        [$organisation, $firstMembership] = $this->createOrganisationMembership($firstAdmin, 'admin');
        [, $secondMembership] = $this->createOrganisationMembership($secondAdmin, 'admin', organisation: $organisation);

        $this->actingAs($firstAdmin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$secondMembership->getKey()}/deactivate")
            ->assertNoContent();
        self::assertNotNull($secondMembership->fresh()?->getAttribute('deactivated_at'));

        $secondMembership->refresh()->forceFill(['deactivated_at' => null])->save();

        $selfPath = "/api/v1/organisations/{$organisation->getKey()}/memberships/{$firstMembership->getKey()}/deactivate";
        $this->actingAs($firstAdmin, 'web')->postJson($selfPath)->assertNoContent();
        self::assertNotNull($firstMembership->fresh()?->getAttribute('deactivated_at'));
        self::assertNull($secondMembership->fresh()?->getAttribute('deactivated_at'));
        $this->actingAs($firstAdmin, 'web')
            ->postJson($selfPath)
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
    }

    public function test_final_active_admin_cannot_be_deactivated_and_cross_tenant_target_is_not_found(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$organisation, $membership] = $this->createOrganisationMembership($admin, 'admin');
        [$otherOrganisation, $otherMembership] = $this->createOrganisationMembership($otherAdmin, 'admin');

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$membership->getKey()}/deactivate")
            ->assertConflict()->assertExactJson([
                'message' => 'An organisation must retain at least one active Admin.',
            ]);
        self::assertNull($membership->fresh()?->getAttribute('deactivated_at'));

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$otherMembership->getKey()}/deactivate")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/999999/deactivate")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        self::assertNull($otherMembership->fresh()?->getAttribute('deactivated_at'));
        self::assertSame($otherOrganisation->getKey(), $otherMembership->getAttribute('organisation_id'));
    }

    public function test_client_fields_cannot_change_deactivation_target(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [, $membership] = $this->createOrganisationMembership($target, 'recruiter', organisation: $organisation);

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/memberships/{$membership->getKey()}/deactivate", [
                'organisation_id' => 999,
                'user_id' => $admin->getKey(),
                'membership_id' => 999,
                'role' => 'admin',
                'deactivated_at' => now()->toAtomString(),
                'actor_user_id' => $target->getKey(),
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'organisation_id', 'user_id', 'membership_id', 'role', 'deactivated_at', 'actor_user_id',
            ]);

        self::assertNull($membership->fresh()?->getAttribute('deactivated_at'));
    }

    public function test_application_rechecks_stale_actor_membership_instead_of_trusting_tenant_context(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        [$organisation, $adminMembership] = $this->createOrganisationMembership($admin, 'admin');
        [, $targetMembership] = $this->createOrganisationMembership($target, 'recruiter', organisation: $organisation);
        $tenant = new TenantContext(
            organisationId: (int) $organisation->getKey(),
            userId: (int) $admin->getKey(),
            membershipId: (int) $adminMembership->getKey(),
            role: OrganisationRole::Admin,
        );

        $adminMembership->forceFill(['deactivated_at' => now()])->save();

        try {
            $this->app->make(ChangeOrganisationMembershipRole::class)->handle(
                $tenant,
                (int) $targetMembership->getKey(),
                OrganisationRole::Admin,
            );
            self::fail('A deactivated actor must not use a stale tenant context.');
        } catch (TenantMembershipUnavailable) {
            self::assertSame('recruiter', $targetMembership->fresh()?->getAttribute('role'));
        }

        $adminMembership->forceFill(['deactivated_at' => null, 'role' => 'recruiter'])->save();

        $this->expectException(MembershipManagementForbidden::class);
        $this->app->make(ListOrganisationMemberships::class)->handle($tenant);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'recruiter' => ['recruiter'],
            'hiring manager' => ['hiring_manager'],
        ];
    }

    /**
     * @return array{Organisation, OrganisationMembership}
     */
    private function createOrganisationMembership(
        User $user,
        string $role,
        string $name = 'Test Organisation',
        bool $deactivated = false,
        ?Organisation $organisation = null,
    ): array {
        $organisation ??= Organisation::query()->create(['name' => $name]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'deactivated_at' => $deactivated ? now() : null,
        ]);

        return [$organisation, $membership];
    }
}
