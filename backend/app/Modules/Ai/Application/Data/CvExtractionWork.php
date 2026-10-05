<?php

namespace App\Modules\Ai\Application\Data;

final readonly class CvExtractionWork
{
    public function __construct(
        public int $id,
        public int $organisationId,
        public int $candidateId,
        public int $candidateDocumentId,
        public string $provider,
        public string $model,
    ) {}
}
