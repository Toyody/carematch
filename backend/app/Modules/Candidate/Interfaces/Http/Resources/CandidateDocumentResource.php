<?php

namespace App\Modules\Candidate\Interfaces\Http\Resources;

use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateDocumentRecord */
final class CandidateDocumentResource extends JsonResource
{
    /** @return array<string, int|string> */
    public function toArray(Request $request): array
    {
        /** @var CandidateDocumentRecord $document */
        $document = $this->resource;

        return [
            'id' => $document->id,
            'original_name' => $document->originalName,
            'mime_type' => $document->mimeType,
            'size_bytes' => $document->sizeBytes,
            'uploaded_by_user_id' => $document->uploadedByUserId,
            'created_at' => $document->createdAt->format(DATE_ATOM),
            'updated_at' => $document->updatedAt->format(DATE_ATOM),
        ];
    }
}
