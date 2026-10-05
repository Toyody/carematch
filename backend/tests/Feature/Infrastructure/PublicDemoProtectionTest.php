<?php

namespace Tests\Feature\Infrastructure;

use App\Modules\Candidate\Infrastructure\Persistence\Candidate;
use App\Modules\Candidate\Infrastructure\Persistence\CandidateDocument;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicDemoProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('carematch.portfolio_demo.public_mode', true);
    }

    public function test_public_identity_creation_and_recovery_are_disabled(): void
    {
        $this->postJson('/api/v1/auth/register', [])->assertForbidden();
        $this->postJson('/api/v1/auth/forgot-password', [])->assertForbidden();
        $this->postJson('/api/v1/auth/reset-password', [])->assertForbidden();
    }

    public function test_login_remains_available(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable();
    }

    public function test_organisation_creation_and_invitation_sending_are_disabled(): void
    {
        [$user, $organisation] = $this->tenant();

        $this->actingAs($user, 'web')
            ->postJson('/api/v1/organisations', ['name' => 'Blocked'])
            ->assertForbidden();

        $this->actingAs($user, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => 'invitee@example.test',
                'role' => 'recruiter',
            ])
            ->assertForbidden();
    }

    public function test_candidate_document_reads_remain_available_but_writes_are_disabled(): void
    {
        [$user, $organisation] = $this->tenant();
        $candidate = Candidate::query()->create([
            'organisation_id' => $organisation->getKey(),
            'first_name' => 'Synthetic',
            'last_name' => 'Candidate',
        ]);
        $document = CandidateDocument::query()->create([
            'organisation_id' => $organisation->getKey(),
            'candidate_id' => $candidate->getKey(),
            'uploaded_by_user_id' => $user->getKey(),
            'original_name' => 'synthetic.pdf',
            'storage_key' => hash('sha256', 'synthetic-document'),
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);
        $base = "/api/v1/organisations/{$organisation->getKey()}/candidates/{$candidate->getKey()}/documents";

        $this->actingAs($user, 'web')->getJson($base)->assertOk();
        $this->actingAs($user, 'web')->postJson($base)->assertForbidden();
        $this->actingAs($user, 'web')->deleteJson("{$base}/{$document->getKey()}")->assertForbidden();
    }

    public function test_compliance_expiry_digest_delivery_is_disabled(): void
    {
        [$user, $organisation] = $this->tenant();

        $this->actingAs($user, 'web')->postJson(
            "/api/v1/organisations/{$organisation->getKey()}/compliance/expiry-digests",
            [],
            ['Idempotency-Key' => 'public-demo-digest'],
        )->assertForbidden();

        $this->assertDatabaseCount('compliance_expiry_digest_requests', 0);
    }

    public function test_restrictions_are_inactive_outside_public_demo_mode(): void
    {
        config()->set('carematch.portfolio_demo.public_mode', false);

        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
    }

    /** @return array{User, Organisation} */
    private function tenant(): array
    {
        $user = User::factory()->create();
        $organisation = Organisation::query()->create(['name' => 'Synthetic Demo Organisation']);
        OrganisationMembership::query()->create([
            'organisation_id' => $organisation->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
        ]);

        return [$user, $organisation];
    }
}
