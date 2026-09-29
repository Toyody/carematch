<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Data\QualificationDefinitionData;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class CreateQualificationDefinition
{
    public function __construct(private QualificationDefinitionStore $definitions) {}

    public function handle(TenantContext $tenant, QualificationDefinitionData $data): QualificationDefinitionRecord
    {
        return $this->definitions->create($tenant->organisationId, $tenant->userId, $data);
    }
}
