<?php

namespace App\Modules\Audit\Application\Data;

final readonly class AuditEventPage
{
    /** @param list<AuditEventRecord> $items */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}
}
