<?php

namespace App\Modules\Ai\Interfaces\Http\Resources;

use App\Modules\Ai\Application\Data\CvExtractionRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CvExtractionRecord */
final class CvExtractionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CvExtractionRecord $record */
        $record = $this->resource;

        return [
            'id' => $record->id, 'candidate_id' => $record->candidateId,
            'candidate_document_id' => $record->candidateDocumentId, 'status' => $record->status->value,
            'prompt_version' => $record->promptVersion, 'schema_version' => $record->schemaVersion,
            'draft' => $record->draft, 'failure_code' => $record->failureCode,
            'created_at' => $record->createdAt->format(DATE_ATOM),
            'applied_at' => $record->appliedAt?->format(DATE_ATOM),
        ];
    }
}
