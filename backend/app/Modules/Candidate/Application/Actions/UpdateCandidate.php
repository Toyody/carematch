<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateUpdater;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Identity\Application\Contracts\CanonicalEmailNormalizer;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class UpdateCandidate
{
    public function __construct(
        private CandidateUpdater $candidates,
        private CanonicalEmailNormalizer $emailNormalizer,
    ) {}

    /**
     * @param  array<string, string|null>  $changes
     */
    public function handle(
        TenantContext $tenant,
        int $candidateId,
        array $changes,
    ): CandidateRecord {
        if (array_key_exists('email', $changes) && $changes['email'] !== null) {
            $changes['email'] = $this->emailNormalizer->normalize($changes['email']);
        }

        return $this->candidates->update(
            $tenant->organisationId,
            $candidateId,
            $changes,
        ) ?? throw new CandidateNotFound;
    }
}
