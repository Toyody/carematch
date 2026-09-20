<?php

namespace Tests\Feature\Organisation;

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organisation\Application\Contracts\OrganisationInvitationAcceptor;
use App\Modules\Organisation\Infrastructure\Notifications\OrganisationInvitationNotification;
use App\Modules\Organisation\Infrastructure\Persistence\Organisation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationInvitation;
use App\Modules\Organisation\Infrastructure\Persistence\OrganisationMembership;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OrganisationInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_cannot_manage_or_accept_invitations(): void
    {
        $organisation = Organisation::query()->create(['name' => 'Protected']);

        $this->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
            'email' => 'invitee@example.test',
            'role' => 'recruiter',
        ])->assertUnauthorized();
        $this->getJson("/api/v1/organisations/{$organisation->getKey()}/invitations")
            ->assertUnauthorized();
        $this->postJson('/api/v1/organisation-invitations/accept', ['token' => 'unknown'])
            ->assertUnauthorized();
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_members_cannot_create_list_or_revoke_invitations(string $role): void
    {
        $user = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($user, $role);
        $invitation = $this->createInvitationRecord($organisation, $user);

        $this->actingAs($user, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => 'invitee@example.test',
                'role' => 'recruiter',
            ])->assertForbidden();
        $this->actingAs($user, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/invitations")
            ->assertForbidden();
        $this->actingAs($user, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations/{$invitation->getKey()}/revoke")
            ->assertForbidden();
    }

    public function test_admin_creates_a_canonicalised_hashed_time_limited_invitation_and_delivery(): void
    {
        Notification::fake();
        config()->set('carematch.frontend_url', 'https://frontend.carematch.test');
        config()->set('carematch.invitation_expiry_days', 7);

        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $before = now()->addDays(7)->subSecond();

        $response = $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => '  INVITEE@EXAMPLE.TEST ',
                'role' => 'recruiter',
            ]);

        $after = now()->addDays(7)->addSecond();
        $response->assertCreated()
            ->assertJsonPath('data.email', 'invitee@example.test')
            ->assertJsonPath('data.role', 'recruiter')
            ->assertJsonMissingPath('data.token_hash')
            ->assertJsonMissingPath('data.token');

        $invitation = OrganisationInvitation::query()->sole();
        $rawToken = $this->captureDeliveredToken('invitee@example.test');

        self::assertSame($organisation->getKey(), $invitation->getAttribute('organisation_id'));
        self::assertSame($admin->getKey(), $invitation->getAttribute('invited_by_user_id'));
        self::assertSame(hash('sha256', $rawToken), $invitation->getAttribute('token_hash'));
        self::assertNotSame($rawToken, $invitation->getAttribute('token_hash'));
        self::assertTrue($invitation->expires_at->between($before, $after));
        self::assertStringNotContainsString($rawToken, $response->getContent());
        self::assertArrayNotHasKey('token_hash', $invitation->toArray());
    }

    #[DataProvider('roles')]
    public function test_each_fixed_role_may_be_invited(string $role): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => "{$role}@example.test",
                'role' => $role,
            ])->assertCreated()->assertJsonPath('data.role', $role);
    }

    public function test_invalid_role_and_client_controlled_security_fields_are_rejected(): void
    {
        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => 'invitee@example.test',
                'role' => 'owner',
                'organisation_id' => 999,
                'user_id' => 999,
                'invited_by_user_id' => 999,
                'membership_id' => 999,
                'token_hash' => 'client-token',
                'accepted_at' => now()->toAtomString(),
                'revoked_at' => now()->toAtomString(),
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'role',
                'organisation_id',
                'user_id',
                'invited_by_user_id',
                'membership_id',
                'token_hash',
                'accepted_at',
                'revoked_at',
            ]);

        self::assertSame(0, OrganisationInvitation::query()->count());
    }

    public function test_inaccessible_tenants_share_not_found_semantics_and_cannot_be_switched_by_body(): void
    {
        config()->set('app.debug', false);
        $user = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$ownOrganisation] = $this->createOrganisationMembership($user, 'admin', 'Own');
        [$otherOrganisation] = $this->createOrganisationMembership($otherAdmin, 'admin', 'Other');

        foreach ([$otherOrganisation->getKey(), 999999] as $organisationId) {
            $this->actingAs($user, 'web')
                ->postJson("/api/v1/organisations/{$organisationId}/invitations", [
                    'email' => 'invitee@example.test',
                    'role' => 'admin',
                    'organisation_id' => $ownOrganisation->getKey(),
                ])->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        }

        self::assertSame(0, OrganisationInvitation::query()->count());
    }

    public function test_active_member_and_duplicate_pending_invitation_return_conflicts_but_deactivated_member_can_be_invited(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        $active = User::factory()->create(['email' => 'active@example.test']);
        $deactivated = User::factory()->create(['email' => 'deactivated@example.test']);
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $this->createOrganisationMembership($active, 'recruiter', organisation: $organisation);
        $this->createOrganisationMembership(
            $deactivated,
            'hiring_manager',
            deactivated: true,
            organisation: $organisation,
        );

        $this->invite($admin, $organisation, 'active@example.test')
            ->assertConflict()
            ->assertExactJson(['message' => 'The user is already an active member of this organisation.']);

        $this->invite($admin, $organisation, 'deactivated@example.test')->assertCreated();
        $this->invite($admin, $organisation, 'deactivated@example.test')
            ->assertConflict()
            ->assertExactJson(['message' => 'An unresolved invitation already exists for this email.']);
    }

    public function test_an_expired_unresolved_invitation_is_closed_and_may_be_replaced(): void
    {
        Notification::fake();
        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $expired = $this->createInvitationRecord(
            $organisation,
            $admin,
            email: 'expired@example.test',
            expiresAt: now()->subMinute(),
        );

        $this->invite($admin, $organisation, 'expired@example.test')->assertCreated();

        self::assertNotNull($expired->fresh()?->getAttribute('revoked_at'));
        self::assertSame(2, OrganisationInvitation::query()->count());
    }

    public function test_admin_lists_only_tenant_invitations_without_tokens(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [$otherOrganisation] = $this->createOrganisationMembership($otherAdmin, 'admin');
        $own = $this->createInvitationRecord($organisation, $admin, email: 'own@example.test');
        $this->createInvitationRecord($otherOrganisation, $otherAdmin, email: 'other@example.test');

        $response = $this->actingAs($admin, 'web')
            ->getJson("/api/v1/organisations/{$organisation->getKey()}/invitations");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->getKey())
            ->assertJsonPath('data.0.email', 'own@example.test')
            ->assertJsonMissingPath('data.0.token_hash')
            ->assertJsonMissingPath('data.0.token');
        self::assertStringNotContainsString((string) $own->getAttribute('token_hash'), $response->getContent());
    }

    public function test_admin_revokes_pending_invitation_idempotently_and_cross_tenant_ids_are_not_found(): void
    {
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [$otherOrganisation] = $this->createOrganisationMembership($otherAdmin, 'admin');
        $invitation = $this->createInvitationRecord($organisation, $admin);
        $otherInvitation = $this->createInvitationRecord($otherOrganisation, $otherAdmin);

        $path = "/api/v1/organisations/{$organisation->getKey()}/invitations/{$invitation->getKey()}/revoke";
        $this->actingAs($admin, 'web')->postJson($path)->assertNoContent();
        $firstRevokedAt = $invitation->fresh()?->getAttribute('revoked_at');
        $this->actingAs($admin, 'web')->postJson($path)->assertNoContent();
        self::assertEquals($firstRevokedAt, $invitation->fresh()?->getAttribute('revoked_at'));

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations/{$otherInvitation->getKey()}/revoke")
            ->assertNotFound()->assertExactJson(['message' => 'Not Found']);
        self::assertNull($otherInvitation->fresh()?->getAttribute('revoked_at'));
    }

    public function test_an_accepted_invitation_cannot_be_revoked(): void
    {
        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $invitation = $this->createInvitationRecord($organisation, $admin, acceptedAt: now());

        $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations/{$invitation->getKey()}/revoke")
            ->assertConflict()
            ->assertExactJson(['message' => 'An accepted invitation cannot be revoked.']);
    }

    public function test_matching_authenticated_user_accepts_and_cannot_reuse_an_invitation(): void
    {
        $admin = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $rawToken = 'valid-high-entropy-test-token';
        $invitation = $this->createInvitationRecord(
            $organisation,
            $admin,
            email: 'invitee@example.test',
            role: 'hiring_manager',
            tokenHash: hash('sha256', $rawToken),
        );

        $this->accept($invitee, $rawToken)->assertNoContent();

        $membership = OrganisationMembership::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $invitee->getKey())
            ->sole();
        self::assertSame('hiring_manager', $membership->getAttribute('role'));
        self::assertNull($membership->getAttribute('deactivated_at'));
        self::assertNotNull($invitation->fresh()?->getAttribute('accepted_at'));

        $this->accept($invitee, $rawToken)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
        self::assertSame(1, OrganisationMembership::query()
            ->where('organisation_id', $organisation->getKey())
            ->where('user_id', $invitee->getKey())
            ->count());
    }

    public function test_acceptance_reactivates_membership_and_applies_the_persisted_invitation_role(): void
    {
        $admin = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'returning@example.test']);
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        [, $membership] = $this->createOrganisationMembership(
            $invitee,
            'recruiter',
            deactivated: true,
            organisation: $organisation,
        );
        $rawToken = 'reactivation-token';
        $this->createInvitationRecord(
            $organisation,
            $admin,
            email: 'returning@example.test',
            role: 'admin',
            tokenHash: hash('sha256', $rawToken),
        );

        $this->actingAs($invitee, 'web')
            ->postJson('/api/v1/organisation-invitations/accept', [
                'token' => $rawToken,
                'organisation_id' => 999,
                'user_id' => $admin->getKey(),
                'role' => 'recruiter',
                'membership_id' => 999,
            ])->assertUnprocessable()->assertJsonValidationErrors([
                'organisation_id', 'user_id', 'role', 'membership_id',
            ]);

        $this->accept($invitee, $rawToken)->assertNoContent();
        $membership->refresh();
        self::assertSame('admin', $membership->getAttribute('role'));
        self::assertNull($membership->getAttribute('deactivated_at'));
    }

    public function test_all_unavailable_or_wrong_recipient_tokens_share_safe_errors(): void
    {
        $admin = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.test']);
        $wrongUser = User::factory()->create(['email' => 'wrong@example.test']);
        $cases = [
            ['invalid-token', null, $invitee],
            ['expired-token', now()->subMinute(), $invitee],
            ['revoked-token', null, $invitee],
            ['accepted-token', null, $invitee],
            ['wrong-email-token', null, $wrongUser],
        ];

        $responses = [];
        foreach ($cases as [$rawToken, $expiresAt, $user]) {
            if ($rawToken !== 'invalid-token') {
                [$organisation] = $this->createOrganisationMembership(
                    $admin,
                    'admin',
                    "Case {$rawToken}",
                );
                $this->createInvitationRecord(
                    $organisation,
                    $admin,
                    email: 'invitee@example.test',
                    tokenHash: hash('sha256', $rawToken),
                    expiresAt: $expiresAt,
                    acceptedAt: $rawToken === 'accepted-token' ? now() : null,
                    revokedAt: $rawToken === 'revoked-token' ? now() : null,
                );
            }

            $response = $this->accept($user, $rawToken);
            self::assertSame(422, $response->getStatusCode(), $rawToken);
            $response->assertUnprocessable()->assertJsonValidationErrors(['token']);
            $responses[] = $response->getContent();
        }

        self::assertCount(1, array_unique($responses));
    }

    public function test_membership_failure_rolls_back_invitation_acceptance(): void
    {
        $admin = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $rawToken = 'rollback-token';
        $invitation = $this->createInvitationRecord(
            $organisation,
            $admin,
            email: 'missing-user@example.test',
            tokenHash: hash('sha256', $rawToken),
        );

        try {
            $this->app->make(OrganisationInvitationAcceptor::class)->accept(
                hash('sha256', $rawToken),
                999999,
                'missing-user@example.test',
                now()->toDateTimeImmutable(),
            );
            self::fail('The missing user should violate the membership foreign key.');
        } catch (QueryException) {
            self::assertNull($invitation->fresh()?->getAttribute('accepted_at'));
            self::assertSame(0, OrganisationMembership::query()
                ->where('organisation_id', $organisation->getKey())
                ->where('user_id', 999999)
                ->count());
        }
    }

    public function test_database_constraints_protect_inviter_role_email_state_and_unresolved_uniqueness(): void
    {
        $admin = User::factory()->create();
        $outsider = User::factory()->create();
        [$organisation] = $this->createOrganisationMembership($admin, 'admin');
        $this->createInvitationRecord($organisation, $admin, email: 'duplicate@example.test');

        $invalidRows = [
            ['email' => 'UPPER@example.test', 'role' => 'admin', 'inviter' => $admin->getKey(), 'accepted' => null, 'revoked' => null],
            ['email' => 'role@example.test', 'role' => 'owner', 'inviter' => $admin->getKey(), 'accepted' => null, 'revoked' => null],
            ['email' => 'outsider@example.test', 'role' => 'admin', 'inviter' => $outsider->getKey(), 'accepted' => null, 'revoked' => null],
            ['email' => 'state@example.test', 'role' => 'admin', 'inviter' => $admin->getKey(), 'accepted' => now(), 'revoked' => now()],
            ['email' => 'duplicate@example.test', 'role' => 'admin', 'inviter' => $admin->getKey(), 'accepted' => null, 'revoked' => null],
        ];

        foreach ($invalidRows as $index => $row) {
            try {
                DB::transaction(static function () use ($organisation, $row, $index): void {
                    DB::table('organisation_invitations')->insert([
                        'organisation_id' => $organisation->getKey(),
                        'email' => $row['email'],
                        'role' => $row['role'],
                        'token_hash' => hash('sha256', "constraint-{$index}"),
                        'invited_by_user_id' => $row['inviter'],
                        'expires_at' => now()->addDay(),
                        'accepted_at' => $row['accepted'],
                        'revoked_at' => $row['revoked'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                self::fail("Constraint case {$index} should fail.");
            } catch (QueryException) {
                // Each row violates exactly one independently meaningful constraint.
            }
        }

        self::assertSame(1, OrganisationInvitation::query()->count());
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

    private function createInvitationRecord(
        Organisation $organisation,
        User $inviter,
        string $email = 'invitee@example.test',
        string $role = 'recruiter',
        ?string $tokenHash = null,
        ?DateTimeInterface $expiresAt = null,
        ?DateTimeInterface $acceptedAt = null,
        ?DateTimeInterface $revokedAt = null,
    ): OrganisationInvitation {
        return OrganisationInvitation::query()->create([
            'organisation_id' => $organisation->getKey(),
            'email' => $email,
            'role' => $role,
            'token_hash' => $tokenHash ?? hash('sha256', (string) Str::uuid()),
            'invited_by_user_id' => $inviter->getKey(),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'accepted_at' => $acceptedAt,
            'revoked_at' => $revokedAt,
        ]);
    }

    private function invite(User $admin, Organisation $organisation, string $email): TestResponse
    {
        return $this->actingAs($admin, 'web')
            ->postJson("/api/v1/organisations/{$organisation->getKey()}/invitations", [
                'email' => $email,
                'role' => 'recruiter',
            ]);
    }

    private function accept(User $user, string $rawToken): TestResponse
    {
        Auth::forgetGuards();

        return $this->actingAs($user, 'web')
            ->postJson('/api/v1/organisation-invitations/accept', ['token' => $rawToken]);
    }

    private function captureDeliveredToken(string $recipient): string
    {
        $rawToken = null;

        Notification::assertSentOnDemand(
            OrganisationInvitationNotification::class,
            function (
                OrganisationInvitationNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use ($recipient, &$rawToken): bool {
                self::assertContains('mail', $channels);
                self::assertSame($recipient, $notifiable->routes['mail']);
                $url = $notification->toMail($notifiable)->actionUrl;
                self::assertIsString($url);
                $parts = parse_url($url);
                self::assertIsArray($parts);
                self::assertSame('https', $parts['scheme'] ?? null);
                self::assertSame('frontend.carematch.test', $parts['host'] ?? null);
                self::assertSame('/invitations/accept', $parts['path'] ?? null);
                self::assertArrayNotHasKey('query', $parts);
                parse_str((string) ($parts['fragment'] ?? ''), $fragment);
                $rawToken = $fragment['token'] ?? null;

                return true;
            },
        );

        self::assertIsString($rawToken);

        return $rawToken;
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
}
