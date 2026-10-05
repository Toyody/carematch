<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Contracts\AiRequestDispatcher;
use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Data\CvExtractionRecord;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Ai\Application\Exceptions\PermanentAiFailure;
use App\Modules\Ai\Application\Support\AiConfiguration;
use App\Modules\Candidate\Application\Contracts\CandidateDocumentStore;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class RequestCvExtraction
{
    public const string PROMPT_VERSION = 'cv_extraction_prompt_v1';

    public const string SCHEMA_VERSION = 'cv_extraction_schema_v1';

    public function __construct(
        private AiConfiguration $configuration,
        private CandidateDocumentStore $documents,
        private CvExtractionStore $extractions,
        private AiRequestDispatcher $dispatcher,
    ) {}

    public function handle(TenantContext $tenant, int $candidateId, int $documentId, string $idempotencyKey): CvExtractionRecord
    {
        $this->configuration->assertEnabled();
        $document = $this->documents->find($tenant->organisationId, $candidateId, $documentId)
            ?? throw new AiResourceNotFound;
        if (! in_array($document->mimeType, [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ], true)) {
            throw new PermanentAiFailure('unsupported_document');
        }
        $fingerprint = hash('sha256', sprintf('cv-extraction:v1:%d:%d:%d', $tenant->organisationId, $candidateId, $documentId));
        $record = $this->extractions->createOrRetrieve(
            $tenant, $candidateId, $documentId, hash('sha256', $idempotencyKey), $fingerprint,
            $this->configuration->provider(), $this->configuration->model(), self::PROMPT_VERSION, self::SCHEMA_VERSION,
        );
        if ($record->status->value === 'queued') {
            $this->dispatcher->dispatchCvExtractionAfterCommit($record->id);
        }

        return $record;
    }
}
