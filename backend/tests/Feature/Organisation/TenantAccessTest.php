<?php

namespace Tests\Feature\Organisation;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_detail_and_update_requests_return_unauthorized(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected Organisation']);

        $this->getJson("/api/v1/organisations/{$organisation->getKey()}")
            ->assertUnauthorized();

        $this->patchJson("/api/v1/organisations/{$organisation->getKey()}", [
            'name' => 'Unauthorised Update',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->getKey(),
            'name' => 'Protected Organisation',
        ]);
    }

    #[DataProvider('roles')]
    public function test_each_active_membership_role_can_view_its_organisation(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, $role);

        $response = $this
            ->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $organisation->getKey())
            ->assertJsonPath('data.name', $organisation->getAttribute('name'))
            ->assertJsonPath('data.membership.role', $role);

        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertSame(
            ['created_at', 'id', 'membership', 'name'],
            $this->sortedKeys($data),
        );
    }

    public function test_inaccessible_and_nonexistent_organisations_share_the_same_not_found_response(): void
    {
        config()->set('app.debug', false);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $withoutMembership = Organisation::query()->create(['name' => 'No Membership']);
        [$otherOrganisation] = $this->createOrganisationMembership($otherUser, 'admin');
        [$deactivatedOrganisation] = $this->createOrganisationMembership(
            $user,
            'admin',
            deactivated: true,
        );

        $paths = [
            "/api/v1/organisations/{$withoutMembership->getKey()}",
            "/api/v1/organisations/{$otherOrganisation->getKey()}",
            "/api/v1/organisations/{$deactivatedOrganisation->getKey()}",
            '/api/v1/organisations/999999',
        ];

        $expectedContent = null;

        foreach ($paths as $path) {
            $detailResponse = $this->actingAs($user, 'web')->getJson($path);
            $detailResponse
                ->assertNotFound()
                ->assertExactJson(['message' => 'Not Found']);
            $expectedContent ??= $detailResponse->getContent();
            self::assertSame($expectedContent, $detailResponse->getContent());

            $updateResponse = $this->actingAs($user, 'web')->patchJson($path, [
                'name' => 'Inaccessible Update',
            ]);
            $updateResponse
                ->assertNotFound()
                ->assertExactJson(['message' => 'Not Found']);
            self::assertSame($expectedContent, $updateResponse->getContent());
        }
    }

    public function test_one_user_can_resolve_each_active_tenant_independently(): void
    {
        $user = User::factory()->create();
        [$first] = $this->createOrganisationMembership($user, 'recruiter', 'First Tenant');
        [$second] = $this->createOrganisationMembership($user, 'hiring_manager', 'Second Tenant');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$first->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.membership.role', 'recruiter');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$second->getKey()}")
            ->assertOk()
            ->assertJsonPath('data.membership.role', 'hiring_manager');
    }

    public function test_an_admin_can_update_the_organisation_name(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, 'admin');

        $this->actingAs($user, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}", [
                'name' => 'Updated Organisation',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $organisation->getKey())
            ->assertJsonPath('data.name', 'Updated Organisation')
            ->assertJsonPath('data.membership.role', 'admin');

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->getKey(),
            'name' => 'Updated Organisation',
        ]);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_members_cannot_update_organisation_settings(string $role): void
    {
        config()->set('app.debug', false);

        $user = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, $role);

        $this->actingAs($user, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}", [
                'name' => 'Forbidden Update',
                'role' => 'admin',
            ])
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->getKey(),
            'name' => 'Test Organisation',
        ]);
    }

    public function test_security_sensitive_body_fields_are_rejected_for_an_authorised_tenant(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, 'admin', 'Trusted Tenant');
        [$otherOrganisation, $otherMembership] = $this->createOrganisationMembership(
            $otherUser,
            'admin',
            'Other Tenant',
        );

        $this->actingAs($user, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}", [
                'name' => 'Attempted Switch',
                'organisation_id' => $otherOrganisation->getKey(),
                'user_id' => $otherUser->getKey(),
                'role' => 'admin',
                'membership_id' => $otherMembership->getKey(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'organisation_id',
                'user_id',
                'role',
                'membership_id',
            ]);

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->getKey(),
            'name' => 'Trusted Tenant',
        ]);
        $this->assertDatabaseHas('organisations', [
            'id' => $otherOrganisation->getKey(),
            'name' => 'Other Tenant',
        ]);
    }

    public function test_body_identifiers_cannot_bypass_cross_tenant_route_access(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        [$ownOrganisation, $ownMembership] = $this->createOrganisationMembership(
            $user,
            'admin',
            'Own Tenant',
        );
        [$otherOrganisation] = $this->createOrganisationMembership(
            $otherUser,
            'admin',
            'Other Tenant',
        );

        $this->actingAs($user, 'web')
            ->patchJson("/api/v1/organisations/{$otherOrganisation->getKey()}", [
                'name' => 'Cross Tenant Update',
                'organisation_id' => $ownOrganisation->getKey(),
                'user_id' => $user->getKey(),
                'role' => 'admin',
                'membership_id' => $ownMembership->getKey(),
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('organisations', [
            'id' => $otherOrganisation->getKey(),
            'name' => 'Other Tenant',
        ]);
    }

    public function test_invalid_name_is_rejected_without_updating_the_organisation(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, 'admin');

        $this->actingAs($user, 'web')
            ->patchJson("/api/v1/organisations/{$organisation->getKey()}", [
                'name' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->getKey(),
            'name' => 'Test Organisation',
        ]);
    }

    /**
     * @return array{Organisation, OrganisationMembership}
     */
    private function createOrganisationMembership(
        User $user,
        string $role,
        string $name = 'Test Organisation',
        bool $deactivated = false,
    ): array {
        $organisation = Organisation::query()->create(['name' => $name]);
        $membership = OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'deactivated_at' => $deactivated ? now() : null,
        ]);

        return [$organisation, $membership];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roles(): array
    {
        return [
            'admin' => ['admin'],
            'recruiter' => ['recruiter'],
            'hiring manager' => ['hiring_manager'],
        ];
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
     * @param  array<string, mixed>  $values
     * @return list<string>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
