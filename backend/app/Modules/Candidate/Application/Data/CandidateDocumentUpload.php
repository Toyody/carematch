<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidateDocumentUpload
{
    public function __construct(
        public string $temporaryPath,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
