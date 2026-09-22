<?php

namespace App\Modules\Candidate\Application\Contracts;

interface CandidateDocumentStorage
{
    public function exists(string $storageKey): bool;

    public function put(string $sourcePath, string $storageKey): void;

    public function get(string $storageKey): string;

    public function delete(string $storageKey): void;
}
