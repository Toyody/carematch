<?php

namespace App\Modules\Audit\Application\Contracts;

use App\Modules\Audit\Application\Data\AuditEvent;

interface AuditRecorder
{
    public function record(AuditEvent $event): void;
}
