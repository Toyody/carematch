<?php

namespace App\Modules\Ai\Application\Actions;

use App\Modules\Ai\Application\Contracts\CvExtractionStore;
use App\Modules\Ai\Application\Data\CvExtractionRecord;
use App\Modules\Ai\Application\Exceptions\AiResourceNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ViewCvExtraction
{
    public function __construct(private CvExtractionStore $store) {}

    public function handle(TenantContext $tenant, int $candidateId, int $documentId, int $extractionId): CvExtractionRecord
    {
        return $this->store->find($tenant->organisationId, $candidateId, $documentId, $extractionId)
            ?? throw new AiResourceNotFound;
    }
}
