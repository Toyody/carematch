<?php

namespace App\Modules\Candidate\Interfaces\Http\Resources;

use App\Modules\Candidate\Application\Data\CandidateQualificationRecord;
use App\Modules\Compliance\Domain\QualificationExpiryClassifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CandidateQualificationRecord */
final class CandidateQualificationResource extends JsonResource
{
    /** @return array<string, int|string|null> */
    public function toArray(Request $request): array
    {
        /** @var CandidateQualificationRecord $credential */
        $credential = $this->resource;
        $today = CarbonImmutable::today('UTC');
        $status = (new QualificationExpiryClassifier)->classify(
            $credential->expiresOn,
            $today,
            max(0, (int) config('carematch.compliance.expiry_warning_days', 30)),
        );

        return [
            'id' => $credential->id,
            'qualification_definition_id' => $credential->qualificationDefinitionId,
            'qualification_name' => $credential->qualificationName,
            'issuer' => $credential->issuer,
            'credential_number' => $credential->credentialNumber,
            'issued_on' => $credential->issuedOn?->format('Y-m-d'),
            'expires_on' => $credential->expiresOn?->format('Y-m-d'),
            'status' => $status->value,
            'created_at' => $credential->createdAt->format(DATE_ATOM),
            'updated_at' => $credential->updatedAt->format(DATE_ATOM),
        ];
    }
}
