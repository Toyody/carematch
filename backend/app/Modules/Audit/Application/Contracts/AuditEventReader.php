<?php

namespace App\Modules\Audit\Application\Contracts;

use App\Modules\Audit\Application\Data\AuditEventListCriteria;
use App\Modules\Audit\Application\Data\StoredAuditEventPage;

interface AuditEventReader
{
    public function list(int $organisationId, AuditEventListCriteria $criteria): StoredAuditEventPage;
}
