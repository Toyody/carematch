<?php

namespace Tests\Feature\Candidate;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CandidateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_access_candidate_routes(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);
        $candidate = $this->createCandidate($organisation, 'Protected', 'Candidate');
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates";

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, $this->validPayload())->assertUnauthorized();
        $this->getJson("{$base}/{$candidate->getKey()}")->assertUnauthorized();
        $this->patchJson("{$base}/{$candidate->getKey()}", ['first_name' => 'Changed'])
            ->assertUnauthorized();
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_create_tenant_owned_candidates(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);

        $response = $this->actingAs($user, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/candidates", [
                ...$this->validPayload(),
                'email' => '  PERSON@EXAMPLE.TEST ',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.first_name', 'Aiko')
            ->assertJsonPath('data.email', 'person@example.test')
            ->assertJsonMissingPath('data.organisation_id');

        $candidateId = $response->json('data.id');
        self::assertIsInt($candidateId);
        $this->assertDatabaseHas('candidates', [
            'id' => $candidateId,
            'organisation_id' => $organisation->getKey(),
            'email' => 'person@example.test',
        ]);

        $data = $response->json('data');
        self::assertIsArray($data);
        self::assertSame([
            'availability',
            'created_at',
            'email',
            'first_name',
            'id',
            'last_name',
            'location',
            'notes',
            'occupation',
            'phone',
            'updated_at',
        ], $this->sortedKeys($data));
    }

    public function test_hiring_manager_is_forbidden_from_creation_and_deactivated_membership_is_not_found(): void
    {
        config()->set('app.debug', false);
        $manager = User::factory()->create();
        [$managerOrganisation] = $this->createMembership($manager, 'hiring_manager');

        $this->actingAs($manager, 'web')
            ->postJson(
                "/api/v1/organisations/{$managerOrganisation->getKey()}/candidates",
                $this->validPayload(),
            )
            ->assertForbidden()
            ->assertExactJson(['message' => 'This action is unauthorized.']);

        $deactivatedUser = User::factory()->create();
        [$deactivatedOrganisation] = $this->createMembership(
            $deactivatedUser,
            'admin',
            deactivated: true,
        );

        $this->actingAs($deactivatedUser, 'web')
            ->postJson(
                "/api/v1/organisations/{$deactivatedOrganisation->getKey()}/candidates",
                $this->validPayload(),
            )
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_stateful_candidate_writes_require_a_valid_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $candidate = $this->createCandidate($organisation, 'Existing', 'Candidate');
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates";

        $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost:3000')
            ->postJson($base, $this->validPayload())
            ->assertStatus(419)
            ->assertJsonPath('message', 'CSRF token mismatch.');

        $this->actingAs($user, 'web')
            ->withHeader('Origin', 'http://localhost:3000')
            ->patchJson("{$base}/{$candidate->getKey()}", ['first_name' => 'Changed'])
            ->assertStatus(419)
            ->assertJsonPath('message', 'CSRF token mismatch.');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->getKey(),
            'first_name' => 'Existing',
        ]);
    }

    public function test_create_validation_rejects_invalid_and_client_controlled_fields(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);

        $this->actingAs($user, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/candidates", [
                'first_name' => '   ',
                'last_name' => '',
                'email' => 'not-an-email',
                'organisation_id' => $other->getKey(),
                'id' => 100,
                'created_at' => now()->toAtomString(),
                'updated_at' => now()->toAtomString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'email',
                'organisation_id',
                'id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_optional_fields_are_nullable_and_duplicate_candidate_email_is_allowed(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'recruiter');
        $url = "/api/v1/organisations/{$organisation->getKey()}/candidates";

        $first = $this->actingAs($user, 'web')->postJson($url, [
            'first_name' => 'First',
            'last_name' => 'Candidate',
            'email' => 'shared@example.test',
        ]);
        $second = $this->actingAs($user, 'web')->postJson($url, [
            'first_name' => 'Second',
            'last_name' => 'Candidate',
            'email' => 'shared@example.test',
        ]);

        $first->assertCreated()->assertJsonPath('data.phone', null);
        $second->assertCreated();
        $this->assertDatabaseCount('candidates', 2);
    }

    #[DataProvider('allRoles')]
    public function test_all_active_roles_list_only_the_current_tenant_candidates(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role, 'Own');
        $other = Organisation::query()->create(['name' => 'Other']);
        $ownCandidate = $this->createCandidate($organisation, 'Own', 'Candidate');
        $this->createCandidate($other, 'Other', 'Candidate');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/candidates")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownCandidate->getKey())
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_an_empty_candidate_list_has_a_valid_paginated_shape(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/candidates")
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'links' => [
                    'first' => "http://localhost:8000/api/v1/organisations/{$organisation->getKey()}/candidates?page=1",
                    'last' => "http://localhost:8000/api/v1/organisations/{$organisation->getKey()}/candidates?page=1",
                    'prev' => null,
                    'next' => null,
                ],
                'meta' => [
                    'current_page' => 1,
                    'from' => null,
                    'last_page' => 1,
                    'path' => "http://localhost:8000/api/v1/organisations/{$organisation->getKey()}/candidates",
                    'per_page' => 20,
                    'to' => null,
                    'total' => 0,
                ],
            ]);
    }

    #[DataProvider('searchCases')]
    public function test_candidate_search_matches_expected_fields(string $search): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $match = $this->createCandidate(
            $organisation,
            'Akiko',
            'Tanaka',
            'akiko@example.test',
        );
        $this->createCandidate($organisation, 'Ben', 'Smith', 'ben@example.test');

        $this->actingAs($user, 'web')
            ->getJson(
                "/api/v1/organisations/{$organisation->getKey()}/candidates?search=".urlencode($search),
            )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->getKey());
    }

    public function test_search_treats_like_wildcards_as_literal_characters(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $literal = $this->createCandidate($organisation, 'Percent%', 'Candidate');
        $this->createCandidate($organisation, 'Ordinary', 'Candidate');

        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/candidates?search=%25")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $literal->getKey());
    }

    public function test_occupation_filter_and_name_sort_are_explicit_and_deterministic(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $zara = $this->createCandidate($organisation, 'Zara', 'Adams', occupation: 'Nurse');
        $amy = $this->createCandidate($organisation, 'Amy', 'Adams', occupation: 'Nurse');
        $this->createCandidate($organisation, 'Doctor', 'Only', occupation: 'Doctor');

        $response = $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/candidates?occupation=Nurse&sort=name&direction=asc",
        );

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $amy->getKey())
            ->assertJsonPath('data.1.id', $zara->getKey());

        $this->actingAs($user, 'web')->getJson(
            "/api/v1/organisations/{$organisation->getKey()}/candidates?occupation=Nurse&sort=name&direction=desc",
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $zara->getKey())
            ->assertJsonPath('data.1.id', $amy->getKey());
    }

    public function test_created_at_sort_and_pagination_preserve_deterministic_order(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');

        CarbonImmutable::setTestNow('2026-01-01 00:00:00');
        $first = $this->createCandidate($organisation, 'First', 'Candidate');
        $second = $this->createCandidate($organisation, 'Second', 'Candidate');
        CarbonImmutable::setTestNow('2026-01-02 00:00:00');
        $third = $this->createCandidate($organisation, 'Third', 'Candidate');
        CarbonImmutable::setTestNow();

        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates?sort=created_at&direction=asc&per_page=2";

        $this->actingAs($user, 'web')->getJson($base)
            ->assertOk()
            ->assertJsonPath('data.0.id', $first->getKey())
            ->assertJsonPath('data.1.id', $second->getKey())
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('links.next', "http://localhost:8000{$base}&page=2");

        $this->actingAs($user, 'web')->getJson("{$base}&page=2")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $third->getKey());
    }

    public function test_invalid_or_unknown_list_parameters_are_rejected(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates";

        $this->actingAs($user, 'web')
            ->getJson("{$base}?sort=password&direction=sideways&page=0&per_page=101&role=admin")
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sort',
                'direction',
                'page',
                'per_page',
                'query',
            ]);
    }

    #[DataProvider('allRoles')]
    public function test_all_active_roles_view_tenant_candidate_details(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);
        $candidate = $this->createCandidate($organisation, 'Viewed', 'Candidate');

        $this->actingAs($user, 'web')
            ->getJson(
                "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}",
            )
            ->assertOk()
            ->assertJsonPath('data.id', $candidate->getKey())
            ->assertJsonPath('data.first_name', 'Viewed');
    }

    public function test_missing_cross_tenant_and_deactivated_detail_requests_share_not_found_semantics(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $otherCandidate = $this->createCandidate($other, 'Other', 'Candidate');
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates";

        $crossTenant = $this->actingAs($user, 'web')
            ->getJson("{$base}/{$otherCandidate->getKey()}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
        $missing = $this->actingAs($user, 'web')
            ->getJson("{$base}/999999")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
        self::assertSame($crossTenant->getContent(), $missing->getContent());

        $membership = OrganisationMembership::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
        $membership->update(['deactivated_at' => now()]);

        $this->actingAs($user, 'web')
            ->getJson("{$base}/999999")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);

        $this->actingAs($user, 'web')
            ->getJson($base)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);

        $this->actingAs($user, 'web')
            ->patchJson("{$base}/{$otherCandidate->getKey()}", ['first_name' => 'Changed'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);
    }

    #[DataProvider('writingRoles')]
    public function test_admin_and_recruiter_partially_update_candidates(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, $role);
        $candidate = $this->createCandidate($organisation, 'Before', 'Candidate');
        $url = "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}";

        $this->actingAs($user, 'web')->patchJson($url, [
            'first_name' => 'After',
            'email' => '  UPDATED@EXAMPLE.TEST ',
        ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'After')
            ->assertJsonPath('data.last_name', 'Candidate')
            ->assertJsonPath('data.email', 'updated@example.test');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->getKey(),
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'After',
            'email' => 'updated@example.test',
        ]);
    }

    public function test_hiring_manager_update_is_forbidden_and_leaves_data_unchanged(): void
    {
        config()->set('app.debug', false);
        $manager = User::factory()->create();
        [$organisation] = $this->createMembership($manager, 'hiring_manager');
        $candidate = $this->createCandidate($organisation, 'Unchanged', 'Candidate');

        $this->actingAs($manager, 'web')
            ->patchJson(
                "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}",
                ['first_name' => 'Forbidden'],
            )
            ->assertForbidden();

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->getKey(),
            'first_name' => 'Unchanged',
        ]);
    }

    public function test_update_rejects_security_fields_and_cannot_move_a_candidate(): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $candidate = $this->createCandidate($organisation, 'Unchanged', 'Candidate');

        $this->actingAs($user, 'web')
            ->patchJson(
                "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}",
                [
                    'first_name' => '',
                    'email' => 'invalid',
                    'organisation_id' => $other->getKey(),
                    'id' => 900,
                    'created_at' => now()->toAtomString(),
                    'updated_at' => now()->toAtomString(),
                ],
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'email',
                'organisation_id',
                'id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->getKey(),
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Unchanged',
        ]);
    }

    public function test_cross_tenant_candidate_cannot_be_updated_through_another_organisation(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        [$organisation] = $this->createMembership($user, 'admin');
        $other = Organisation::query()->create(['name' => 'Other']);
        $candidate = $this->createCandidate($other, 'Other', 'Candidate');

        $this->actingAs($user, 'web')
            ->patchJson(
                "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}",
                ['first_name' => 'Attempted'],
            )
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not Found']);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->getKey(),
            'organisation_id' => $other->getKey(),
            'first_name' => 'Other',
        ]);
    }

    /**
     * @return array<string, string|null>
     */
    private function validPayload(): array
    {
        return [
            'first_name' => 'Aiko',
            'last_name' => 'Tanaka',
            'email' => 'aiko@example.test',
            'phone' => '+81 90 0000 0000',
            'occupation' => 'Nurse',
            'location' => 'Tokyo',
            'availability' => 'Immediately',
            'notes' => 'Experienced clinician.',
        ];
    }

    /**
     * @return array{Organisation, OrganisationMembership}
     */
    private function createMembership(
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

    private function createCandidate(
        Organisation $organisation,
        string $firstName,
        string $lastName,
        ?string $email = null,
        ?string $occupation = null,
    ): Candidate {
        return Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'occupation' => $occupation,
        ]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allRoles(): array
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
    public static function writingRoles(): array
    {
        return [
            'admin' => ['admin'],
            'recruiter' => ['recruiter'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function searchCases(): array
    {
        return [
            'first name' => ['aki'],
            'last name' => ['NAKA'],
            'combined name' => ['Akiko Tanaka'],
            'email' => ['AKIKO@EXAMPLE.TEST'],
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
