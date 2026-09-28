<?php

namespace App\Modules\Compliance\Application\Actions;

use App\Modules\Compliance\Application\Contracts\QualificationDefinitionStore;
use App\Modules\Compliance\Application\Data\QualificationDefinitionRecord;
use App\Modules\Organisation\Application\Data\TenantContext;

final readonly class ListQualificationDefinitions
{
    public function __construct(private QualificationDefinitionStore $definitions) {}

    /** @return list<QualificationDefinitionRecord> */
    public function handle(TenantContext $tenant): array
    {
        return $this->definitions->all($tenant->organisationId);
    }
}
