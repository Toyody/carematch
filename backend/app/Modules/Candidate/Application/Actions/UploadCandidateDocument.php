<?php

namespace App\Modules\Candidate\Application\Actions;

use App\Modules\Candidate\Application\Contracts\CandidateDetails;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStorage;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Candidate\Application\Data\CandidateDocumentRecord;
use App\Modules\Candidate\Application\Data\CandidateDocumentUpload;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentPersistenceFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateDocumentStorageFailure;
use App\Modules\Candidate\Application\Exceptions\CandidateNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;
use Throwable;

final readonly class UploadCandidateDocument
{
    public function __construct(
        private CandidateDetails $candidates,
        private CandidateDocumentStore $documents,
        private CandidateDocumentStorage $storage,
    ) {}

    public function handle(
        TenantContext $tenant,
        int $candidateId,
        CandidateDocumentUpload $upload,
    ): CandidateDocumentRecord {
        if ($this->candidates->find($tenant->organisationId, $candidateId) === null) {
            throw new CandidateNotFound;
        }

        $storageKey = $this->newStorageKey();
        $this->storage->put($upload->temporaryPath, $storageKey);

        try {
            return $this->documents->create(
                $tenant->organisationId,
                $candidateId,
                $tenant->userId,
                $storageKey,
                $upload,
            );
        } catch (Throwable) {
            try {
                $this->storage->delete($storageKey);
            } catch (CandidateDocumentStorageFailure) {
                throw new CandidateDocumentStorageFailure;
            }

            throw new CandidateDocumentPersistenceFailure;
        }
    }

    private function newStorageKey(): string
    {
        do {
            $storageKey = bin2hex(random_bytes(32));
        } while ($this->storage->exists($storageKey));

        return $storageKey;
    }
}
