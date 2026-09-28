<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use App\Modules\Compliance\Application\Exceptions\QualificationDefinitionNotFound;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class UpdateQualificationDefinition
{
    public function __construct(private QualificationDefinitionStore $definitions) {}

    public function handle(TenantContext $tenant, int $definitionId, QualificationDefinitionData $data): QualificationDefinitionRecord
    {
        return $this->definitions->update($tenant->organisationId, $definitionId, $data)
            ?? throw new QualificationDefinitionNotFound;
    }
}
