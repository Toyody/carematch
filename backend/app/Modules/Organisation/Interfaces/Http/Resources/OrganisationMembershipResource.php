<?php

namespace App\Modules\Organisation\Interfaces\Http\Resources;

use App\Modules\Organisation\Application\Data\OrganisationMembershipSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganisationMembershipSummary
 */
final class OrganisationMembershipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganisationMembershipSummary $membership */
        $membership = $this->resource;

        return [
            'id' => $membership->id,
            'user' => [
                'id' => $membership->userId,
                'name' => $membership->userName,
                'email' => $membership->userEmail,
            ],
            'role' => $membership->role->value,
            'deactivated_at' => $membership->deactivatedAt?->format(DATE_ATOM),
            'created_at' => $membership->createdAt->format(DATE_ATOM),
            'updated_at' => $membership->updatedAt->format(DATE_ATOM),
        ];
    }
}
