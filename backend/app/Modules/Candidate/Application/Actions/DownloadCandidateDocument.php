<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Data\CandidateDocumentDownload;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentNotFound;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class DownloadCandidateDocument
{
    public function __construct(
        private CandidateDetails $candidates,
        private CandidateDocumentStore $documents,
        private CandidateDocumentStorage $storage,
    ) {}

    public function handle(
        TenantContext $tenant,
        int $candidateId,
        int $documentId,
    ): CandidateDocumentDownload {
        if ($this->candidates->find($tenant->organisationId, $candidateId) === null) {
            throw new CandidateNotFound;
        }

        $document = $this->documents->find(
            $tenant->organisationId,
            $candidateId,
            $documentId,
        ) ?? throw new CandidateDocumentNotFound;

        return new CandidateDocumentDownload(
            document: $document,
            contents: $this->storage->get($document->storageKey),
        );
    }
}
