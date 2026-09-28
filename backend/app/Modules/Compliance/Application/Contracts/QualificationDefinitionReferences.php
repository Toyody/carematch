<?php

namespace App\Modules\Compliance\Application\Contracts;

interface QualificationDefinitionReferences
{
    public function activeExists(int $organisationId, int $definitionId): bool;

    /**
     * @param  list<int>  $definitionIds
     * @return array<int, string>
     */
    public function names(int $organisationId, array $definitionIds): array;
}
