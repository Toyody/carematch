<?php

namespace App\Modules\Compliance\Application\Contracts;

use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;

interface QualificationDefinitionStore
{
    /** @return list<QualificationDefinitionRecord> */
    public function all(int $organisationId): array;

    public function create(int $organisationId, QualificationDefinitionData $data): QualificationDefinitionRecord;

    public function update(int $organisationId, int $definitionId, QualificationDefinitionData $data): ?QualificationDefinitionRecord;
}
