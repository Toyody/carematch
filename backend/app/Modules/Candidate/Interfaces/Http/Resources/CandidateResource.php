<?php

namespace App\Modules\Candidate\Interfaces\Http\Resources;

use App\Modules\Candidate\Application\Data\CandidateRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateRecord
 */
final class CandidateResource extends JsonResource
{
    /**
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        /** @var CandidateRecord $candidate */
        $candidate = $this->resource;

        return [
            'id' => $candidate->id,
            'first_name' => $candidate->firstName,
            'last_name' => $candidate->lastName,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'occupation' => $candidate->occupation,
            'location' => $candidate->location,
            'availability' => $candidate->availability,
            'notes' => $candidate->notes,
            'created_at' => $candidate->createdAt->format(DATE_ATOM),
            'updated_at' => $candidate->updatedAt->format(DATE_ATOM),
        ];
    }
}
