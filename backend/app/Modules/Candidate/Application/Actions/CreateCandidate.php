<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateCreator;
use App\Modules\Candidate\Application\Data\CandidateData;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class CreateCandidate
{
    public function __construct(
        private CandidateCreator $candidates,
        private CanonicalEmailNormalizer $emailNormalizer,
    ) {}

    public function handle(TenantContext $tenant, CandidateData $data): CandidateRecord
    {
        return $this->candidates->create(
            $tenant->organisationId,
            new CandidateData(
                firstName: $data->firstName,
                lastName: $data->lastName,
                email: $data->email === null
                    ? null
                    : $this->emailNormalizer->normalize($data->email),
                phone: $data->phone,
                occupation: $data->occupation,
                location: $data->location,
                availability: $data->availability,
                notes: $data->notes,
            ),
        );
    }
}
