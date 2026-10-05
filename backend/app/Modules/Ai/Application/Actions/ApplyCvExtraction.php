<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Contracts\AiReviewTransaction;
use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Exceptions\AiReviewUnavailable;
use App\Modules\Ai\Application\Exceptions\StaleAiReview;
use App\Modules\Ai\Domain\AiOperationStatus;
use App\Modules\Candidate\Application\Actions\UpdateCandidate;
use App\Modules\Candidate\Application\Data\CandidateRecord;
use App\Modules\Candidate\Application\Exceptions\StaleCandidate;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ApplyCvExtraction
{
    public function __construct(
        private AiReviewTransaction $transaction,
        private CvExtractionStore $extractions,
        private UpdateCandidate $updateCandidate,
    ) {}

    /** @param array<string, string|null> $changes */
    public function handle(TenantContext $tenant, int $candidateId, int $documentId, int $extractionId, array $changes): CandidateRecord
    {
        return $this->transaction->run(function () use ($tenant, $candidateId, $documentId, $extractionId, $changes): CandidateRecord {
            $extraction = $this->extractions->find($tenant->organisationId, $candidateId, $documentId, $extractionId, true)
                ?? throw new AiResourceNotFound;
            if ($extraction->status !== AiOperationStatus::ReviewReady || $extraction->candidateVersion === null || $extraction->candidateFingerprint === null) {
                throw new AiReviewUnavailable;
            }
            try {
                $candidate = $this->updateCandidate->handle($tenant, $candidateId, $changes, $extraction->candidateVersion, $extraction->candidateFingerprint);
            } catch (StaleCandidate) {
                throw new StaleAiReview;
            }
            $this->extractions->markApplied($extractionId, $tenant->userId);

            return $candidate;
        });
    }
}
