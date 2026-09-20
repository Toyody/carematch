<?php

namespace Tests\Feature\Organisation;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Actions\CreateOrganisation;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class OrganisationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unauthenticated_user_cannot_create_or_list_organisations(): void
    {
        $this->postJson('/api/v1/organisations', [
            'name' => 'Unauthorised Organisation',
        ])->assertUnauthorized();

        $this->getJson('/api/v1/organisations')->assertUnauthorized();

        self::assertSame(0, Organisation::query()->count());
    }

    public function test_an_authenticated_user_creates_an_organisation_with_an_active_admin_membership(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user, 'web')
            ->postJson('/api/v1/organisations', [
                'name' => 'Northside Health',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Northside Health')
            ->assertJsonPath('data.membership.role', 'admin')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'membership' => ['role'], 'created_at'],
            ]);

        $organisation = Organisation::query()->where('name', 'Northside Health')->sole();
        $membership = OrganisationMembership::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $user->getKey())
            ->sole();

        self::assertSame('admin', $membership->getAttribute('role'));
        self::assertNull($membership->getAttribute('deactivated_at'));
        self::assertSame(1, Organisation::query()->count());
        self::assertSame(1, OrganisationMembership::query()->count());
    }

    public function test_client_controlled_ownership_and_role_fields_are_rejected(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this
            ->actingAs($user, 'web')
            ->postJson('/api/v1/organisations', [
                'name' => 'Untrusted Ownership',
                'user_id' => $otherUser->getKey(),
                'organisation_id' => 999,
                'role' => 'recruiter',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'organisation_id', 'role']);

        self::assertSame(0, Organisation::query()->count());
    }

    public function test_an_invalid_organisation_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user, 'web')
            ->postJson('/api/v1/organisations', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        self::assertSame(0, Organisation::query()->count());
    }

    public function test_organisation_and_initial_membership_are_atomic(): void
    {
        $missingUserId = 999_999;
        $createOrganisation = $this->app->make(CreateOrganisation::class);

        try {
            $createOrganisation->handle('Rolled Back Organisation', $missingUserId);
            self::fail('The missing creator should violate the membership foreign key.');
        } catch (QueryException) {
            self::assertSame(0, Organisation::query()->count());
            self::assertSame(0, OrganisationMembership::query()->count());
        }
    }

    public function test_a_user_lists_only_organisations_with_active_memberships(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $createOrganisation = $this->app->make(CreateOrganisation::class);

        $active = $createOrganisation->handle('Active Organisation', (int) $user->getKey());
        $deactivated = $createOrganisation->handle('Deactivated Organisation', (int) $user->getKey());
        $createOrganisation->handle('Other User Organisation', (int) $otherUser->getKey());
        Organisation::query()->create(['name' => 'No Membership Organisation']);

        OrganisationMembership::query()
            ->where('organisation_id', $deactivated->id)
            ->where('user_id', $user->getKey())
            ->update(['deactivated_at' => now()]);

        $response = $this
            ->actingAs($user, 'web')
            ->getJson('/api/v1/organisations');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.name', 'Active Organisation')
            ->assertJsonPath('data.0.membership.role', 'admin');

        $data = $response->json('data.0');
        self::assertIsArray($data);
        $membership = $data['membership'] ?? null;
        self::assertIsArray($membership);
        self::assertSame(
            ['created_at', 'id', 'membership', 'name'],
            $this->sortedKeys($data),
        );
        self::assertSame(['role'], $this->sortedKeys($membership));
    }

    public function test_one_user_may_belong_to_multiple_organisations(): void
    {
        $user = User::factory()->create();
        $createOrganisation = $this->app->make(CreateOrganisation::class);

        $createOrganisation->handle('First Organisation', (int) $user->getKey());
        $createOrganisation->handle('Second Organisation', (int) $user->getKey());

        $this
            ->actingAs($user, 'web')
            ->getJson('/api/v1/organisations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'First Organisation')
            ->assertJsonPath('data.1.name', 'Second Organisation');

        self::assertSame(2, OrganisationMembership::query()->count());
    }

    public function test_the_database_prevents_duplicate_membership_for_an_organisation_and_user(): void
    {
        $user = User::factory()->create();
        $created = $this->app->make(CreateOrganisation::class)
            ->handle('Unique Membership', (int) $user->getKey());

        $this->expectException(QueryException::class);

        DB::table('organisation_memberships')->insert([
            'organisation_id' => $created->id,
            'user_id' => $user->getKey(),
            'role' => 'recruiter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_database_rejects_unsupported_roles(): void
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Role Constraint']);

        $this->expectException(QueryException::class);

        DB::table('organisation_memberships')->insert([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
