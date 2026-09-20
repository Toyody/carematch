<?php

namespace App\Modules\Organisation\Interfaces\Http\Resources;

use App\Modules\Organisation\Application\Data\OrganisationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganisationSummary
 */
final class OrganisationResource extends JsonResource
{
    /**
     * @return array<string, int|string|array<string, string>>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganisationSummary $organisation */
        $organisation = $this->resource;

        return [
            'id' => $organisation->id,
            'name' => $organisation->name,
            'membership' => [
                'role' => $organisation->role,
            ],
            'created_at' => $organisation->createdAt->format(DATE_ATOM),
        ];
    }
}
