<?php

namespace App\Modules\Organisation\Interfaces\Http\Resources;

use App\Modules\Organisation\Application\Data\OrganisationInvitationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganisationInvitationSummary
 */
final class OrganisationInvitationResource extends JsonResource
{
    /**
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganisationInvitationSummary $invitation */
        $invitation = $this->resource;

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expiresAt->format(DATE_ATOM),
            'accepted_at' => $invitation->acceptedAt?->format(DATE_ATOM),
            'revoked_at' => $invitation->revokedAt?->format(DATE_ATOM),
            'created_at' => $invitation->createdAt->format(DATE_ATOM),
        ];
    }
}
