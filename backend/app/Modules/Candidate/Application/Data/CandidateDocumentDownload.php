<?php

namespace App\Modules\Candidate\Application\Data;

final readonly class CandidateDocumentDownload
{
    public function __construct(
        public CandidateDocumentRecord $document,
        public string $contents,
    ) {}
}
